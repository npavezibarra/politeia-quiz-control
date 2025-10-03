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
        $attempts = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, PoliteiaCourse::getFirstQuizId( $course->id ) );
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
        <tr><td><strong>First Quiz ID:</strong></td><td><?php echo PoliteiaCourse::getFirstQuizId( $course->id ) ?: 'NO ASIGNADO'; ?></td></tr>
        <tr><td><strong>Final Quiz ID:</strong></td><td><?php echo PoliteiaCourse::getFinalQuizId( $course->id ) ?: 'NO ASIGNADO'; ?></td></tr>
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
        $attempts = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, PoliteiaCourse::getFirstQuizId( $course->id ) );
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
 
    // 2. Usar helper para encontrar el curso al que pertenece el quiz
    $course_id = class_exists( 'PoliteiaCourse' )
        ? PoliteiaCourse::getCourseFromQuiz( (int) $quiz_id_completed )
        : 0;

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
    $first_quiz_id = PoliteiaCourse::getFirstQuizId( $course->id );
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

/* REMOVES DESCRIPTION TEXT ONCE QUIZ IS STARTED */

function politeia_add_quiz_script() {
	if ( is_singular( 'sfwd-quiz' ) ) {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const startBtn = document.querySelector('input[name="startQuiz"]');
			const introText = document.querySelector('.ld-tabs-content');

			if (startBtn && introText) {
				startBtn.addEventListener('click', function () {
					introText.style.display = 'none';
				});
			}
		});
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'politeia_add_quiz_script' );

/* ENROLL TO CHECKOUT */

// Automatically redirects the user to the checkout page with the product added to the cart
// when they access a product page that belongs to the 'cursos' category.
add_action('template_redirect', function() {
	if ( is_product() && ! is_admin() && ! is_checkout() ) {
		global $post;

		$product_id = $post->ID;

		// Checks if the product belongs to the 'cursos' category
		if ( has_term('cursos', 'product_cat', $product_id) ) {
			wp_safe_redirect( wc_get_checkout_url() . '?add-to-cart=' . $product_id );
			exit;
		}
	}
});

/**
 * Clears WooCommerce notices on the checkout page when arriving
 * via a direct "add-to-cart" link.
 * This creates a cleaner experience for users coming from a direct link.
 */
add_action( 'template_redirect', 'politeia_clear_notices_on_checkout_from_link' );

function politeia_clear_notices_on_checkout_from_link() {
    // 1. Make sure WooCommerce is active and we are on the checkout page.
    // 2. Check if the URL contains the 'add-to-cart' parameter.
    if ( class_exists('WooCommerce') && is_checkout() && isset( $_GET['add-to-cart'] ) ) {
        // If conditions are met, clear all queued notices.
        wc_clear_notices();
    }
}

/* SOBREESCRIBIR QUIZ DUANDO NO HAY 100% LESSONS COMPLETE */

