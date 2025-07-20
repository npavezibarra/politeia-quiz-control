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
<div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; margin-bottom: 30px;">
	<div style="max-width: 300px;">
		<div id="radial-chart"></div>
	</div>
	<div style="max-width: 300px;">
		<div id="radial-chart-promedio"></div>
	</div>
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

<?php
global $wpdb;
$current_user_id = get_current_user_id();

// ID del quiz actual
$quiz_id = get_the_ID();

// Buscar si este quiz está asignado como First Quiz en algún curso
$course_id = $wpdb->get_var( $wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_first_quiz_id' AND meta_value = %d",
    $quiz_id
) );

// ¿Es First Quiz?
$is_first_quiz = !empty( $course_id );

// Buscar si este quiz está asignado como Final Quiz en algún curso
$final_quiz_course_id = $wpdb->get_var( $wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_final_quiz_id' AND meta_value = %d",
    $quiz_id
) );

// ¿Es Final Quiz?
$is_final_quiz = !empty( $final_quiz_course_id );

// Si es Final Quiz, usar ese course_id
if ( $is_final_quiz ) {
    $course_id = $final_quiz_course_id;
}

// Buscar producto relacionado usando meta_value serializado
$related_product_id = null;

if ( $course_id ) {
    $like = '%i:0;i:' . (int) $course_id . ';%';
    $related_product_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_related_course' 
         AND meta_value LIKE %s",
        $like
    ) );
}

// Verificar si el usuario compró ese producto
$has_bought   = false;
$order_number = null;
$order_status = null;

if ( $current_user_id && $related_product_id ) {
    $orders = wc_get_orders( array(
        'customer_id' => $current_user_id,
        'status'      => array( 'completed', 'processing', 'on-hold', 'course-on-hold' ),
        'limit'       => -1,
        'return'      => 'ids'
    ) );

    foreach ( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_product_id() == $related_product_id ) {
                $has_bought   = true;
                $order_number = $order->get_order_number();
                $order_status = $order->get_status();
                break 2;
            }
        }
    }
}

// Obtener el puntaje del First Quiz si estamos en Final Quiz
$first_quiz_score = 0;
if ( $is_final_quiz && $course_id ) {
    $first_quiz_id = get_post_meta( $course_id, '_first_quiz_id', true );

    if ( $first_quiz_id && $current_user_id ) {
        $latest_id = Politeia_Quiz_Stats::get_latest_attempt_id( $current_user_id, $first_quiz_id );
        if ( $latest_id ) {
            $data = Politeia_Quiz_Stats::get_score_and_pct_by_activity( $latest_id );
            if ( $data && isset( $data->percentage ) ) {
                $first_quiz_score = round( floatval( $data->percentage ) );
            }
        }
    }
}

?>

<table style="width:100%; border-collapse: collapse; font-size: 14px; display:none;">
    <tbody>
        <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ccc;">Quiz ID</th>
            <td style="padding: 8px; border-bottom: 1px solid #ccc;"><?php echo esc_html( $quiz_id ); ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ccc;">¿Es First Quiz?</th>
            <td style="padding: 8px; border-bottom: 1px solid #ccc;"><?php echo $is_first_quiz ? 'TRUE' : 'FALSE'; ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ccc;">¿Es Final Quiz?</th>
            <td style="padding: 8px; border-bottom: 1px solid #ccc;"><?php echo $is_final_quiz ? 'TRUE' : 'FALSE'; ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ccc;">Course ID</th>
            <td style="padding: 8px; border-bottom: 1px solid #ccc;"><?php echo $course_id ? esc_html( $course_id ) : '—'; ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ccc;">Related Product ID</th>
            <td style="padding: 8px; border-bottom: 1px solid #ccc;"><?php echo $related_product_id ? esc_html( $related_product_id ) : '—'; ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px;">Bought?</th>
            <td style="padding: 8px;"><?php echo $has_bought ? 'TRUE' : 'FALSE'; ?></td>
        </tr>
        <?php if ( $is_final_quiz ) : ?>
        <tr>
            <th style="text-align: left; padding: 8px;">First Quiz Score</th>
            <td style="padding: 8px;"><?php echo esc_html( $first_quiz_score ); ?>%</td>
        </tr>
        <?php endif; ?>
        <?php if ( $has_bought && $order_number ) : ?>
        <tr>
            <th style="text-align: left; padding: 8px;">Order Number</th>
            <td style="padding: 8px;"><?php echo esc_html( $order_number ); ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 8px;">Order Status</th>
            <td style="padding: 8px;"><?php echo esc_html( ucfirst( $order_status ) ); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
