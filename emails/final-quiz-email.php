<?php
/**
 * Template para el correo de notificación del quiz final.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('politeia_generate_quickchart_url')) {
	function politeia_generate_quickchart_url($value, $label = '')
	{
		$config = [
			'type' => 'doughnut',
			'data' => [
				'datasets' => [
					[
						'data' => [$value, 100 - $value],
						'backgroundColor' => ['#ffd000', '#eeeeee'],
						'borderWidth' => 0
					]
				]
			],
			'options' => [
				'cutoutPercentage' => 75,
				'legend' => ['display' => false],
				'plugins' => [
					'datalabels' => ['display' => false],
					'doughnutlabel' => [
						'labels' => [
							[
								'text' => $label,
								'font' => [
									'size' => 22,
									'family' => 'sans-serif',
									'weight' => 'bold'
								],
								'color' => '#666666'
							],
							[
								'text' => $value . '%',
								'font' => [
									'size' => 40,
									'family' => 'sans-serif',
									'weight' => 'bold'
								],
								'color' => '#000000'
							]
						]
					]
				]
			]
		];

		return 'https://quickchart.io/chart?c=' . urlencode(json_encode($config));
	}
}

function pqc_get_final_quiz_email_content($quiz_data, $user)
{
	$debug_data = pqc_get_quiz_debug_data($quiz_data, $user);

	$subject = '✔️ Final Quiz Completed: ' . $debug_data['quiz_title'];

	$logo_url = content_url('uploads/2025/06/LogoNewBlackPoliteia.svg');

	$first_score = (int) preg_replace('/[^0-9]/', '', $debug_data['first_quiz_attempt'] ?? '0');
	$final_score = (int) preg_replace('/[^0-9]/', '', $debug_data['final_quiz_attempt'] ?? '0');

	$first_score = min(100, max(0, $first_score));
	$final_score = min(100, max(0, $final_score));

	$progreso = round($final_score - $first_score, 2);

	// Mensaje dinámico para knowledge-increase
	if ($progreso > 0) {
		$mensaje_knowledge = '
			<h2 style="margin: 0; text-align: center; color: #000;">You improved your score by <strong style="color: #4CAF50;">+' . $progreso . ' points</strong>. Great job!</h2>
		';
	} elseif ($progreso === 0) {
		$mensaje_knowledge = '
			<h2 style="margin: 0; text-align: center; color: #000;">Your knowledge has been reinforced. Your progress was <strong>0 points</strong>.</h2>
		';
	} else {
		$mensaje_knowledge = '
			<h2 style="margin: 0; text-align: center; color: #000;">Your score changed by <strong style="color: #D32F2F;">' . $progreso . ' points</strong>.</h2>
			<p style="margin-top: 5px; font-weight: normal; color: #333; text-align: center;">
				This is uncommon, but nothing to worry about. You can review the lessons and take the Final Quiz again in 10 days.
			</p>
		';
	}

	$chart_url_final = politeia_generate_quickchart_url($final_score, 'Final Quiz Result');
	$chart_url_first = politeia_generate_quickchart_url($first_score, 'First Quiz Result');

	$courses_url = home_url('/courses/');

	$body = '<div style="background-color: #f8f8f8; padding: 30px 0;">'; // Wrapper externo

	// Tabla Principal (Container)
	$body .= '<table align="center" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; margin: 0 auto; max-width: 600px; width: 100%; border: 1px solid #d5d5d5; border-radius: 6px; font-family: sans-serif;">';

	// 1. Logo
	$body .= '<tr><td align="center" style="padding: 30px 0;">';
	$body .= '<img src="' . esc_url($logo_url) . '" alt="Politeia Logo" width="180" style="display: block; margin: 0 auto; max-width: 180px; height: auto;">';
	$body .= '</td></tr>';

	// Separator
	$body .= '<tr><td style="border-bottom: 1px solid #eeeeee;"></td></tr>';

	// 2. Static Congrats Message
	$body .= '<tr><td align="center" style="padding: 30px 30px 0 30px;">';
	$body .= '<h2 style="margin: 0; color: #333;">🎉 Congratulations!</h2>';
	$body .= '<p style="margin: 10px 0; color: #555; font-size: 18px;">You finished the course <strong>' . esc_html($debug_data['course_title']) . '</strong>.</p>';
	$body .= '</td></tr>';

	// Separator (small)
	$body .= '<tr><td style="padding-top: 20px;"></td></tr>';

	// 3. Dynamic Knowledge Message
	$body .= '<tr><td align="center" style="padding: 0 30px 30px 30px; border-bottom: 1px solid #eeeeee;">';
	$body .= $mensaje_knowledge;
	$body .= '</td></tr>';

	// 4. Charts Section (Nested Table)
	$body .= '<tr><td align="center" style="padding: 30px 10px;">';
	$body .= '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';

	// Chart 1: Final Quiz
	$body .= '<td width="50%" align="center" valign="top" style="padding: 0 5px;">';
	$body .= '<img src="' . esc_url($chart_url_final) . '" alt="Final Score" width="220" style="display: block; max-width: 100%; height: auto;">';
	$body .= '</td>';

	// Chart 2: First Quiz
	$body .= '<td width="50%" align="center" valign="top" style="padding: 0 5px;">';
	$body .= '<img src="' . esc_url($chart_url_first) . '" alt="First Score" width="220" style="display: block; max-width: 100%; height: auto;">';
	$body .= '</td>';

	$body .= '</tr></table>';
	$body .= '</td></tr>';

	// Separator
	$body .= '<tr><td style="border-bottom: 1px solid #eeeeee;"></td></tr>';

	// 5. Footer CTA
	$body .= '<tr><td align="center" style="padding: 30px;">';
	$body .= '<p style="margin-top:0; color: #555;">📚 Continue learning! Check out our full course catalogue:</p>';
	$body .= '<a href="' . esc_url($courses_url) . '" style="display: inline-block; background-color: #000000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">Browse Courses</a>';
	$body .= '</td></tr>';

	// End Main Table
	$body .= '</table>';

	// End Wrapper
	$body .= '</div>';

	return [
		'subject' => $subject,
		'body' => $body,
	];
}