function politeia_get_final_quiz_access_requirements( $quiz_id, $user_id ) {
    $status = [
        'course_id'            => 0,
        'final_quiz_id'        => 0,
        'is_final_quiz'        => false,
        'is_enrolled'          => false,
        'has_completed'        => false,
        'progress_percentage'  => 0,
        'product_id'           => 0,
        'has_access'           => true,
    ];

    if ( ! $quiz_id ) {
        return $status;
    }

    $analytics = class_exists( 'Politeia_Quiz_Analytics' )
        ? new Politeia_Quiz_Analytics( (int) $quiz_id )
        : null;

    if ( ! $analytics ) {
        return $status;
    }

    $status['course_id']     = $analytics->getCourseId();
    $status['final_quiz_id'] = $analytics->getFinalQuizId();
    $status['is_final_quiz'] = $status['final_quiz_id'] && (int) $quiz_id === (int) $status['final_quiz_id'];

    if ( ! $status['is_final_quiz'] ) {
        return $status;
    }

    $status['has_access'] = false;

    if ( ! $user_id ) {
        return $status;
    }

    if ( ! $status['course_id'] ) {
        return $status;
    }

    $course = new PoliteiaCourse( $status['course_id'] );

    $status['product_id'] = $course->getRelatedProductId();

    $enrolled = $course->isUserEnrolled( $user_id );

    if ( ! $enrolled && $status['product_id'] ) {
        $order_finder = class_exists( 'PoliteiaOrderFinder' ) ? new PoliteiaOrderFinder() : null;
        if ( $order_finder ) {
            $order_id = $order_finder->findOrderForUser( $user_id, $status['product_id'] );
            $enrolled = (bool) $order_id;
        }
    }

    $status['is_enrolled'] = $enrolled;

    if ( function_exists( 'learndash_course_progress' ) ) {
        $progress_data = learndash_course_progress( [
            'user_id'   => $user_id,
            'course_id' => $status['course_id'],
            'array'     => true,
        ] );

        if ( isset( $progress_data['percentage'] ) ) {
            $status['progress_percentage'] = (int) $progress_data['percentage'];
        }

        if ( isset( $progress_data['completed'], $progress_data['total'] ) && (int) $progress_data['total'] > 0 ) {
            $status['has_completed'] = (int) $progress_data['completed'] >= (int) $progress_data['total'];
        }
    }

    if ( ! $status['has_completed'] && method_exists( $course, 'hasCompletedAllLessons' ) ) {
        $status['has_completed'] = $course->hasCompletedAllLessons( $user_id );
    }

    $status['has_access'] = $status['is_enrolled'] && $status['has_completed'];

    return $status;
}

function isFinalQuizAccessible( $quiz_id, $user_id ) {
    $status = politeia_get_final_quiz_access_requirements( $quiz_id, $user_id );

    if ( ! $status['is_final_quiz'] ) {
        return true;
    }

    return (bool) $status['has_access'];
}

