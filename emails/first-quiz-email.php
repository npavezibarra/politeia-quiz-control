<?php
/**
 * Template for the first quiz notification email.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function politeia_generate_quickchart_url( $value ) {
	$config = [
		'type' => 'doughnut',
		'data' => [
			'datasets' => [[
				'data' => [ $value, 100 - $value ],
				'backgroundColor' => ['#f9c600', '#eeeeee'],
				'borderWidth' => 0
			]]
		],
		'options' => [
			'cutout' => '10%',
			'plugins' => [
				'legend' => [ 'display' => false ],
				'tooltip' => [ 'enabled' => false ],
				'datalabels' => [ 'display' => false ],
				'doughnutlabel' => [
					'labels' => [[
						'text' => $value . '%',
						'font' => [
							'size' => 24,
							'weight' => 'bold'
						],
						'color' => '#333'
					]]
				]
			]
		],
		'plugins' => ['doughnutlabel']
	];

	return 'https://quickchart.io/chart?c=' . urlencode( json_encode( $config ) );
}

function pqc_get_first_quiz_email_content( $quiz_data, $user ) {
	global $wpdb;

	$debug_data       = pqc_get_quiz_debug_data( $quiz_data, $user );
	$debug_table_html = pqc_build_html_debug_table( $debug_data );
	$subject          = '✔️ Nuevo Quiz Completado: ' . $debug_data['quiz_title'];

	$svg_url = content_url( 'uploads/2025/06/LogoNewBlackPoliteia.svg' );

	$raw_score  = isset( $debug_data['first_quiz_attempt'] ) ? $debug_data['first_quiz_attempt'] : '0';
	$user_score = (int) preg_replace( '/[^0-9]/', '', $raw_score );
	$user_score = min( 100, max( 0, $user_score ) );

	$avg_score       = 75;
	$chart_url_user  = politeia_generate_quickchart_url( $user_score );
	$chart_url_avg   = politeia_generate_quickchart_url( $avg_score );
	$quiz_id         = isset( $quiz_data['quiz'] ) ? intval( $quiz_data['quiz'] ) : 0;

	// Obtener ID del curso relacionado con el quiz
	$course_id = $wpdb->get_var( $wpdb->prepare( "
		SELECT post_id
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_first_quiz_id'
		  AND meta_value = %d
		LIMIT 1
	", $quiz_id ) );

	$course_url     = $course_id ? get_permalink( $course_id ) : home_url();
	$login_redirect = wp_login_url( $course_url );

	// Obtener ID del producto asociado al curso
    $product_id = 0;
    if ( $course_id ) {
        if ( ! class_exists( 'PoliteiaCourse' ) && file_exists( plugin_dir_path( __FILE__ ) . '../classes/class-politeia-course.php' ) ) {
            include_once plugin_dir_path( __FILE__ ) . '../classes/class-politeia-course.php';
        }
        if ( class_exists( 'PoliteiaCourse' ) ) {
            $course_object = new PoliteiaCourse( $course_id );
            $product_id    = $course_object->getRelatedProductId();
        }
    }

    // --- INICIO DE CÓDIGO DE DEPURACIÓN Y SOLUCIÓN ---

    // Forzar la carga de la sesión de WooCommerce si no está disponible
    if ( class_exists('WooCommerce') && ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie(true);
    }
    
    // Obtener las URLs
    $checkout_url    = $product_id ? wc_get_checkout_url() : '#';
    $add_to_cart_url = $product_id ? add_query_arg( 'add-to-cart', $product_id, $checkout_url ) : '#';

    // Registrar los valores en el log de errores de PHP para depuración
    error_log('--- DEBUG CORREO POLITEIA ---');
    error_log('Product ID: ' . $product_id);
    error_log('Checkout URL Obtenida: ' . $checkout_url);
    error_log('URL Final "Add to Cart": ' . $add_to_cart_url);
    error_log('-----------------------------');

    // --- FIN DE CÓDIGO DE DEPURACIÓN Y SOLUCIÓN ---

	$body  = '<div style="background-color: #f8f8f8; padding: 30px 0;">';
	$body .= '<style>@media only screen and (max-width: 600px) { .hide-on-mobile { display: none !important; } }</style>';
	$body .= '<div id="content-top-email" style="background: white; padding: 30px 0px; text-align: center; max-width: 700px; margin: auto; border-radius: 6px; border: 1px solid #d5d5d5;">';
	$body .= '<img src="' . esc_url( $svg_url ) . '" alt="Encabezado Quiz" style="max-width: 200px; height: auto; margin-bottom: 20px;">';
	$body .= '<hr style="margin: 0px auto;">';

	$body .= '<div style="text-align: center; font-family: sans-serif;">';
	$body .= '<h2 style="color: #333333; font-size: 22px; margin-bottom: 10px; padding: 0px 30px;">🎉 Congratulations ' . esc_html( $debug_data['user_display_name'] ) . '!</h2>';
	$body .= '<p>You have completed the quiz <strong>' . esc_html( $debug_data['quiz_title'] ) . '</strong>.</p>';
	$body .= '<p>Here is your score:</p>';
	$body .= '</div>';
	$body .= '<hr style="margin: 20px auto 0px auto;">';

	$body .= '<div id="container-pie-charts" style="display: flex; justify-content: space-evenly; flex-wrap: wrap; margin: 0px auto; align-items: center; text-align: center; font-family: sans-serif;">';

	$body .= '<div>';
	$body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Your Score</h3>';
	$body .= '<img src="' . esc_url( $chart_url_user ) . '" alt="Your Score" style="max-width: 300px; height: auto;">';
	$body .= '</div>';

	$body .= '<div class="hide-on-mobile" style="width: 1px; height: 300px; background-color: #cccccc;"></div>';

	$body .= '<div>';
	$body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Polis Average</h3>';
	$body .= '<img src="' . esc_url( $chart_url_avg ) . '" alt="Polis Average" style="max-width: 300px; height: auto;">';
	$body .= '</div>';

	$body .= '</div>';
	$body .= '<hr style="margin: 0px auto 20px auto;">';

	// Comprobar si el usuario ya compró el curso
	$course_bought = false;

	if ( $product_id && is_array( $user ) && isset( $user['ID'] ) ) {
		$user = get_user_by( 'ID', $user['ID'] );
	}

	if ( $product_id && $user instanceof WP_User ) {
		$customer_orders = wc_get_orders( [
			'customer_id' => $user->ID,
			'limit'       => -1,
			'status'      => [ 'processing', 'completed', 'course-on-hold' ],
		] );

		foreach ( $customer_orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item->get_product_id() == $product_id ) {
					$course_bought = true;
					break 2;
				}
			}
		}
	}

	if ( $course_bought ) {
		$body .= '<div id="content-bottom-message" style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
		$body .= '<h3>You can now proceed to complete the lessons of the course.</h3>';
		$body .= '<p><a href="' . esc_url( $login_redirect ) . '" style="display: inline-block; background-color: #000000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;" target="_blank">Go to Course</a></p>';
		$body .= '<p style="color: #555;">Remember: after completing all the lessons, you will be able to take the final quiz to see how your results evolve over time.</p>';
		$body .= '</div>';
	} else {
		$body .= '<div style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
		$body .= '<p>To access the course content and take the quiz, please purchase the course first.</p>';
		$body .= '<p><a href="' . esc_url( $add_to_cart_url ) . '" style="display: inline-block; background-color: #000000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Buy Course</a></p>';
		$body .= '</div>';
	}

	$body .= '<div style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
	$body .= '<p>We hope you learn and have fun on this educational journey!</p>';
	$body .= '</div>';
	$body .= '</div></div>';

	return [
		'subject' => $subject,
		'body'    => $body,
	];
}
