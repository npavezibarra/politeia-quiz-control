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

/* CHANGE ORDER STATUS IN COURSE PAGE */

add_action( 'wp', 'politeia_maybe_complete_order_from_debug_table' );

function politeia_maybe_complete_order_from_debug_table() {
    if ( ! is_singular( 'sfwd-courses' ) ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) return;

    $course_id = get_the_ID();
    $course = new PoliteiaCourse( $course_id );
    $product_id = $course->getRelatedProductId();

    if ( ! $product_id ) return;

    $orderFinder = new PoliteiaOrderFinder();
    $order_id = $orderFinder->findOrderForUser( $user_id, $product_id );

    if ( ! $order_id ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_status() === 'completed' ) return;

    // Verificar si se completó el First Quiz
    $first_quiz_completed = false;
    if ( class_exists('Politeia_Quiz_Stats') ) {
        $attempts = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, $course->getFirstQuizId() );
        $first_quiz_completed = ! empty( $attempts );
    }

    // Si se completó el First Quiz y el usuario compró el curso, completar la orden
    if ( $first_quiz_completed ) {
        $order->update_status( 'completed', 'Orden completada automáticamente desde vista de curso.' );
    }
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
 
     if ( empty( $user_id ) || empty( $quiz_id_completed ) ) {
         error_log("Faltan datos esenciales para procesar la orden.");
         return;
     }
 
     error_log("HOOK learndash_quiz_completed triggered for user ID: $user_id and quiz ID: $quiz_id_completed");
     error_log("Comprobando finalización de quiz. User ID: $user_id | Quiz ID: $quiz_id_completed");
 
     // 2. Usar la clase para encontrar el curso al que pertenece el quiz
     $course_id = 0;
     $course_query = new WP_Query([
         'post_type'  => 'sfwd-courses',
         'fields'     => 'ids',
         'posts_per_page' => 1,
         'meta_query' => [
             'relation' => 'OR',
             [
                 'key'   => '_first_quiz_id',
                 'value' => $quiz_id_completed
             ],
             [
                 'key'   => '_final_quiz_id',
                 'value' => $quiz_id_completed
             ]
         ]
     ]);
 
     if ( $course_query->have_posts() ) {
         $course_id = $course_query->posts[0];
     }
 
     if ( ! $course_id ) {
         error_log("No se encontró curso asociado al quiz ID: $quiz_id_completed");
         return;
     }
 
     // 3. Obtener producto relacionado usando la clase
     $course     = new PoliteiaCourse( $course_id );
     $product_id = $course->getRelatedProductId();
 
     if ( ! $product_id ) {
         error_log("No se encontró producto relacionado al curso ID: $course_id");
         return;
     }
 
     // 4. Buscar órdenes 'course-on-hold' del usuario
     $orders = wc_get_orders([
         'customer_id' => $user_id,
         'status'      => [ 'course-on-hold' ],
         'limit'       => -1,
     ]);
 
     if ( empty( $orders ) ) {
         error_log("No se encontraron órdenes con estado 'course-on-hold' para user ID: $user_id");
         return;
     }
 
     // 5. Verificar que el producto está en la orden y actualizarla
     foreach ( $orders as $order ) {
         foreach ( $order->get_items() as $item ) {
             if ( (int) $item->get_product_id() === (int) $product_id ) {
                 $order->update_status( 'completed', 'Orden completada automáticamente tras rendir un quiz clave del curso.' );
                 error_log("Orden ID {$order->get_id()} actualizada a 'completed' para user ID: $user_id");
                 return;
             }
         }
     }
 
     error_log("No se encontró producto $product_id en ninguna orden 'course-on-hold' del usuario.");
 }
 

/**
 * Si NO se ha completado el First Quiz, muestra un aviso alineado a la derecha
 * justo después de "Course Content".
 */
add_action( 'learndash-course-heading-after', 'politeia_show_quiz_gate_message', 10, 2 );
function politeia_show_quiz_gate_message( $course_id, $user_id ) {
    if ( ! is_singular( 'sfwd-courses' ) ) {
        return;
    }

    $course        = new PoliteiaCourse( $course_id );
    $first_quiz_id = $course->getFirstQuizId();
    if ( ! $first_quiz_id ) {
        return;
    }

    $completed = false;
    if ( class_exists( 'Politeia_Quiz_Stats' ) ) {
        $attempts  = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, $first_quiz_id );
        $completed = ! empty( $attempts );
    }

    if ( ! $completed ) {
        // Usamos esc_html__() para que esto pueda traducirse en los .po/.mo
        $msg = esc_html__( 'To access lessons finish the First Quiz', 'politeia-quiz-control' );

        echo '<div class="politeia-course-content-msg" '
           . 'style="display:inline-block; float:right; margin-top:-0.2em; font-weight:bold; color:#444">'
           . $msg
           . '</div>';
    }
}

/* SHOW RESULT */

add_action( 'wp_footer', 'politeia_add_quiz_result_button' );

function politeia_add_quiz_result_button() {
	if ( ! is_singular( 'sfwd-quiz' ) ) {
		return;
	}

	// Asegura que el usuario esté conectado y se haya completado el quiz
	$user_id = get_current_user_id();
	if ( ! $user_id ) return;

	$quiz_id = get_the_ID();
	$related_course_id = get_post_meta( $quiz_id, '_related_course', true );

	if ( ! $related_course_id ) return;

	// Obtener ID del producto que vende este curso
	$product_id = wc_get_products([
		'limit'      => 1,
		'status'     => 'publish',
		'type'       => 'simple',
		'meta_key'   => '_related_course',
		'meta_value' => $related_course_id,
		'return'     => 'ids',
	])[0] ?? null;

	if ( ! $product_id ) return;

	$course_url  = get_permalink( $related_course_id );
	$product_url = get_permalink( $product_id );
	$has_bought  = wc_customer_bought_product( '', $user_id, $product_id );

	$link_url  = $has_bought ? $course_url : $product_url;
	$link_text = $has_bought ? __( 'Ir al Curso', 'politeia' ) : __( 'Comprar Curso', 'politeia' );

	?>
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			const actionsContainer = document.querySelector('.ld-quiz-actions');
			if (!actionsContainer) return;

			const button = document.createElement('a');
			button.href = '<?php echo esc_url( $link_url ); ?>';
			button.innerText = '<?php echo esc_html( $link_text ); ?>';
			button.className = 'wpProQuiz_button';
			button.style.marginLeft = '10px';

			actionsContainer.appendChild(button);
		});
	</script>
	<?php
}