add_action( 'template_redirect', 'politeia_block_final_quiz_if_no_progress' );
function politeia_block_final_quiz_if_no_progress() {
    if ( ! is_singular( 'sfwd-quiz' ) ) {
        return;
    }

    global $post;

    $quiz_id = $post->ID;

    $analytics = class_exists( 'Politeia_Quiz_Analytics' )
        ? new Politeia_Quiz_Analytics( (int) $quiz_id )
        : null;

    if ( ! $analytics ) {
        return;
    }

    $first_quiz_id = $analytics->getFirstQuizId();
    $final_quiz_id = $analytics->getFinalQuizId();

    $is_first_quiz = $first_quiz_id && (int) $quiz_id === (int) $first_quiz_id;
    $is_final_quiz = $final_quiz_id && (int) $quiz_id === (int) $final_quiz_id;

    if ( $is_first_quiz ) {
        if ( is_user_logged_in() ) {
            return;
        }

        $login_url    = esc_url( wp_login_url( get_permalink( $quiz_id ) ) );
        $register_url = esc_url( wp_registration_url() );

        $message  = '<div style="text-align:center; padding: 3em; border: 2px dashed #ccc; background: #fff;">';
        $message .= '<h2 style="color:#111; font-size: 1.5em;">' . esc_html__( 'Please log in to start the First Quiz.', 'text-domain' ) . '</h2>';
        $message .= '<div style="margin-top:2em; display:flex; gap:1em; justify-content:center; flex-wrap:wrap;">';
        $message .= '<a href="' . $login_url . '" style="display:inline-block; padding:0.85em 1.5em; background:black; color:#fff; font-weight:bold; border-radius:6px; text-decoration:none;">' . esc_html__( 'Log In', 'text-domain' ) . '</a>';
        if ( get_option( 'users_can_register' ) ) {
            $message .= '<a href="' . $register_url . '" style="display:inline-block; padding:0.85em 1.5em; background:#f5f5f5; color:#111; font-weight:bold; border-radius:6px; text-decoration:none; border:1px solid #ccc;">' . esc_html__( 'Register', 'text-domain' ) . '</a>';
        }
        $message .= '</div>';
        $message .= '</div>';

        wp_die( $message, '', [ 'response' => 200 ] );
    }

    if ( ! $is_final_quiz ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        $login_url = esc_url( wp_login_url( get_permalink( $quiz_id ) ) );

        $message  = '<div style="text-align:center; padding: 3em; border: 2px dashed #ccc; background: #fff;">';
        $message .= '<h2 style="color:#111; font-size: 1.5em;">' . esc_html__( 'Please log in to continue to the Final Quiz.', 'text-domain' ) . '</h2>';
        $message .= '<a href="' . $login_url . '" style="display:inline-block; margin-top:2em; padding:0.85em 1.5em; background:black; color:#fff; font-weight:bold; border-radius:6px; text-decoration:none;">' . esc_html__( 'Log In', 'text-domain' ) . '</a>';
        $message .= '</div>';

        wp_die( $message, '', [ 'response' => 200 ] );
    }

    $user_id = get_current_user_id();

    if ( isFinalQuizAccessible( $quiz_id, $user_id ) ) {
        return;
    }

    $requirements = politeia_get_final_quiz_access_requirements( $quiz_id, $user_id );
    $course_id    = $requirements['course_id'];
    $course_url   = $course_id ? get_permalink( $course_id ) : home_url();

    $message  = '<div style="text-align:center; padding: 3em; border: 2px dashed #ccc; background: #fff;">';

    if ( ! $requirements['is_enrolled'] ) {
        $purchase_url = $requirements['product_id'] ? get_permalink( $requirements['product_id'] ) : $course_url;

        $message .= '<h2 style="color:#111; font-size: 1.5em;">' . esc_html__( 'Purchase the course to unlock the Final Quiz.', 'text-domain' ) . '</h2>';
        $message .= '<a href="' . esc_url( $purchase_url ) . '" style="display:inline-block; margin-top:2em; padding:0.85em 1.5em; background:black; color:#fff; font-weight:bold; border-radius:6px; text-decoration:none;">' . esc_html__( 'View Course Options', 'text-domain' ) . '</a>';
    } else {
        $message .= '<h2 style="color:#111; font-size: 1.5em;">' . esc_html__( 'Complete all lessons to unlock the Final Quiz.', 'text-domain' ) . '</h2>';

        if ( $requirements['progress_percentage'] ) {
            $message .= '<p style="margin-top:1em; color:#555;">' . sprintf( esc_html__( 'Current progress: %s%% complete.', 'text-domain' ), (int) $requirements['progress_percentage'] ) . '</p>';
        }

        $message .= '<a href="' . esc_url( $course_url ) . '" style="display:inline-block; margin-top:2em; padding:0.85em 1.5em; background:black; color:#fff; font-weight:bold; border-radius:6px; text-decoration:none;">' . esc_html__( 'Resume Course', 'text-domain' ) . '</a>';
    }

    $message .= '</div>';

    wp_die( $message, '', [ 'response' => 200 ] );
}


/**
 * FUNCIÓN 1: MOSTRAR INFORMACIÓN EN EL CHECKOUT
 * ---------------------------------------------
 * Su única responsabilidad es mostrar las tablas de compras pasadas y del carrito actual.
 * Ya no se encarga de eliminar productos, solo de mostrar el estado "Ya compraste este curso".
 */
