<?php
/**
 * Template Name: Quiz Debug Template
 */

get_header();

the_post();
remove_all_actions('learndash-quiz-content'); // bloquear inyección automática del contenido del quiz

// =================================================================
// FUNCIÓN CORREGIDA PARA OBTENER DATOS DEL ÚLTIMO INTENTO
// =================================================================
function politeia_get_last_attempt_data( $user_id, $quiz_id ) {
    global $wpdb;

    if ( empty( $quiz_id ) ) {
        return [ 'percentage' => 'None', 'date' => '—' ];
    }

    $activity = $wpdb->get_row( $wpdb->prepare( "
        SELECT ua.activity_id, ua.activity_completed
        FROM {$wpdb->prefix}learndash_user_activity AS ua
        INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
          ON ua.activity_id = uam.activity_id
        WHERE ua.user_id                 = %d
          AND uam.activity_meta_key      = 'quiz'
          AND uam.activity_meta_value+0  = %d
          AND ua.activity_type           = 'quiz'
          AND ua.activity_completed IS NOT NULL
        ORDER BY ua.activity_id DESC
        LIMIT 1
    ", $user_id, $quiz_id ) );

    if ( ! $activity ) {
        return [ 'percentage' => 'None', 'date' => '—' ];
    }

    $percentage = $wpdb->get_var( $wpdb->prepare( "
        SELECT activity_meta_value+0
        FROM {$wpdb->prefix}learndash_user_activity_meta
        WHERE activity_id        = %d
          AND activity_meta_key  = 'percentage'
        LIMIT 1
    ", $activity->activity_id ) );

    $percentage = intval( $percentage );

    return [
        'percentage' => $percentage . '%',
        'date'       => date_i18n( get_option( 'date_format' ) . ' H:i', $activity->activity_completed ),
    ];
}

// =================================================================
// LÓGICA DE DATOS
// =================================================================
global $wpdb;

$current_user_id = get_current_user_id();
$quiz_id         = get_the_ID();
$quiz_title      = get_the_title( $quiz_id );

$course_id_first = $wpdb->get_var( $wpdb->prepare( "
    SELECT post_id
    FROM {$wpdb->postmeta}
    WHERE meta_key = '_first_quiz_id'
      AND meta_value = %d
    LIMIT 1
", $quiz_id ) );

$course_id_final = $wpdb->get_var( $wpdb->prepare( "
    SELECT post_id
    FROM {$wpdb->postmeta}
    WHERE meta_key = '_final_quiz_id'
      AND meta_value = %d
    LIMIT 1
", $quiz_id ) );

$course_id = $course_id_first ?: $course_id_final;

$first_quiz_id = get_post_meta( $course_id, '_first_quiz_id', true );
$final_quiz_id = get_post_meta( $course_id, '_final_quiz_id', true );

$is_first_quiz = ( (int) $quiz_id === (int) $first_quiz_id );
$is_final_quiz = ( (int) $quiz_id === (int) $final_quiz_id );

$course_title = $course_id ? get_the_title( $course_id ) : 'N/A';

$first_data = $first_quiz_id
    ? politeia_get_last_attempt_data( $current_user_id, $first_quiz_id )
    : [ 'percentage' => 'None', 'date' => '—' ];

$final_data = $final_quiz_id
    ? politeia_get_last_attempt_data( $current_user_id, $final_quiz_id )
    : [ 'percentage' => 'None', 'date' => '—' ];

$progress_data = learndash_course_progress(array(
    'user_id' => $current_user_id,
    'course_id' => $course_id,
    'array' => true
));
$progress = isset($progress_data['percentage']) ? $progress_data['percentage'] : 0;
?>

<!--DEBUG TABLE-->
<div class="quiz-wrap">
  <div style="padding:40px; max-width:600px; margin:auto;">
    <table style="border-collapse: collapse; width: 100%; font-family: monospace;">
      <tr><td>CURRENT QUIZ:</td><td><?= esc_html( $quiz_id ); ?></td></tr>
      <tr><td>QUIZ NAME:</td><td><?= esc_html( $quiz_title ); ?></td></tr>
      <tr><td>IS FIRST?</td><td><?= $is_first_quiz ? 'true' : 'false'; ?></td></tr>
      <tr><td>IS FINAL?</td><td><?= $is_final_quiz ? 'true' : 'false'; ?></td></tr>
      <tr><td>COURSE IT BELONGS TO:</td><td><?= esc_html( $course_title ); ?></td></tr>
      <tr><td>USER ID:</td><td><?= esc_html( $current_user_id ); ?></td></tr>
      <tr><td>FIRST QUIZ LAST ATTEMPT %:</td><td><?= esc_html( $first_data['percentage'] ); ?></td></tr>
      <tr><td>FIRST QUIZ DATE:</td><td><?= esc_html( $first_data['date'] ); ?></td></tr>
      <tr><td>FINAL QUIZ LAST ATTEMPT %:</td><td><?= esc_html( $final_data['percentage'] ); ?></td></tr>
      <tr><td>FINAL QUIZ DATE:</td><td><?= esc_html( $final_data['date'] ); ?></td></tr>
      <tr><td><strong>LESSONS COMPLETED:</strong></td><td><?= $progress . '%'; ?></td></tr>
    </table>
  </div>

  <div class="page-content">
    <?php the_content(); ?>

    <?php
    // Mostrar el quiz si corresponde
    if ( $is_first_quiz ) {
        echo do_shortcode('[quiz id="' . $quiz_id . '"]');
    } elseif ( $is_final_quiz ) {
        if (
            learndash_is_user_enrolled($current_user_id, $course_id)
            && intval($progress) === 100
        ) {
            echo do_shortcode('[quiz id="' . $quiz_id . '"]');
        } else {
            echo '<div style="text-align:center; padding: 2em; border: 2px dashed #ccc; background: #fefefe;">';
            echo '<h2 style="color:#b71c1c;">Debes completar todas las lecciones del curso antes de rendir la Prueba Final.</h2>';
            echo '<a href="' . esc_url( get_permalink( $course_id ) ) . '" style="display:inline-block; margin-top:1em; padding:0.75em 1.5em; background:#ff9800; color:#fff; border-radius:5px; text-decoration:none;">Reanudar Curso</a>';
            echo '</div>';
        }
    }
    ?>
  </div>
</div>
<!-- DEBUG END-->

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
get_footer();
