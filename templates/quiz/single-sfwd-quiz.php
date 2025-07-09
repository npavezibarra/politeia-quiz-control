<?php
/**
 * Template Name: Quiz Debug Template
 */

get_header();
the_post();

// =================================================================
// FUNCIÓN CORREGIDA PARA OBTENER DATOS DEL ÚLTIMO INTENTO
// =================================================================
function politeia_get_last_attempt_data( $user_id, $quiz_id ) {
    global $wpdb;

    if ( empty( $quiz_id ) ) {
        return [ 'percentage' => 'None', 'date' => '—' ];
    }

    // 1) Unimos por el meta 'quiz' correcto para obtener el último intento completado
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

    // 2) Recuperamos el porcentaje para ese activity_id
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

// 1) Recuperar el ID del curso al que pertenece como FIRST
$course_id_first = $wpdb->get_var( $wpdb->prepare( "
    SELECT post_id
    FROM {$wpdb->postmeta}
    WHERE meta_key = '_first_quiz_id'
      AND meta_value = %d
    LIMIT 1
", $quiz_id ) );

// 2) Recuperar el ID del curso al que pertenece como FINAL
$course_id_final = $wpdb->get_var( $wpdb->prepare( "
    SELECT post_id
    FROM {$wpdb->postmeta}
    WHERE meta_key = '_final_quiz_id'
      AND meta_value = %d
    LIMIT 1
", $quiz_id ) );

// 3) Determinar el curso al que pertenece (puede ser por FIRST o FINAL)
$course_id = $course_id_first ?: $course_id_final;

// 4) Obtener IDs de quizzes del curso
$first_quiz_id = get_post_meta( $course_id, '_first_quiz_id', true );
$final_quiz_id = get_post_meta( $course_id, '_final_quiz_id', true );

// 5) Comparar si el quiz actual es FIRST o FINAL
$is_first_quiz = ( (int) $quiz_id === (int) $first_quiz_id );
$is_final_quiz = ( (int) $quiz_id === (int) $final_quiz_id );

// 6) Título del curso
$course_title = $course_id ? get_the_title( $course_id ) : 'N/A';

// 7) Obtener datos de porcentaje y fecha para ambos quizzes
$first_data = $first_quiz_id
    ? politeia_get_last_attempt_data( $current_user_id, $first_quiz_id )
    : [ 'percentage' => 'None', 'date' => '—' ];

$final_data = $final_quiz_id
    ? politeia_get_last_attempt_data( $current_user_id, $final_quiz_id )
    : [ 'percentage' => 'None', 'date' => '—' ];

?>
<div class="quiz-wrap">
  <div style="padding:40px; max-width:600px; margin:auto;">
    <table style="border-collapse: collapse; width: 100%; font-family: monospace;">
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">CURRENT QUIZ:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $quiz_id ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">QUIZ NAME:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $quiz_title ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">IS FIRST?</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= $is_first_quiz ? 'true' : 'false'; ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">IS FINAL?</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= $is_final_quiz ? 'true' : 'false'; ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">COURSE IT BELONGS TO:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $course_title ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">USER ID:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $current_user_id ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">FIRST QUIZ LAST ATTEMPT %:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $first_data['percentage'] ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">FIRST QUIZ DATE:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $first_data['date'] ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">FINAL QUIZ LAST ATTEMPT %:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $final_data['percentage'] ); ?></td>
      </tr>
      <tr>
        <td style="border:1px solid #ccc;padding:8px;">FINAL QUIZ DATE:</td>
        <td style="border:1px solid #ccc;padding:8px;"><?= esc_html( $final_data['date'] ); ?></td>
      </tr>
    </table>
  </div>

  <div class="page-content">
    <?php the_content(); ?>
  </div>
</div>
<?php
get_footer();