function mostrar_info_usuario_checkout() {
    if ( ! is_user_logged_in() || ! is_checkout() ) {
        return;
    }

    $user_id = get_current_user_id();

    $purchased_product_ids = [];
    $customer_orders = wc_get_orders( array(
        'customer_id' => $user_id,
        'limit'       => -1,
        'status'      => array('completed', 'processing', 'on-hold'),
    ) );

    if ( ! empty($customer_orders) ) {
        foreach ( $customer_orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $purchased_product_ids[] = $item->get_product_id();
            }
        }
    }

    // --- NEW LOGIC START ---
    $display_checkout_info = false;

    // Check if there are any messages from bfg_validar_y_avisar_compra_duplicada
    global $politeia_checkout_messages;
    if ( ! empty( $politeia_checkout_messages ) ) {
        $display_checkout_info = true;
    }

    // Check if any cart item is a 'course' and has been purchased
    if ( ! $display_checkout_info ) { // Only proceed if not already marked for display by messages
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();

            // Check if the product has the 'courses' category
            $terms = get_the_terms( $product_id, 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    if ( $term->slug === 'courses' && in_array( $product_id, $purchased_product_ids ) ) {
                        $display_checkout_info = true;
                        break 2; // Exit both inner and outer loops
                    }
                }
            }
        }
    }

    // Additionally, check if there are any past orders to display in the "Purchases" section
    if ( ! $display_checkout_info && ! empty( $customer_orders ) ) {
        $display_checkout_info = true;
    }

    // If no conditions met, do not print the div
    if ( ! $display_checkout_info ) {
        return;
    }
    // --- NEW LOGIC END ---

    echo '<div id="repeat-course-purchase-message" style="border:1px solid #d6d9dd; padding:15px; margin-bottom:20px;">';

    // Mostrar mensajes desplazados al div de arriba
    // global $politeia_checkout_messages; // Already globalized above
    echo '<div id="purchases-table">';
    if ( ! empty( $politeia_checkout_messages ) ) {
        foreach ( $politeia_checkout_messages as $msg ) {
            echo '<div class="woocommerce-message" role="alert" style="color:red;">' . $msg . '</div>';
        }
    }
    echo '</div>';

    $user_info = get_userdata( $user_id );
    echo '<strong>User Name:</strong> ' . esc_html( $user_info->user_login ) . '<br>';
    echo '<strong>Purchases:</strong><br>';

    if ( ! empty( $customer_orders ) ) {
        echo '<table style="width:100%; border-collapse:collapse; margin-top:10px;">';
        echo '<thead><tr>
                <th style="border:1px solid #ccc; padding:8px;">Order ID</th>
                <th style="border:1px solid #ccc; padding:8px;">Status</th>
                <th style="border:1px solid #ccc; padding:8px;">Total</th>
                <th style="border:1px solid #ccc; padding:8px;">Date</th>
                <th style="border:1px solid #ccc; padding:8px;">Items</th>
                <th style="border:1px solid #ccc; padding:8px;">Category</th>
              </tr></thead><tbody>';
        foreach ( $customer_orders as $order ) {
            echo '<tr>';
            echo '<td style="border:1px solid #ccc; padding:8px;">#' . esc_html( $order->get_id() ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px;">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px;">' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px;">' . esc_html( $order->get_date_created()->date( 'Y-m-d' ) ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px;">';
            $categories = [];
            foreach ( $order->get_items() as $item ) {
                echo esc_html( $item->get_name() ) . ' (x' . esc_html( $item->get_quantity() ) . ')<br>';
                $terms = get_the_terms( $item->get_product_id(), 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) { $categories[] = $term->name; }
                }
            }
            echo '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px;">' . esc_html( implode( ', ', array_unique( $categories ) ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No purchases found.</p>';
    }

    // Tabla del carrito
    echo '<div id="debug-purchase-table" style="display:none">';
    echo '<h3 style="margin-top:30px;">Current Cart</h3>';
    echo '<table style="width:100%; border-collapse:collapse;">';
    echo '<thead><tr>
            <th style="border:1px solid #ccc; padding:8px;">Product</th>
            <th style="border:1px solid #ccc; padding:8px;">Category</th>
            <th style="border:1px solid #ccc; padding:8px;">Related Course</th>
            <th style="border:1px solid #ccc; padding:8px;">Course ID</th>
            <th style="border:1px solid #ccc; padding:8px;">Estado</th>
          </tr></thead><tbody>';

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();

        $related_course_id = '';
        $related_course_raw = get_post_meta( $product_id, '_related_course', true );
        $course_data = maybe_unserialize( $related_course_raw );
        if ( is_array( $course_data ) && ! empty( $course_data ) ) {
            $related_course_id = (int) reset( $course_data );
        } elseif ( is_numeric( $course_data ) ) {
            $related_course_id = (int) $course_data;
        }

        echo '<tr>';
        echo '<td style="border:1px solid #ccc; padding:8px;">' . esc_html( $product->get_name() ) . '</td>';
        echo '<td style="border:1px solid #ccc; padding:8px;">' . wc_get_product_category_list( $product_id ) . '</td>';
        echo '<td style="border:1px solid #ccc; padding:8px;">' . ( $related_course_id ? 'true' : 'false' ) . '</td>';
        echo '<td style="border:1px solid #ccc; padding:8px;">' . esc_html( $related_course_id ) . '</td>';
        echo '<td style="border:1px solid #ccc; padding:8px;">' . ( in_array( $product_id, $purchased_product_ids ) ? '<span style="color:red;">Curso ya comprado</span>' : 'Disponible para comprar' ) . '</td>';
        echo '</tr>';
    }

    global $politeia_removed_from_cart;
    if ( ! empty( $politeia_removed_from_cart ) ) {
        foreach ( $politeia_removed_from_cart as $removed ) {
            echo '<tr>';
            echo '<td style="border:1px solid #ccc; padding:8px; color:#999;">' . esc_html( $removed['product_name'] ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px; color:#999;">' . $removed['categories'] . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px; color:#999;">true</td>';
            echo '<td style="border:1px solid #ccc; padding:8px; color:#999;">' . esc_html( $removed['related_course_id'] ) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:8px; color:red;">Curso ya comprado</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
}
add_action( 'woocommerce_before_checkout_form', 'mostrar_info_usuario_checkout', 5 );



/**
 * FUNCIÓN 2: VALIDAR Y MOSTRAR AVISO PERSONALIZADO (Versión Modificada)
 * -------------------------------------------------------------------
 * Valida el carrito y si un curso ya fue comprado:
 * 1. Lo elimina del carrito.
 * 2. Muestra un aviso personalizado con un enlace directo al curso.
 */
function bfg_validar_y_avisar_compra_duplicada() {
    if ( is_admin() || ! is_user_logged_in() || ! WC()->cart ) {
        return;
    }

    global $politeia_removed_from_cart, $politeia_checkout_messages;
    if ( ! isset( $politeia_removed_from_cart ) ) {
        $politeia_removed_from_cart = [];
    }
    if ( ! isset( $politeia_checkout_messages ) ) {
        $politeia_checkout_messages = [];
    }

    $user_id = get_current_user_id();

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product_id = $cart_item['product_id'];

        if ( wc_customer_bought_product( '', $user_id, $product_id ) ) {
            $related_course_id = '';
            $related_course_raw = get_post_meta( $product_id, '_related_course', true );
            $course_data = maybe_unserialize( $related_course_raw );

            if ( is_array( $course_data ) && ! empty( $course_data ) ) {
                $related_course_id = (int) reset( $course_data );
            } elseif ( is_numeric( $course_data ) ) {
                $related_course_id = (int) $course_data;
            }

            $titulo_curso = $related_course_id ? get_the_title( $related_course_id ) : '';
            $url_curso = $related_course_id ? get_permalink( $related_course_id ) : '';

            // Guardar mensaje
            $mensaje_personalizado = 'Ya compraste este curso.';
            if ( $related_course_id && get_post_status( $related_course_id ) === 'publish' ) {
                $mensaje_personalizado .= sprintf(
                    '<br>Puedes acceder en <a href="%s" style="font-weight:bold; text-decoration:underline;">%s</a>',
                    esc_url( $url_curso ),
                    esc_html( $titulo_curso )
                );
            }

            $politeia_checkout_messages[] = $mensaje_personalizado;

            // Guardar para mostrarlo en tabla
            $politeia_removed_from_cart[] = [
                'product_id'          => $product_id,
                'product_name'        => get_the_title( $product_id ),
                'categories'          => wc_get_product_category_list( $product_id ),
                'related_course_id'   => $related_course_id,
                'related_course_name' => $titulo_curso,
                'related_course_url'  => $url_curso,
            ];

            // Eliminar producto del carrito
            WC()->cart->remove_cart_item( $cart_item_key );
        }
    }
}
add_action( 'woocommerce_before_checkout_form', 'bfg_validar_y_avisar_compra_duplicada', 1 );
add_action( 'woocommerce_before_cart', 'bfg_validar_y_avisar_compra_duplicada', 1 );


/* FUNCION AJAX */


/**
* Manejador AJAX para obtener la última actividad del quiz.
* Esta función es invocada por las peticiones AJAX desde el cliente.
*/
function politeia_get_latest_quiz_activity() {
    error_log( 'AJAX Debug: politeia_get_latest_quiz_activity function initiated.' );

    $received_action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : 'NOT_SET';
    error_log( 'AJAX Debug: Received action: ' . $received_action );

    if ( $received_action !== 'get_latest_quiz_activity' ) {
        error_log( 'AJAX Debug: ERROR - Action mismatch or not set. Expected "get_latest_quiz_activity", received: ' . $received_action );
        wp_send_json_error( 'Invalid AJAX action received by handler.' );
        exit;
    }

    global $wpdb;

    $current_quiz_post_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
    $current_user_id = get_current_user_id();
    
    // NEW: Get the baseline activity ID from the client (what shortcode had at page load)
    $baseline_activity_id = isset( $_POST['baseline_activity_id'] ) ? intval( $_POST['baseline_activity_id'] ) : 0;

    error_log( 'AJAX Debug: Params - Quiz Post ID: ' . $current_quiz_post_id . ', Current User ID: ' . $current_user_id . ', Baseline Activity ID: ' . $baseline_activity_id );

    if ( ! $current_quiz_post_id || ! $current_user_id ) {
        error_log( 'AJAX Debug: ERROR - Missing essential IDs for AJAX request.' );
        wp_send_json_error( 'Missing essential IDs for AJAX request.' );
        exit;
    }

    // --- MODIFIED: Look for the CURRENT USER'S LATEST attempt that is NEWER than baseline ---
    $current_user_latest_activity_data = null;
    $user_specific_main_activity = $wpdb->get_row( $wpdb->prepare(
        "
        SELECT ua.activity_id, ua.activity_started, ua.activity_completed
        FROM {$wpdb->prefix}learndash_user_activity AS ua
        INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
        ON ua.activity_id = uam.activity_id
        WHERE ua.user_id = %d
        AND ua.activity_type = 'quiz'
        AND uam.activity_meta_key = 'quiz'
        AND uam.activity_meta_value+0 = %d
        AND ua.activity_id > %d
        ORDER BY ua.activity_id DESC
        LIMIT 1
        ",
        $current_user_id,
        $current_quiz_post_id,
        $baseline_activity_id  // Only get attempts NEWER than baseline
    ) );

    if ($user_specific_main_activity) {
        error_log( 'AJAX Debug: Found NEW user-specific activity (ID: ' . $user_specific_main_activity->activity_id . ') newer than baseline (' . $baseline_activity_id . ')' );

        $raw_activity_meta_results_for_user_latest = $wpdb->get_results( $wpdb->prepare(
            "SELECT activity_meta_key, activity_meta_value
            FROM {$wpdb->prefix}learndash_user_activity_meta
            WHERE activity_id = %d",
            $user_specific_main_activity->activity_id
        ), OBJECT );

        $activity_meta_map_for_user_latest = [];
        foreach ($raw_activity_meta_results_for_user_latest as $row) {
            $activity_meta_map_for_user_latest[$row->activity_meta_key] = $row->activity_meta_value;
        }

        error_log( 'AJAX Debug: User-specific raw meta map for NEW ID ' . $user_specific_main_activity->activity_id . ': ' . print_r($activity_meta_map_for_user_latest, true) );

        // Check if we have essential score data - if not, continue polling
        if (isset($activity_meta_map_for_user_latest['total_points']) && isset($activity_meta_map_for_user_latest['percentage'])) {
            $current_user_latest_activity_data = [
                'activity_id' => $user_specific_main_activity->activity_id,
                'started' => date( 'Y-m-d H:i:s', $user_specific_main_activity->activity_started ),
                'completed' => $user_specific_main_activity->activity_completed ? date( 'Y-m-d H:i:s', $user_specific_main_activity->activity_completed ) : 'In Progress',
                'duration' => $user_specific_main_activity->activity_completed ? ($user_specific_main_activity->activity_completed - $user_specific_main_activity->activity_started) : 0,
                'score_data' => [
                    'score' => isset($activity_meta_map_for_user_latest['score']) ? intval($activity_meta_map_for_user_latest['score']) : 0,
                    'total_points' => isset($activity_meta_map_for_user_latest['total_points']) ? intval($activity_meta_map_for_user_latest['total_points']) : 0,
                    'percentage' => isset($activity_meta_map_for_user_latest['percentage']) ? round(floatval($activity_meta_map_for_user_latest['percentage'])) : 0,
                    'passed' => isset($activity_meta_map_for_user_latest['pass']) ? (bool)intval($activity_meta_map_for_user_latest['pass']) : false,
                ],
            ];
            error_log( 'AJAX Debug: NEW attempt details SUCCESSFULLY BUILT with complete score data.' );
        } else {
            error_log( 'AJAX Debug: NEW attempt found (ID: ' . $user_specific_main_activity->activity_id . ') but ESSENTIAL META DATA NOT YET AVAILABLE. Continue polling...' );
        }
    } else {
        error_log( 'AJAX Debug: No NEW user-specific activity found yet (looking for ID > ' . $baseline_activity_id . ')' );
    }

    // --- Fetch ALL relevant quiz attempts (for updated Promedio Polis) ---
    $all_global_attempts_percentages = [];
    $attempts_base_query_sql = "
        SELECT ua.activity_id
        FROM {$wpdb->prefix}learndash_user_activity AS ua
        INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
        ON ua.activity_id = uam.activity_id
        WHERE ua.activity_type = 'quiz'
        AND ua.activity_completed IS NOT NULL
        AND uam.activity_meta_key = 'quiz'
        AND uam.activity_meta_value+0 = %d
        ORDER BY ua.activity_id ASC
    ";
    $base_query_params = [$current_quiz_post_id];

    $all_global_activity_ids = $wpdb->get_results( $wpdb->prepare( $attempts_base_query_sql, ...$base_query_params ) );

    foreach ( $all_global_activity_ids as $activity_row ) {
        $percentage_val = $wpdb->get_var( $wpdb->prepare( "
            SELECT activity_meta_value
            FROM {$wpdb->prefix}learndash_user_activity_meta
            WHERE activity_id = %d
            AND activity_meta_key = 'percentage'
            LIMIT 1
        ", $activity_row->activity_id ) );

        if ( $percentage_val !== null && is_numeric($percentage_val) ) {
            $all_global_attempts_percentages[] = [
                'id' => $activity_row->activity_id,
                'percentage' => round(floatval($percentage_val))
            ];
        }
    }

    error_log( 'AJAX Debug: all_global_attempts_percentages (for updated Promedio Polis): ' . print_r($all_global_attempts_percentages, true) );

    // --- Send response ---
    // SUCCESS: We found a new attempt with complete score data
    if ( $current_user_latest_activity_data !== null ) {
        wp_send_json_success( array(
            'all_attempts_percentages' => $all_global_attempts_percentages,
            'latest_activity_details' => $current_user_latest_activity_data,
            'new_attempt_found' => true,
            'message' => 'New attempt found with complete data'
        ) );
    } 
    // CONTINUE POLLING: Either no new attempt found, or new attempt found but incomplete data
    else {
        $message = $user_specific_main_activity ? 
            'New attempt found but data incomplete - continue polling' : 
            'No new attempt found yet - continue polling';
            
        error_log( 'AJAX Debug: ' . $message );
        wp_send_json_error( array(
            'message' => $message,
            'baseline_id' => $baseline_activity_id,
            'continue_polling' => true
        ) );
    }
    exit;
}

// Estos "hooks" son los que registran tu función AJAX con WordPress.
// Se ejecutan automáticamente cuando WordPress procesa una petición a admin-ajax.php
// con la acción 'get_latest_quiz_activity'.
// 'wp_ajax_' es para usuarios logueados.
add_action( 'wp_ajax_get_latest_quiz_activity', 'politeia_get_latest_quiz_activity' );
// 'wp_ajax_nopriv_' es para usuarios no logueados (si quieres que vean los resultados).
add_action( 'wp_ajax_nopriv_get_latest_quiz_activity', 'politeia_get_latest_quiz_activity' );