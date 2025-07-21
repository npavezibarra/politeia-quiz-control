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

	$subject = '✔️ Final Quiz Completed: ' . $debug_data['quiz_title'];

	$logo_url = content_url( 'uploads/2025/06/LogoNewBlackPoliteia.svg' );

	$first_score = (int) preg_replace( '/[^0-9]/', '', $debug_data['first_quiz_attempt'] ?? '0' );
	$final_score = (int) preg_replace( '/[^0-9]/', '', $debug_data['final_quiz_attempt'] ?? '0' );

	$first_score = min( 100, max( 0, $first_score ) );
	$final_score = min( 100, max( 0, $final_score ) );

	$increase = max( 0, $final_score - $first_score );

	$chart_url_final = politeia_generate_quickchart_url( $final_score );
	$chart_url_first = politeia_generate_quickchart_url( $first_score );

	$courses_url = home_url( '/courses/' );

	$body  = '<div id="pqc-email-wrapper" style="background-color: #f8f8f8; padding: 30px 0;">';
	$body .= '<div id="pqc-email-card" style="background: white; max-width: 700px; margin: auto; border-radius: 6px; border: 1px solid #d5d5d5; font-family: sans-serif; color: #333;">';

	$body .= '<div id="pqc-logo-section" style="text-align: center; padding: 20px;">';
	$body .= '<img src="' . esc_url( $logo_url ) . '" alt="Politeia Logo" style="max-width: 200px;">';
	$body .= '</div>';

	$body .= '<div id="pqc-congrats-message" style="text-align: center; padding: 20px 30px; border-top: 1px solid black; border-bottom: 1px solid black;">';
	$body .= '<h2 style="margin: 0;">🎉 Congratulations!</h2>';
	$body .= '<p style="margin: 5px 0;">You finished the course <strong>' . esc_html( $debug_data['course_title'] ) . '</strong>.</p>';
	$body .= '</div>';

	$body .= '<div id="pqc-knowledge-increase" style="text-align: center; padding: 40px 30px; border-bottom: 1px solid black;">';
	$body .= '<h2 style="margin: 0;">You increased your knowledge by <strong>' . $increase . '%</strong> after completing the course.</h2>';
	$body .= '</div>';

	$body .= '<div id="pqc-results-graphs" style="display: flex; justify-content: space-around; align-items: center; padding: 30px 10px; gap: 20px; flex-wrap: wrap; border-bottom: 1px solid black;">';

	$body .= '<div id="pqc-final-quiz" style="text-align: center;">';
	$body .= '<h3 style="margin-bottom: 10px;">Final Quiz Result</h3>';
	$body .= '<img src="' . esc_url( $chart_url_final ) . '" alt="Final Score" style="max-width: 300px;">';
	$body .= '</div>';

	$body .= '<div id="pqc-first-quiz" style="text-align: center;">';
	$body .= '<h3 style="margin-bottom: 10px;">First Quiz Result</h3>';
	$body .= '<img src="' . esc_url( $chart_url_first ) . '" alt="First Score" style="max-width: 300px;">';
	$body .= '</div>';

	$body .= '</div>';

	$body .= '<div id="pqc-footer-cta" style="text-align: center; padding: 20px 30px;">';
	$body .= '<p>📚 Continue learning! Check out our full course catalogue:</p><br>';
	$body .= '<p><a href="' . esc_url( $courses_url ) . '" style="background-color: #000000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Browse Courses</a></p>';
	$body .= '</div>';

	$body .= '</div></div>';

	return [
		'subject' => $subject,
		'body'    => $body,
	];
}

