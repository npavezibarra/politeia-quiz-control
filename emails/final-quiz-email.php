<?php
/**
 * Template para el correo de notificación del quiz final.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Genera una URL QuickChart.io para el gráfico circular sin leyenda ni etiquetas.
 *
 * @param int $value Valor del porcentaje.
 * @return string URL codificada.
 */
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

/**
 * Genera el contenido para el correo del quiz final, incluyendo tabla de depuración y gráficos.
 *
 * @param array    $quiz_data Datos del quiz completado.
 * @param WP_User  $user      Objeto del usuario que completó el quiz.
 * @return array Contiene 'subject' y 'body'.
 */
function pqc_get_final_quiz_email_content( $quiz_data, $user ) {

    $debug_data = pqc_get_quiz_debug_data( $quiz_data, $user );
    $debug_table_html = pqc_build_html_debug_table( $debug_data );

    $subject = '✔️ Prueba Final Completada: ' . $debug_data['quiz_title'];

    $svg_url = content_url( 'uploads/2025/06/LogoNewBlackPoliteia.svg' );

    $base_style = 'padding: 20px 40px; font-family: sans-serif; mso-height-rule: exactly; line-height: 25px; color: #7F868F; font-size: 16px;';
    $link_style = 'color: #007CFF;';

    $raw_score = isset( $debug_data['final_quiz_attempt'] ) ? $debug_data['final_quiz_attempt'] : '0';
    $user_score = (int) preg_replace( '/[^0-9]/', '', $raw_score );
    $user_score = min(100, max(0, $user_score));

    $avg_score = 75;

    $chart_url_user = politeia_generate_quickchart_url( $user_score );
    $chart_url_avg  = politeia_generate_quickchart_url( $avg_score );

    $body  = '<div style="background-color: #f8f8f8; padding: 30px 0;">';
    $body .= '<style>@media only screen and (max-width: 600px) { .hide-on-mobile { display: none !important; } }</style>';
    $body .= '<div id="content-top-email" style="background: white; padding: 30px 0px; text-align: center; max-width: 700px; margin: auto; border-radius: 6px; border: 1px solid #d5d5d5;">';
    $body .= '<img src="' . esc_url( $svg_url ) . '" alt="Encabezado Quiz" style="max-width: 200px; height: auto; margin-bottom: 20px;">';
    $body .= '<hr style="margin: 0px auto;">';

    $body .= '<div style="text-align: center; font-family: sans-serif;">';
    $body .= '<h2 style="color: #333333; font-size: 22px; margin-bottom: 10px; padding: 0px 30px;">🏁 Well done, ' . esc_html( $debug_data['user_display_name'] ) . '!</h2>';
    $body .= '<p>You have completed the <strong>Final Quiz</strong> of the course <strong>' . esc_html( $debug_data['course_title'] ) . '</strong>.</p>';
    $body .= '<p>Here is your score:</p>';
    $body .= '</div>';
    $body .= '<hr style="margin: 20px auto 0px auto;">';
    $body .= '<div id="container-pie-charts" style="display: flex; justify-content: space-evenly; flex-wrap: wrap; margin: 0px auto; align-items: center; text-align: center; font-family: sans-serif;">';

    $body .= '<div>';
    $body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Final Score</h3>';
    $body .= '<img src="' . esc_url( $chart_url_user ) . '" alt="Final Score" style="max-width: 300px; height: auto;">';
    $body .= '</div>';

    $body .= '<div class="hide-on-mobile" style="width: 1px; height: 300px; background-color: #cccccc;"></div>';

    $body .= '<div>';
    $body .= '<h3 style="font-size: 16px; color: #333; margin-bottom: 10px;">Polis Average</h3>';
    $body .= '<img src="' . esc_url( $chart_url_avg ) . '" alt="Polis Average" style="max-width: 300px; height: auto;">';
    $body .= '</div>';

    $body .= '</div>';
    $body .= '<hr style="margin: 0px auto 20px auto;">';

    $body .= '<div id="content-bottom-message" style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
    $body .= '<h3>🎓 You have reached the final step of the course.</h3>';
    $body .= '<p>Congratulations on completing your learning journey. You can now download your certificate (if available) or continue exploring more courses.</p>';
    $body .= '<p><a href="' . esc_url( $debug_data['course_url'] ) . '" style="display: inline-block; background-color: #000000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Return to Course</a></p>';
    $body .= '</div>';

    $body .= '<div style="text-align: center; padding: 10px 40px; font-family: sans-serif;">';
    $body .= '<p>We hope you’ve enjoyed learning with us at Politeia!</p>';
    $body .= '</div>';
    $body .= '</div></div>';

    return [
        'subject' => $subject,
        'body'    => $body,
    ];
}