echo '<div style="margin-top:20px;">';

if ( $course_id ) {
    if ( $related_product_id && ! $has_bought ) {
        // Hay producto, pero no lo compró → mostramos "Buy Course"
        $product_link = get_permalink( $related_product_id );
        echo '<a href="' . esc_url( $product_link ) . '" class="button"'
           . ' style="background:black;color:white;padding:10px 20px;text-decoration:none;">'
           . esc_html__( 'Buy Course', 'text-domain' )
           . '</a>';
    } else {
        // O bien no hay producto (curso free), o ya lo compró → "Go to Course"
        $course_link = get_permalink( $course_id );
        echo '<a href="' . esc_url( $course_link ) . '" class="button"'
           . ' style="background:black;color:white;padding:10px 20px;text-decoration:none;">'
           . esc_html__( 'Go to Course', 'text-domain' )
           . '</a>';
    }
}

echo '</div>';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
	const target = document.querySelector(".wpProQuiz_points.wpProQuiz_points--message");
	if (!target) return;

	const span = target.querySelectorAll("span")[2];
	if (!span) return;

	const chartContainer = document.querySelector("#radial-chart");
	const chartContainerPromedio = document.querySelector("#radial-chart-promedio");

	// Determinar si es Final Quiz desde PHP
	const isFinalQuiz = <?php echo $is_final_quiz ? 'true' : 'false'; ?>;
	const firstQuizScore = <?php echo $first_quiz_score; ?>;

	const observer = new MutationObserver(function () {
		const percentageText = span.innerText.replace('%', '').trim();
		const percentage = parseFloat(percentageText);

		if (isNaN(percentage)) return;

		observer.disconnect();

		const options = (value, labelText, colorStart, colorEnd) => ({
			series: [value],
			chart: {
				height: 400,
				type: 'radialBar'
			},
			plotOptions: {
				radialBar: {
					hollow: { size: '60%' },
					dataLabels: {
						name: {
							show: true,
							offsetY: -10,
							color: '#666',
							fontSize: '16px',
							text: labelText
						},
						value: {
							show: true,
							fontSize: '32px',
							fontWeight: 600,
							color: '#111',
							offsetY: 8,
							formatter: val => val + '%'
						}
					}
				}
			},
			labels: [labelText],
			colors: [colorStart],
			fill: {
				type: 'gradient',
				gradient: {
					shade: 'light',
					type: 'diagonal',
					gradientToColors: [colorEnd],
					stops: [0, 100],
					opacityFrom: 1,
					opacityTo: 1,
					angle: 145
				}
			}
		});

		// Configurar gráficos según el tipo de quiz
		if (isFinalQuiz) {
			// Para Final Quiz: mostrar Final Score y First Score
			if (chartContainer) {
				const chart = new ApexCharts(chartContainer, options(percentage, 'Final Score', '#d29d01', '#ffd000'));
				chart.render();
			}

			if (chartContainerPromedio) {
				const chartFirst = new ApexCharts(chartContainerPromedio, options(firstQuizScore, 'First Score', '#d29d01', '#ffd000'));
				chartFirst.render();
			}
		} else {
			// Para First Quiz: mostrar Tu Puntaje y Promedio Polis
			if (chartContainer) {
				const chart = new ApexCharts(chartContainer, options(percentage, 'Tu Puntaje', '#d29d01', '#ffd000'));
				chart.render();
			}

			if (chartContainerPromedio) {
				const chartProm = new ApexCharts(chartContainerPromedio, options(75, 'Promedio Polis', '#d29d01', '#ffd000'));
				chartProm.render();
			}
		}
	});

	observer.observe(span, { childList: true, characterData: true, subtree: true });
});
</script>

</div>