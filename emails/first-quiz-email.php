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

	$debug_data = pqc_get_quiz_debug_data( $quiz_data, $user );
	$subject    = '✔️ New Quiz Completed: ' . $debug_data['quiz_title'];

	$svg_url = content_url( 'uploads/2025/06/LogoNewBlackPoliteia.svg' );

	$raw_score  = isset( $debug_data['first_quiz_attempt'] ) ? $debug_data['first_quiz_attempt'] : '0';
	$user_score = (int) preg_replace( '/[^0-9]/', '', $raw_score );
	$user_score = min( 100, max( 0, $user_score ) );

	$avg_score       = 75;
	$chart_url_user  = politeia_generate_quickchart_url( $user_score );
	$chart_url_avg   = politeia_generate_quickchart_url( $avg_score );
	$quiz_id         = isset( $quiz_data['quiz'] ) ? intval( $quiz_data['quiz'] ) : 0;

	$course_id = $wpdb->get_var( $wpdb->prepare( "
		SELECT post_id
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_first_quiz_id'
		  AND meta_value = %d
		LIMIT 1
	", $quiz_id ) );

	$course_title = get_the_title( $course_id );
	$course_url   = $course_id ? get_permalink( $course_id ) : home_url();
	$login_redirect = wp_login_url( $course_url );

	$completion_date = date_i18n(
		get_option( 'date_format' ),
		current_time( 'timestamp' )
	);

	$debug_data['completion_date'] = $completion_date;

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

	$checkout_url    = $product_id ? wc_get_checkout_url() : '#';
	$add_to_cart_url = $product_id ? add_query_arg( 'add-to-cart', $product_id, $checkout_url ) : '#';

	// Detectar si el curso es FREE usando LearnDash API
	$access_type = function_exists( 'learndash_get_setting' ) ? learndash_get_setting( $course_id, 'course_price_type' ) : '';
	$is_free_course = ( $access_type === 'free' );

	// Inicia cuerpo del correo
	$body  = '<div style="background-color: #f8f8f8; padding: 30px 0;">';

	$body .= '<style>
	@media only screen and (max-width: 600px) {
		.hide-on-mobile { display: none !important; }
		#your-score-pie, #polis-score-pie { margin-bottom: 40px !important; }
	}
	</style>';

	$body .= '<div id="content-top-email" style="background: white; padding: 30px 0px; text-align: center; max-width: 700px; margin: auto; border-radius: 6px; border: 1px solid #d5d5d5;">';
	$body .= '<img src="' . esc_url( $svg_url ) . '" alt="Quiz Header" style="max-width: 200px; height: auto; margin-bottom: 20px;">';
	$body .= '<hr style="margin: 0px auto;">';

	$body .= '<div style="text-align: center; font-family: sans-serif; padding: 20px 50px 0px 50px;">';
	$body .= '<p style="font-size:12px;">Completion date: <strong>' . esc_html( $completion_date ) . '</strong>.</p>';
	$body .= '<h2 style="color: #333333; font-size: 22px; margin-bottom: 10px; padding: 0px 30px;">🎉 Congratulations ' . esc_html( $debug_data['user_display_name'] ) . '!</h2>';
	$body .= '<p>You have completed the quiz <strong>' . esc_html( $debug_data['quiz_title'] ) . '</strong>,<br> part of the course <strong>' . esc_html( $debug_data['course_title'] ) . '</strong>.</p>';
	$body .= '<p>Here are the statistics:</p>';
	$body .= '</div>';
	$body .= '<hr style="margin: 20px auto 0px auto;">';

	$body .= '<div id="container-pie-charts" style="display: flex; justify-content: space-evenly; flex-wrap: wrap; margin: 0px auto; align-items: center; text-align: center; font-family: sans-serif;">';

	$body .= '<div id="your-score-pie">';
	$body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Your Score</h3>';
	$body .= '<img src="' . esc_url( $chart_url_user ) . '" alt="Your Score" style="max-width: 300px; height: auto;">';
	$body .= '</div>';

	$body .= '<div class="hide-on-mobile" style="width: 1px; height: 300px; background-color: #cccccc;"></div>';

	$body .= '<div id="polis-score-pie">';
	$body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Polis Average</h3>';
	$body .= '<img src="' . esc_url( $chart_url_avg ) . '" alt="Polis Average" style="max-width: 300px; height: auto;">';
	$body .= '</div>';

	$body .= '</div>';
	$body .= '<hr style="margin: 0px auto 20px auto;">';

	// Verificar si el usuario compró el curso (solo si es tipo closed)
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

	// Mensaje final según tipo de curso
	if ( $is_free_course || $course_bought ) {
		$body .= '<div id="content-bottom-message" style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
		if ( $is_free_course ) {
			$body .= '<h3>This is a free course — get started now!</h3>';
		}
		$body .= '<p><a href="' . esc_url( $login_redirect ) . '" style="display: inline-block; background-color: #000000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;" target="_blank">Go to Course</a></p>';
		if ( ! $is_free_course ) {
			$body .= '<p style="color: #555;">Remember: after completing all the lessons, you will be able to take the final quiz to see how your results evolve over time.</p>';
		}
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
