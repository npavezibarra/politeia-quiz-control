<?php
/**
 * Displays Quiz Result Box.
 *
 * @since 3.2.0
 * @version 4.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="display: none;" class="wpProQuiz_sending">
	<h4 class="wpProQuiz_header"><?php esc_html_e( 'Results', 'learndash' ); ?></h4>
	<p>
		<div>
		<?php
		echo wp_kses_post(
			SFWD_LMS::get_template(
				'learndash_quiz_messages',
				array(
					'quiz_post_id' => $quiz->getID(),
					'context'      => 'quiz_complete_message',
					'message'      => sprintf( esc_html_x( '%s complete. Results are being recorded.', 'placeholder: Quiz', 'learndash' ), LearnDash_Custom_Label::get_label( 'quiz' ) ),
				)
			)
		);
		?>
		</div>
		<div>
			<dd class="course_progress">
				<div class="course_progress_blue sending_progress_bar" style="width: 0%;"></div>
			</dd>
		</div>
	</p>
</div>

<div style="display: none;" class="wpProQuiz_results">

<?php
if ( ! $quiz->isHideResultQuizTime() ) {
    ?>
    <p class="wpProQuiz_quiz_time">
    <?php
        echo wp_kses_post(
            SFWD_LMS::get_template(
                'learndash_quiz_messages',
                array(
                    'quiz_post_id' => $quiz->getID(),
                    'context'      => 'quiz_your_time_message',
                    'message'      => sprintf( esc_html_x( 'Your time: %s', 'placeholder: quiz time.', 'learndash' ), '<span></span>' ),
                )
            )
        );
    ?>
    </p>
    <?php
}
?>

	<!-- Pie Chart -->
	<div style="max-width: 300px; margin: 20px auto;">
        <div id="radial-chart" style="max-width: 300px; margin: 0 auto;"></div>
	</div>

	<?php
	if ( ! $quiz->isHideResultCorrectQuestion() ) {
		echo wp_kses_post(
			SFWD_LMS::get_template(
				'learndash_quiz_messages',
				array(
					'quiz_post_id' => $quiz->getID(),
					'context'      => 'quiz_questions_answered_correctly_message',
					'message'      => '<p>' . sprintf( esc_html_x( '%1$s of %2$s %3$s answered correctly', 'placeholder: correct answer, question count, questions', 'learndash' ), '<span class="wpProQuiz_correct_answer">0</span>', '<span>' . $question_count . '</span>', learndash_get_custom_label( 'questions' ) ) . '</p>',
					'placeholders' => array( '0', $question_count ),
				)
			)
		);
	}

	
	?>

	<p class="wpProQuiz_points wpProQuiz_points--message" style="display:none;">
		<?php
		echo wp_kses_post(
			SFWD_LMS::get_template(
				'learndash_quiz_messages',
				array(
					'quiz_post_id' => $quiz->getID(),
					'context'      => 'quiz_have_reached_points_message',
					'message'      => sprintf( esc_html_x( 'You have reached %1$s of %2$s point(s), (%3$s)', 'placeholder: points earned, points total', 'learndash' ), '<span>0</span>', '<span>0</span>', '<span>0</span>' ),
					'placeholders' => array( '0', '0', '0' ),
				)
			)
		);
		?>
	</p>

	<!-- Button Placeholder -->
	<button type="button" class="buy-course-button">CONDITIONAL BUTTON</button>

	<!-- ApexCharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
document.addEventListener("DOMContentLoaded", function () {
	const target = document.querySelector(".wpProQuiz_points.wpProQuiz_points--message");
	if (!target) return;

	const span = target.querySelectorAll("span")[2];
	if (!span) return;

	const chartContainer = document.querySelector("#radial-chart");

	const observer = new MutationObserver(function () {
		const percentageText = span.innerText.replace('%', '').trim();
		const percentage = parseFloat(percentageText);

		if (isNaN(percentage)) return;

		observer.disconnect(); // Stop observing once we have the value

		const options = {
			series: [percentage],
			chart: {
				height: 400,
				type: 'radialBar'
			},
			plotOptions: {
				radialBar: {
					hollow: {
						size: '60%'
					},
					dataLabels: {
						name: {
							show: true,
							offsetY: -10,
							color: '#666',
							fontSize: '16px',
							text: 'Percent'
						},
						value: {
							show: true,
							fontSize: '32px',
							fontWeight: 600,
							color: '#111',
							offsetY: 8,
							formatter: function (val) {
								return val + '%';
							}
						}
					}
				}
			},
			labels: ['Correctas'],
			colors: ['#00B8D9'],
			fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.5,
                    gradientToColors: ['#c67700'],
                    colorStops: [
                        { offset: 0, color: '#fcff9e', opacity: 1 },
                        { offset: 100, color: '#c67700', opacity: 1 }
                    ],
                    inverseColors: false,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100]
                }
            }
		};

		if (chartContainer) {
			const chart = new ApexCharts(chartContainer, options);
			chart.render();
		}
	});

	observer.observe(span, { childList: true, characterData: true, subtree: true });
});
</script>

</div>
