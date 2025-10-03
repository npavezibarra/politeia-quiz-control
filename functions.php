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
 * CAMBIAR ESTADO DE ORDEN (VERSIÓN REFACTORIZADA CON CLASES)
 * =============================================================================
 */



 add_action( 'learndash_quiz_completed', 'politeia_complete_order_on_quiz_refactored', 10, 2 );

 function politeia_complete_order_on_quiz_refactored( $quiz_data, $user ) {
     // 1. Obtener datos iniciales
     $user_id = $user->ID;
     $quiz_id_completed = $quiz_data['quiz'];

     if ( empty( $user_id ) || empty( $quiz_id_completed ) ) {
         return;
     }
 
    // 2. Usar helper para encontrar el curso al que pertenece el quiz
    $course_id = class_exists( 'PoliteiaCourse' )
        ? PoliteiaCourse::getCourseFromQuiz( (int) $quiz_id_completed )
        : 0;

    if ( ! $course_id ) {
        return;
    }
 
     // 3. Obtener producto relacionado usando la clase
     $course     = new PoliteiaCourse( $course_id );
     $product_id = $course->getRelatedProductId();
 
     if ( ! $product_id ) {
         return;
     }
 
     // 4. Buscar órdenes 'course-on-hold' del usuario
     $orders = wc_get_orders([
         'customer_id' => $user_id,
         'status'      => [ 'course-on-hold' ],
         'limit'       => -1,
     ]);

     if ( empty( $orders ) ) {
         return;
     }
 
     // 5. Verificar que el producto está en la orden y actualizarla
     foreach ( $orders as $order ) {
         foreach ( $order->get_items() as $item ) {
             if ( (int) $item->get_product_id() === (int) $product_id ) {
                 $order->update_status( 'completed', 'Orden completada automáticamente tras rendir un quiz clave del curso.' );
                 return;
             }
         }
     }

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
        $course_id = class_exists( 'PoliteiaCourse' )
            ? PoliteiaCourse::getCourseFromQuiz( (int) $quiz_id )
            : 0;

        if ( ! $course_id ) {
            $course_id = (int) get_post_meta( $quiz_id, '_related_course', true );
        }

        if ( ! $course_id ) {
            return;
        }

        $course_object = class_exists( 'PoliteiaCourse' )
            ? new PoliteiaCourse( $course_id )
            : null;

        $product_id = $course_object ? $course_object->getRelatedProductId() : 0;

        if ( ! $product_id ) {
            $product_id = wc_get_products([
                'limit'      => 1,
                'status'     => 'publish',
                'type'       => 'simple',
                'meta_key'   => '_related_course',
                'meta_value' => $course_id,
                'return'     => 'ids',
            ])[0] ?? 0;
        }

        if ( ! $product_id ) {
            return;
        }

        $course_url    = get_permalink( $course_id );
        $product_url   = get_permalink( $product_id );
        $has_access    = $course_object ? $course_object->isUserEnrolled( $user_id ) : false;
        $has_purchased = wc_customer_bought_product( '', $user_id, $product_id );

        if ( ! $has_purchased && class_exists( 'PoliteiaOrderFinder' ) ) {
            $order_finder = new PoliteiaOrderFinder();
            $order_id     = $order_finder->findOrderForUser( $user_id, $product_id );

            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order && $order->has_status( [ 'completed', 'processing' ] ) ) {
                    $has_purchased = true;
                }
            }
        }

        $link_url  = ( $has_access || $has_purchased ) ? $course_url : $product_url;
        $link_text = ( $has_access || $has_purchased ) ? __( 'Ir al Curso', 'politeia' ) : __( 'Comprar Curso', 'politeia' );

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


