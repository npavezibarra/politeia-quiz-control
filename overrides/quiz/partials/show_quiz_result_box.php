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
	<h4 class="wpProQuiz_header"><?php esc_html_e( 'Results', 'learndash' ); ?></h4>

	<!-- Pie Chart -->
	<div style="max-width: 200px; margin: 20px auto;">
		<canvas id="circle-chart" width="200" height="200"></canvas>
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

	<p class="wpProQuiz_points wpProQuiz_points--message">
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

	<!-- Chart.js & Dynamic Percentage Script -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
	const targetNode = document.querySelector(".wpProQuiz_points.wpProQuiz_points--message");
	const canvas = document.getElementById('circle-chart');
	if (!targetNode || !canvas) return;

	const ctx = canvas.getContext('2d');

	const renderChart = (percentage) => {
		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Correct', 'Incorrect'],
				datasets: [{
					data: [percentage, 100 - percentage],
					backgroundColor: ['#2196F3', '#E0E0E0'],
					borderWidth: 0
				}]
			},
			options: {
				cutout: '70%',
				plugins: {
					legend: { display: false },
					tooltip: { enabled: false },
					title: {
						display: true,
						text: 'Results',
						font: {
							size: 18,
							weight: 'bold'
						},
						color: '#333'
					}
				}
			}
		});
	};

	const observer = new MutationObserver(() => {
		const spans = targetNode.querySelectorAll('span');
		if (spans.length < 3) return;

		const percentageText = spans[2].innerText.replace('%', '');
		const percentage = parseFloat(percentageText);

		if (!isNaN(percentage)) {
			observer.disconnect(); // Detenemos el observer
			renderChart(percentage);
		}
	});

	observer.observe(targetNode, { childList: true, subtree: true });
});
</script>

</div>
