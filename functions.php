<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carga la clase de stats (asegúrate de que la ruta sea correcta).
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-politeia-quiz-stats.php';

/* =============================================================================
 * 1) REDIRECT BUTTON "START COURSE" → INSCRIPTION + REDIRECT TO LESSON
 * ============================================================================= */
add_action( 'template_redirect', 'politeia_handle_course_join' );
function politeia_handle_course_join() {
    if ( is_singular( 'sfwd-courses' )
         && ! empty( $_POST['course_join'] )
         && ! empty( $_POST['course_id'] )
    ) {
        $user_id   = get_current_user_id();
        $course_id = absint( $_POST['course_id'] );

        // Nonce check
        if ( ! wp_verify_nonce( $_POST['course_join'], 'course_join_' . $user_id . '_' . $course_id ) ) {
            return;
        }

        // Inscribir al usuario
        if ( function_exists( 'ld_update_course_access' ) ) {
            ld_update_course_access( $user_id, $course_id );
        } else {
            sfwd_lms()->learner()->add_course_access( $user_id, $course_id );
        }

        // Redirigir al primer lesson o resume_link
        $redirect = ! empty( $_POST['redirect_to'] )
                    ? esc_url_raw( $_POST['redirect_to'] )
                    : get_permalink( $course_id );

        wp_safe_redirect( $redirect );
        exit;
    }
}

/**
 * =============================================================================
 * TABLA DE DEPURACIÓN (VERSIÓN REFACTORIZADA CON CLASES)
 * =============================================================================
 */
add_filter( 'the_content', 'politeia_course_debug_table_refactored', 20 );

function politeia_course_debug_table_refactored( $content ) {
    if ( ! is_singular( 'sfwd-courses' ) ) {
        return $content;
    }

    // --- 1. Usar las clases para obtener los datos ---
    $user_id   = get_current_user_id();
    $user_data = get_userdata( $user_id );
    $course    = new PoliteiaCourse( get_the_ID() );
    $orderFinder = new PoliteiaOrderFinder();

    $product_id = $course->getRelatedProductId();
    $order_id   = $orderFinder->findOrderForUser( $user_id, $product_id );

    $first_quiz_completed = false; // Asumiendo que esta lógica puede ir en otro lado o se simplifica.
    if ( class_exists('Politeia_Quiz_Stats') ) {
        $attempts = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, $course->getFirstQuizId() );
        $first_quiz_completed = ! empty( $attempts );
    }

    // --- 2. Renderizar la tabla con los datos obtenidos ---
    ob_start();
    ?>
    <table style="width:60%; margin-top:20px; border-collapse:collapse; display: none;">
        <tr><td><strong>User ID:</strong></td><td><?php echo esc_html( $user_id ); ?></td></tr>
        <tr><td><strong>Username:</strong></td><td><?php echo esc_html( $user_data->user_login ); ?></td></tr>
        <tr><td><strong>Course ID:</strong></td><td><?php echo esc_html( $course->id ); ?></td></tr>
        <tr><td><strong>Related Product:</strong></td><td><?php echo $product_id ?: 'NO ENCONTRADO'; ?></td></tr>
        <tr><td><strong>First Quiz ID:</strong></td><td><?php echo $course->getFirstQuizId() ?: 'NO ASIGNADO'; ?></td></tr>
        <tr><td><strong>Final Quiz ID:</strong></td><td><?php echo $course->getFinalQuizId() ?: 'NO ASIGNADO'; ?></td></tr>
        <tr><td><strong>First Quiz Completed:</strong></td><td><?php echo $first_quiz_completed ? 'TRUE' : 'FALSE'; ?></td></tr>
        <tr><td><strong>ID de Orden (wp_posts.ID):</strong></td><td><?php echo $order_id ?: 'NO'; ?></td></tr>
        <tr><td><strong>Bought?</strong></td><td><?php echo $order_id ? 'YES' : 'NO'; ?></td></tr>
    </table>
    <?php
    return ob_get_clean() . $content;
}


/**
 * =============================================================================
 * CAMBIAR ESTADO DE ORDEN (VERSIÓN REFACTORIZADA CON CLASES)
 * =============================================================================
 */
add_action( 'learndash_quiz_completed', 'politeia_complete_order_on_quiz_refactored', 10, 2 );

function politeia_complete_order_on_quiz_refactored( $quiz_data, $user ) {
    
    // 1. Obtener datos iniciales
    $user_id = $user->ID;
    $quiz_id_completed = $quiz_data['quiz'];

    // 2. Usar la clase para encontrar el curso al que pertenece el quiz
    $course_id = 0;
    // (Esta lógica de búsqueda inversa es específica y se queda aquí por ahora)
    $course_query = new WP_Query([
        'post_type'  => 'sfwd-courses', 'fields' => 'ids', 'posts_per_page' => 1,
        'meta_query' => [ 'relation' => 'OR',
            [ 'key' => '_first_quiz_id', 'value' => $quiz_id_completed ],
            [ 'key' => '_final_quiz_id', 'value' => $quiz_id_completed ],
        ]
    ]);
    if ( $course_query->have_posts() ) { $course_id = $course_query->posts[0]; }
    if ( ! $course_id ) { return; }

    // 3. Usar las clases para el resto de la lógica
    $course     = new PoliteiaCourse( $course_id );
    $product_id = $course->getRelatedProductId();
    
    if ( ! $product_id ) { return; }

    // Aquí no usamos el OrderFinder porque la lógica es más simple:
    // solo buscamos órdenes con un estado específico.
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => [ 'course-on-hold' ],
        'limit'       => -1,
    ]);
    if ( empty( $orders ) ) { return; }

    // 4. Actualizar la orden
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() === (int) $product_id ) {
                $order->update_status( 'completed', 'Orden completada automáticamente tras rendir un quiz clave del curso.' );
                return;
            }
        }
    }
}