function politeia_ajax_mostrar_resultados_curso() {
    check_ajax_referer( 'politeia_course_results', 'nonce' );

    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Inicia sesión para revisar tus resultados.', 'politeia-quiz-control' ),
            ),
            403
        );
    }

    $course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
    $user_id   = get_current_user_id();

    if ( ! $course_id || ! $user_id ) {
        wp_send_json_error( array(
            'message' => __( 'No pudimos identificar el curso solicitado.', 'politeia-quiz-control' ),
        ) );
    }

    if ( function_exists( 'sfwd_lms_has_access' ) && ! sfwd_lms_has_access( $course_id, $user_id ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'No tienes acceso a los resultados de este curso.', 'politeia-quiz-control' ),
            ),
            403
        );
    }

    $first_quiz_id = isset( $_POST['first_quiz_id'] ) ? absint( wp_unslash( $_POST['first_quiz_id'] ) ) : 0;
    $final_quiz_id = isset( $_POST['final_quiz_id'] ) ? absint( wp_unslash( $_POST['final_quiz_id'] ) ) : 0;

    if ( ! $first_quiz_id ) {
        $first_quiz_id = class_exists( 'PoliteiaCourse' ) ? PoliteiaCourse::getFirstQuizId( $course_id ) : 0;
    }

    if ( ! $final_quiz_id ) {
        $final_quiz_id = class_exists( 'PoliteiaCourse' ) ? PoliteiaCourse::getFinalQuizId( $course_id ) : 0;
    }

    $messages = array();

    if ( ! $first_quiz_id ) {
        $messages[] = __( 'No encontramos un First Quiz configurado para este curso.', 'politeia-quiz-control' );
    }

    if ( ! $final_quiz_id ) {
        $messages[] = __( 'No encontramos un Final Quiz configurado para este curso.', 'politeia-quiz-control' );
    }

    $first_summary = null;
    $final_summary = null;

    if ( $first_quiz_id && class_exists( 'Politeia_Quiz_Stats' ) ) {
        $first_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $user_id, $first_quiz_id );
        if ( ! $first_summary ) {
            $messages[] = __( 'Aún no registramos un resultado del First Quiz para este curso.', 'politeia-quiz-control' );
        }
    }

    if ( $final_quiz_id && class_exists( 'Politeia_Quiz_Stats' ) ) {
        $final_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $user_id, $final_quiz_id );
        if ( ! $final_summary ) {
            $messages[] = __( 'Aún no registramos un resultado del Final Quiz.', 'politeia-quiz-control' );
        }
    }

    $delta        = null;
    $days_elapsed = null;

    if ( $first_summary && $final_summary ) {
        $delta = (int) $final_summary['percentage'] - (int) $first_summary['percentage'];

        if ( ! empty( $first_summary['completed_timestamp'] ) && ! empty( $final_summary['completed_timestamp'] ) ) {
            $seconds_diff = max( 0, (int) $final_summary['completed_timestamp'] - (int) $first_summary['completed_timestamp'] );
            $days_elapsed = (int) floor( $seconds_diff / DAY_IN_SECONDS );
        }
    }

    $response = array(
        'course_id' => $course_id,
        'first_quiz' => array(
            'quiz_id' => $first_quiz_id,
            'summary' => $first_summary,
        ),
        'final_quiz' => array(
            'quiz_id' => $final_quiz_id,
            'summary' => $final_summary,
        ),
        'metrics' => array(
            'score_delta' => $delta,
            'days_elapsed' => $days_elapsed,
        ),
        'messages' => $messages,
    );

    wp_send_json_success( $response );
}

add_action( 'wp_ajax_mostrar_resultados_curso', 'politeia_ajax_mostrar_resultados_curso' );

/* FUNCION AJAX */


/**
* Manejador AJAX para obtener la última actividad del quiz.
* Esta función es invocada por las peticiones AJAX desde el cliente.
*/
function politeia_get_latest_quiz_activity() {
    check_ajax_referer( 'politeia_quiz_stats', 'nonce' );

    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wp_send_json_error(
            array(
                'message'          => __( 'Tu sesión no es válida para consultar este quiz.', 'politeia-quiz-control' ),
                'continue_polling' => false,
            ),
            403
        );
    }

    $requested_user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
    $quiz_id           = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
    $baseline_activity = isset( $_POST['baseline_activity_id'] ) ? absint( wp_unslash( $_POST['baseline_activity_id'] ) ) : 0;
    $current_user_id   = get_current_user_id();

    if ( $requested_user_id && $requested_user_id !== $current_user_id ) {
        wp_send_json_error(
            array(
                'message'          => __( 'No puedes revisar el puntaje de otra cuenta.', 'politeia-quiz-control' ),
                'continue_polling' => false,
            ),
            403
        );
    }

    if ( ! $quiz_id ) {
        wp_send_json_error(
            array(
                'message'          => __( 'Faltan datos para consultar el quiz.', 'politeia-quiz-control' ),
                'continue_polling' => false,
            )
        );
    }

    $related_course_id = 0;
    if ( class_exists( 'PoliteiaCourse' ) ) {
        $related_course_id = (int) PoliteiaCourse::getCourseFromQuiz( $quiz_id );
    }

    if ( $related_course_id && function_exists( 'sfwd_lms_has_access' ) && ! sfwd_lms_has_access( $related_course_id, $current_user_id ) ) {
        wp_send_json_error(
            array(
                'message'          => __( 'No tienes permisos para consultar este quiz.', 'politeia-quiz-control' ),
                'continue_polling' => false,
            ),
            403
        );
    }

    global $wpdb;

    $cache_key     = sprintf( 'politeia_latest_attempt_%d_%d', $current_user_id, $quiz_id );
    $cached_payload = get_transient( $cache_key );

    if ( false !== $cached_payload && isset( $cached_payload['latest_activity_id'] ) ) {
        if ( $cached_payload['latest_activity_id'] > $baseline_activity ) {
            $cached_payload['status'] = 'ready';
            wp_send_json_success( $cached_payload );
        }
    }

    $activity = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT ua.activity_id, ua.activity_started, ua.activity_completed
            FROM {$wpdb->prefix}learndash_user_activity AS ua
            INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS quiz_meta
                ON ua.activity_id = quiz_meta.activity_id AND quiz_meta.activity_meta_key = 'quiz'
            WHERE ua.user_id = %d
                AND ua.activity_type = 'quiz'
                AND quiz_meta.activity_meta_value+0 = %d
                AND ua.activity_id > %d
            ORDER BY ua.activity_id DESC
            LIMIT 1
        ",
            $current_user_id,
            $quiz_id,
            $baseline_activity
        )
    );

    $retry_seconds = (int) apply_filters( 'politeia_quiz_poll_retry_seconds', 5 );

    if ( ! $activity ) {
        wp_send_json_success(
            array(
                'status'              => 'pending',
                'retry_after'         => max( 1, $retry_seconds ),
                'latest_activity_id'  => (int) $baseline_activity,
                'message'             => __( 'No se encontraron intentos nuevos todavía.', 'politeia-quiz-control' ),
            )
        );
    }

    $meta_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT activity_meta_key, activity_meta_value
            FROM {$wpdb->prefix}learndash_user_activity_meta
            WHERE activity_id = %d",
            $activity->activity_id
        ),
        OBJECT_K
    );

    if ( empty( $meta_rows['total_points'] ) || empty( $meta_rows['percentage'] ) ) {
        wp_send_json_success(
            array(
                'status'              => 'pending',
                'retry_after'         => max( 1, $retry_seconds ),
                'latest_activity_id'  => (int) $activity->activity_id,
                'message'             => __( 'El intento sigue procesándose, vuelve a consultar en unos segundos.', 'politeia-quiz-control' ),
            )
        );
    }

    $percentage_raw = isset( $meta_rows['percentage']->activity_meta_value ) ? (float) $meta_rows['percentage']->activity_meta_value : 0;
    $average_percentage = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT AVG( CAST( percentage_meta.activity_meta_value AS DECIMAL(10,2) ) )
            FROM {$wpdb->prefix}learndash_user_activity AS ua
            INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS quiz_meta
                ON ua.activity_id = quiz_meta.activity_id AND quiz_meta.activity_meta_key = 'quiz'
            INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS percentage_meta
                ON ua.activity_id = percentage_meta.activity_id AND percentage_meta.activity_meta_key = 'percentage'
            WHERE ua.activity_type = 'quiz'
                AND ua.activity_completed IS NOT NULL
                AND quiz_meta.activity_meta_value+0 = %d
        ",
            $quiz_id
        )
    );

    $latest_activity_details = array(
        'activity_id' => (int) $activity->activity_id,
        'started'     => $activity->activity_started ? gmdate( 'Y-m-d H:i:s', (int) $activity->activity_started ) : '',
        'completed'   => $activity->activity_completed ? gmdate( 'Y-m-d H:i:s', (int) $activity->activity_completed ) : '',
        'duration'    => $activity->activity_completed ? max( 0, (int) $activity->activity_completed - (int) $activity->activity_started ) : 0,
        'score_data'  => array(
            'score'        => isset( $meta_rows['score']->activity_meta_value ) ? (int) $meta_rows['score']->activity_meta_value : 0,
            'total_points' => (int) $meta_rows['total_points']->activity_meta_value,
            'percentage'   => (int) round( $percentage_raw ),
            'passed'       => ! empty( $meta_rows['pass']->activity_meta_value ) && (int) $meta_rows['pass']->activity_meta_value === 1,
        ),
    );

    $payload = array(
        'status'              => 'ready',
        'latest_activity_id'  => (int) $activity->activity_id,
        'latest_activity_details' => $latest_activity_details,
        'average_percentage'  => null !== $average_percentage ? (int) round( (float) $average_percentage ) : null,
        'retry_after'         => max( 1, $retry_seconds ),
        'course_id'           => $related_course_id,
    );

    set_transient( $cache_key, $payload, 10 );

    wp_send_json_success( $payload );
}

// Este hook registra la función AJAX con WordPress para usuarios autenticados.
// Se ejecuta automáticamente cuando WordPress procesa una petición a admin-ajax.php
// con la acción 'get_latest_quiz_activity'.
add_action( 'wp_ajax_get_latest_quiz_activity', 'politeia_get_latest_quiz_activity' );