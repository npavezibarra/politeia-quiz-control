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

// Ensure the Polis_Quiz_Attempts_Shortcode class is loaded and shortcode is rendered
if ( ! class_exists( 'Polis_Quiz_Attempts_Shortcode' ) ) {
    // This assumes your plugin loads this class. If not, include it here.
    // E.g., require_once plugin_dir_path( __FILE__ ) . 'includes/polis-average-quiz-result.php';
}

// Manually render the shortcode to ensure its calculation runs and populates static/global vars
ob_start();
do_shortcode('[polis_quiz_attempts id="' . get_the_ID() . '"]');
ob_end_clean();

// Now, access the values calculated by the shortcode for the initial PHP render
$polis_average = Polis_Quiz_Attempts_Shortcode::$last_average;

// For the debug list, we'll populate it based on the global shortcode logic.
global $wpdb;
$quiz_id_for_shortcode_count = get_the_ID();
$shortcode_attempts_rows = $wpdb->get_results( $wpdb->prepare( "
    SELECT ua.activity_id
    FROM {$wpdb->prefix}learndash_user_activity AS ua
    INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
        ON ua.activity_id = uam.activity_id
    WHERE ua.activity_type = 'quiz'
      AND ua.activity_completed IS NOT NULL
      AND uam.activity_meta_key = 'quiz'
      AND uam.activity_meta_value+0 = %d
", $quiz_id_for_shortcode_count ) );

// --- NEW: Fetch GLOBAL LATEST ACTIVITY ID for display (as PHP sees it on page load) ---
// This must ir antes del foreach para poder excluirlo luego
$php_rendered_latest_global_activity_id = $wpdb->get_var( $wpdb->prepare(
    "
    SELECT ua.activity_id
    FROM {$wpdb->prefix}learndash_user_activity AS ua
    INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
      ON ua.activity_id = uam.activity_id
    WHERE ua.activity_type = 'quiz'
      AND ua.activity_completed IS NOT NULL
      AND uam.activity_meta_key = 'quiz'
      AND uam.activity_meta_value+0 = %d
    ORDER BY ua.activity_id DESC
    LIMIT 1
    ",
    $quiz_id_for_shortcode_count
) );
// --- END NEW PHP BLOCK ---

$polis_attempts_list_for_display = [];
$polis_attempts_for_avg         = [];

foreach ( $shortcode_attempts_rows as $row ) {
    $activity_id = $row->activity_id;

    $pct_val = $wpdb->get_var( $wpdb->prepare( "
        SELECT activity_meta_value
        FROM {$wpdb->prefix}learndash_user_activity_meta
        WHERE activity_id = %d AND activity_meta_key = 'percentage' LIMIT 1
    ", $activity_id ) );

    if ( $pct_val !== null && is_numeric( $pct_val ) ) {
        $percentage = round( floatval( $pct_val ) );

        // Siempre agregamos a la lista visual
        $polis_attempts_list_for_display[] = [
            'id'         => $activity_id,
            'percentage' => $percentage,
        ];

        // Pero solo si NO es el último activity_id (aún en proceso) lo usamos en el promedio
        if ( $php_rendered_latest_global_activity_id !== null
          && $activity_id != $php_rendered_latest_global_activity_id
        ) {
            $polis_attempts_for_avg[] = $percentage;
        }
    }
}

// Calcular promedio excluyendo el intento más reciente
// 1.c) Finalmente calculo el promedio
$polis_average        = count( $polis_attempts_for_avg ) > 0
    ? round( array_sum( $polis_attempts_for_avg ) / count( $polis_attempts_for_avg ) )
    : 0;
$polis_attempts_count = count( $polis_attempts_for_avg );

// END shortcode integration

global $wpdb; // Already globalized above, but good practice if code blocks are separated.
$current_user_id = get_current_user_id();
$quiz_id         = get_the_ID();

// --- Quiz Type and Course Association Logic using metadata helpers ---
$analytics = class_exists( 'Politeia_Quiz_Analytics' )
    ? new Politeia_Quiz_Analytics( (int) $quiz_id )
    : null;

$course_id = $analytics
    ? $analytics->getCourseId()
    : PoliteiaCourse::getCourseFromQuiz( (int) $quiz_id );

$course            = $course_id ? new PoliteiaCourse( $course_id ) : null;
$first_quiz_id     = $analytics && $analytics->getFirstQuizId()
    ? $analytics->getFirstQuizId()
    : ( $course_id ? PoliteiaCourse::getFirstQuizId( $course_id ) : 0 );
$final_quiz_id     = $analytics && $analytics->getFinalQuizId()
    ? $analytics->getFinalQuizId()
    : ( $course_id ? PoliteiaCourse::getFinalQuizId( $course_id ) : 0 );
$is_first_quiz     = $first_quiz_id && (int) $first_quiz_id === (int) $quiz_id;
$is_final_quiz     = $final_quiz_id && (int) $final_quiz_id === (int) $quiz_id;
$course_title      = $course ? $course->getTitle() : '';
$course_url        = $course_id ? get_permalink( $course_id ) : '';
$course_summary_url = $course_url;
$courses_listing_url = home_url( '/courses/' );
$related_product_id  = $course ? $course->getRelatedProductId() : 0;
$product_url         = $related_product_id ? get_permalink( $related_product_id ) : '';
$user_has_course_access = $course && $current_user_id
    ? $course->isUserEnrolled( $current_user_id )
    : false;
$has_bought = false;

if ( $current_user_id && $related_product_id && function_exists( 'wc_customer_bought_product' ) ) {
    $has_bought = wc_customer_bought_product( '', $current_user_id, $related_product_id );

    if ( ! $has_bought && class_exists( 'PoliteiaOrderFinder' ) ) {
        $order_finder = new PoliteiaOrderFinder();
        $order_id     = $order_finder->findOrderForUser( $current_user_id, $related_product_id );

        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && $order->has_status( [ 'completed', 'processing' ] ) ) {
                $has_bought = true;
            }
        }
    }
}

$analytics_first_data = [];
$analytics_final_data = [];
if ( $analytics && $current_user_id ) {
    $analytics_first_data = $analytics->getFirstQuizData( (int) $current_user_id );
    $analytics_final_data = $analytics->getFinalQuizData( (int) $current_user_id );
}

$analytics_first_attempt = $analytics_first_data['latest_attempt'] ?? null;
$analytics_final_attempt = $analytics_final_data['latest_attempt'] ?? null;

$first_attempt_summary = null;
$final_attempt_summary = null;
$first_quiz_score      = 0;
$final_quiz_score      = 0;

if ( $current_user_id && class_exists( 'Politeia_Quiz_Stats' ) ) {
    if ( $first_quiz_id ) {
        $first_attempt_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $current_user_id, $first_quiz_id );
    }

    if ( $final_quiz_id ) {
        $final_attempt_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $current_user_id, $final_quiz_id );
    }
}

if ( $first_attempt_summary ) {
    $first_quiz_score = (int) $first_attempt_summary['percentage'];
} elseif ( $analytics_first_attempt && isset( $analytics_first_attempt['percentage'] ) ) {
    $first_quiz_score = (int) round( floatval( rtrim( $analytics_first_attempt['percentage'], '%' ) ) );
}

if ( $is_final_quiz && $final_attempt_summary ) {
    $final_quiz_score = (int) $final_attempt_summary['percentage'];
} elseif ( $analytics_final_attempt && isset( $analytics_final_attempt['percentage'] ) ) {
    $final_quiz_score = (int) round( floatval( rtrim( $analytics_final_attempt['percentage'], '%' ) ) );
}

$blocking_notices = [];
$info_notices      = [];

if ( ! $course_id ) {
    $blocking_notices[] = __( 'No pudimos identificar el curso asociado a este quiz. Contacta a soporte para revisarlo.', 'politeia-quiz-control' );
}

if ( $is_first_quiz && ! $first_quiz_id ) {
    $blocking_notices[] = __( 'El curso no tiene configurado un First Quiz. Un administrador debe configurarlo.', 'politeia-quiz-control' );
}

if ( $is_final_quiz ) {
    if ( ! $first_quiz_id ) {
        $blocking_notices[] = __( 'Este curso no tiene asignado un First Quiz, por lo que no podemos comparar resultados.', 'politeia-quiz-control' );
    }

    if ( ! $final_quiz_id ) {
        $blocking_notices[] = __( 'Este curso no tiene asignado un Final Quiz en la metabox correspondiente.', 'politeia-quiz-control' );
    }

    if ( $first_quiz_id && ! $first_attempt_summary ) {
        $info_notices[] = __( 'Aún no encontramos un resultado del First Quiz para este curso. Completa el quiz inicial para desbloquear la comparación.', 'politeia-quiz-control' );
    }

    if ( $final_quiz_id && ! $final_attempt_summary ) {
        $info_notices[] = __( 'Todavía estamos registrando tu resultado del Final Quiz. Actualiza la página en unos segundos.', 'politeia-quiz-control' );
    }
}

if ( $is_first_quiz && $first_quiz_id && ! $first_attempt_summary ) {
    $info_notices[] = __( 'Tu puntaje aparecerá aquí apenas el sistema confirme el intento. Si no sucede en unos minutos, refresca la página.', 'politeia-quiz-control' );
}

$progress_delta        = null;
$days_between_attempts = null;

if ( $is_final_quiz && $first_attempt_summary && $final_attempt_summary ) {
    $progress_delta = (int) $final_attempt_summary['percentage'] - (int) $first_attempt_summary['percentage'];

    if ( ! empty( $first_attempt_summary['completed_timestamp'] ) && ! empty( $final_attempt_summary['completed_timestamp'] ) ) {
        $seconds_between       = max( 0, (int) $final_attempt_summary['completed_timestamp'] - (int) $first_attempt_summary['completed_timestamp'] );
        $days_between_attempts = (int) floor( $seconds_between / DAY_IN_SECONDS );
    }
}

$ajax_nonce    = wp_create_nonce( 'politeia_quiz_stats' );
$results_nonce = wp_create_nonce( 'politeia_course_results' );

$can_render_charts = empty( $blocking_notices );

$quiz_stats_bootstrap = array(
    'quizId'               => (int) $quiz_id,
    'userId'               => (int) $current_user_id,
    'courseId'             => (int) $course_id,
    'firstQuizId'          => (int) $first_quiz_id,
    'finalQuizId'          => (int) $final_quiz_id,
    'isFirstQuiz'          => (bool) $is_first_quiz,
    'isFinalQuiz'          => (bool) $is_final_quiz,
    'hasFirstQuiz'         => (bool) $first_quiz_id,
    'hasFinalQuiz'         => (bool) $final_quiz_id,
    'firstQuizScore'       => (int) $first_quiz_score,
    'finalQuizStoredScore' => (int) $final_quiz_score,
    'hasFinalScore'        => (bool) $final_attempt_summary,
    'firstAttemptFound'    => (bool) $first_attempt_summary,
    'finalAttemptFound'    => (bool) $final_attempt_summary,
    'progressDelta'        => null === $progress_delta ? null : (int) $progress_delta,
    'daysBetweenAttempts'  => null === $days_between_attempts ? null : (int) $days_between_attempts,
    'firstQuizCompleted'   => $first_attempt_summary['completed'] ?? '',
    'finalQuizCompleted'   => $final_attempt_summary['completed'] ?? '',
    'firstQuizCompletedTs' => $first_attempt_summary['completed_timestamp'] ?? 0,
    'finalQuizCompletedTs' => $final_attempt_summary['completed_timestamp'] ?? 0,
    'ajaxUrl'              => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
    'nonce'                => $ajax_nonce,
    'resultsNonce'         => $results_nonce,
    'courseUrl'            => $course_url ? esc_url_raw( $course_url ) : '',
    'courseSummaryUrl'     => $course_summary_url ? esc_url_raw( $course_summary_url ) : '',
    'productUrl'           => $product_url ? esc_url_raw( $product_url ) : '',
    'coursesListingUrl'    => esc_url_raw( $courses_listing_url ),
    'courseTitle'          => $course_title ? wp_strip_all_tags( $course_title ) : '',
    'hasCourseAccess'      => (bool) $user_has_course_access,
    'hasPurchased'         => (bool) $has_bought,
    'blockingNotices'      => $blocking_notices,
    'infoNotices'          => $info_notices,
    'canRenderCharts'      => (bool) $can_render_charts,
);

$quiz_stats_bootstrap_json = wp_json_encode( $quiz_stats_bootstrap );
$cta_data_context          = $is_final_quiz ? 'final' : 'first';
$cta_course_url_attr       = $course_url ? esc_url( $course_url ) : '';
$cta_product_url_attr      = $product_url ? esc_url( $product_url ) : '';
$cta_courses_listing_attr  = esc_url( $courses_listing_url );
$cta_course_title_attr     = $course_title ? esc_attr( $course_title ) : '';
$cta_summary_url_attr      = $course_summary_url ? esc_url( $course_summary_url ) : '';

?>

<style>
.wpProQuiz_results .wpProQuiz_quiz_time,
.wpProQuiz_results .wpProQuiz_points,
.wpProQuiz_results .wpProQuiz_points--message {
    display: none !important;
}

.politeia-quiz-cta {
    margin-top: 20px;
    text-align: center;
}

.politeia-quiz-cta .button {
    background: #000;
    color: #fff;
    padding: 10px 24px;
    display: inline-block;
    text-decoration: none;
    border-radius: 4px;
}

.politeia-quiz-messages {
    margin: 20px auto 0;
    max-width: 640px;
    background: #fff7e6;
    border: 1px solid #f2c97d;
    padding: 16px 20px;
    border-radius: 6px;
    color: #8a6d3b;
    font-size: 14px;
    display: none;
}

.politeia-quiz-messages ul {
    margin: 0;
    padding-left: 18px;
    text-align: left;
}

.politeia-results-trigger {
    margin: 25px auto 0;
    text-align: center;
    display: none;
}

.politeia-results-trigger .button {
    background: #1d4ed8;
    color: #fff;
    padding: 10px 24px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.politeia-quiz-encouragement {
    margin-top: 20px;
    text-align: center;
    font-size: 15px;
    color: #333;
}

.politeia-quiz-comparison {
    margin: 30px auto 0;
    max-width: 640px;
    background: #f8f8f8;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e1e1e1;
}

.politeia-quiz-comparison h3 {
    margin-top: 0;
    text-align: center;
}

.politeia-quiz-comparison .metrics {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin-top: 15px;
}

.politeia-quiz-comparison .metric {
    min-width: 120px;
    text-align: center;
}

.politeia-quiz-attempt-status,
.politeia-quiz-attempt-details {
    margin: 25px auto 0;
    max-width: 640px;
    text-align: center;
}

.politeia-quiz-attempt-details {
    background: #f0f8f0;
    border: 1px solid #4CAF50;
    padding: 20px;
    border-radius: 6px;
}
</style>

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
                        'message'      => sprintf(
                            esc_html_x( '%s complete. Results are being recorded.', 'placeholder: Quiz', 'learndash' ),
                            LearnDash_Custom_Label::get_label( 'quiz' )
                        ),
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

<div id="top-message-result" class="wpProQuiz_results" style="display: none;">

    <div id="score" style="margin-top: 40px; text-align: center; font-weight: bold; font-size: 16px; padding: 0 20px;"></div>

    <div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; margin-bottom: 30px;">
        <div style="max-width: 300px;">
            <div id="radial-chart"></div>
        </div>
        <div style="max-width: 300px;">
            <div id="radial-chart-promedio"></div>
        </div>
    </div>

    <div style="margin: 10px auto; max-width: 600px; padding: 10px 20px; border: 1px dashed #eee; font-size: 14px; text-align: center; background-color: #f9f9f9; display:none;">
        <strong>LATEST ACTIVITY ID (PHP Render - Global):</strong>
        <?php echo esc_html( $php_rendered_latest_global_activity_id ? $php_rendered_latest_global_activity_id : 'N/A' ); ?>
        <br>
        <small style="color:#777;">
            (This is the *global* activity ID available in the database at the moment the page loads)
        </small>
    </div>

    <div style="margin: 10px auto; max-width: 600px; padding: 10px 20px; border: 1px dashed #eee; font-size: 14px; text-align: center; background-color: #f9f9f9; color: blue; display:none;">
        <strong>PHP Calculated Promedio Polis (initial chart value – from shortcode):</strong>
        <?php echo esc_html( $polis_average ); ?>%<br>
        <strong>PHP Calculated Polis Attempts Count (initial chart value – from shortcode):</strong>
        <?php echo esc_html( $polis_attempts_count ); ?><br>
        <strong>Attempts Considered for PHP Initial Average (from shortcode data):</strong>
        <ul style="text-align: left; margin: 5px auto; padding-left: 20px;">
            <?php
            if ( ! empty( $polis_attempts_list_for_display ) ) {
                foreach ( $polis_attempts_list_for_display as $attempt ) {
                    echo '<li>ID: ' . esc_html( $attempt['id'] ) . ' – ' . esc_html( $attempt['percentage'] ) . '%</li>';
                }
            } else {
                echo '<li>No attempts considered for initial average.</li>';
            }
            ?>
        </ul>
    </div>

    <div id="quiz-messages" class="politeia-quiz-messages"></div>

    <?php if ( $is_final_quiz ) : ?>
        <div id="politeia-results-trigger" class="politeia-results-trigger">
            <button type="button" id="politeia-results-button" class="button"><?php esc_html_e( 'View Results', 'politeia-quiz-control' ); ?></button>
        </div>
    <?php endif; ?>

    <div id="quiz-comparison-panel" class="politeia-quiz-comparison" style="display:none;"></div>

    <div id="quiz-encouragement" class="politeia-quiz-encouragement" style="display:none;"></div>

    <div
        id="quiz-cta-container"
        class="politeia-quiz-cta"
        data-context="<?php echo esc_attr( $cta_data_context ); ?>"
        data-course-id="<?php echo (int) $course_id; ?>"
        data-course-url="<?php echo esc_attr( $cta_course_url_attr ); ?>"
        data-summary-url="<?php echo esc_attr( $cta_summary_url_attr ); ?>"
        data-product-url="<?php echo esc_attr( $cta_product_url_attr ); ?>"
        data-courses-listing-url="<?php echo esc_attr( $cta_courses_listing_attr ); ?>"
        data-course-title="<?php echo $cta_course_title_attr; ?>"
        data-has-access="<?php echo $user_has_course_access ? '1' : '0'; ?>"
        data-has-purchased="<?php echo $has_bought ? '1' : '0'; ?>"
    ></div>

    <div id="datos-del-intento-status" class="politeia-quiz-attempt-status">
        <p style="color:#555;">
            Buscando el registro del último intento (mayor a
            <?php echo esc_html( $php_rendered_latest_global_activity_id ? $php_rendered_latest_global_activity_id : '0' ); ?>)…
        </p>
    </div>

    <div id="datos-del-intento-container" class="politeia-quiz-attempt-details" style="display:none;"></div>

    <?php
    if ( ! $quiz->isHideResultQuizTime() ) {
        ?>
        <p class="wpProQuiz_quiz_time" style="margin-bottom: 40px;">
            <?php
            echo wp_kses_post(
                SFWD_LMS::get_template(
                    'learndash_quiz_messages',
                    array(
                        'quiz_post_id' => $quiz->getID(),
                        'context'      => 'quiz_your_time_message',
                        'message'      => sprintf(
                            esc_html_x( 'Your time: %s', 'placeholder: quiz time.', 'learndash' ),
                            '<span></span>'
                        ),
                    )
                )
            );
            ?>
        </p>
        <?php
    }
    ?>

    <?php
    if ( ! $quiz->isHideResultCorrectQuestion() ) {
        echo wp_kses_post(
            SFWd_LMS::get_template(
                'learndash_quiz_messages',
                array(
                    'quiz_post_id'  => $quiz->getID(),
                    'context'       => 'quiz_questions_answered_correctly_message',
                    'message'       => '<p>' . sprintf(
                        esc_html_x(
                            '%1$s of %2$s %3$s answered correctly',
                            'placeholder: correct answer, question count, questions',
                            'learndash'
                        ),
                        '<span class="wpProQuiz_correct_answer">0</span>',
                        '<span>' . $question_count . '</span>',
                        learndash_get_custom_label( 'questions' )
                    ) . '</p>',
                    'placeholders'  => array( '0', '0', '0' ),
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
                    'message'      => sprintf(
                        esc_html_x( 'You have reached %1$s of %2$s point(s), (%3$s)', 'placeholder: points earned, points total', 'learndash' ),
                        '<span>0</span>',
                        '<span>0</span>',
                        '<span>0</span>'
                    ),
                    'placeholders' => array( '0', '0', '0' ),
                )
            )
        );
        ?>
    </p>

    <?php // La tabla de debug se queda aquí, solo para el Final Quiz
    if ( $is_final_quiz && current_user_can( 'manage_options' ) ) :
    ?>
        <table style="width:100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; border: 1px dashed #ccc; display:none;">
            <caption style="font-weight: bold; padding: 5px;">Admin Debug Info</caption>
            <tbody>
                <!-- ... aquí siguen todas tus filas de debug tal cual estaban ... -->
            </tbody>
        </table>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const pointsMessage = document.querySelector('.wpProQuiz_points.wpProQuiz_points--message');
    const chartContainer = document.querySelector('#radial-chart');
    const chartContainerPromedio = document.querySelector('#radial-chart-promedio');
    const datosDelIntentoContainer = document.getElementById('datos-del-intento-container');
    const datosDelIntentoStatus = document.getElementById('datos-del-intento-status');
    const comparisonPanel = document.getElementById('quiz-comparison-panel');
    const encouragementContainer = document.getElementById('quiz-encouragement');
    const ctaContainer = document.getElementById('quiz-cta-container');
    const scoreDiv = document.getElementById('score');
    const messagesContainer = document.getElementById('quiz-messages');
    const resultsTrigger = document.getElementById('politeia-results-trigger');
    const resultsButton = document.getElementById('politeia-results-button');
    const resultsModal = document.getElementById('politeia-results-modal');
    const resultsModalBody = resultsModal ? resultsModal.querySelector('.politeia-results-modal__body') : null;
    const modalCloseTriggers = resultsModal ? resultsModal.querySelectorAll('[data-close-modal]') : [];

    const quizStatsBootstrap = <?php echo $quiz_stats_bootstrap_json ? $quiz_stats_bootstrap_json : '{}'; ?> || {};
    const isFinalQuiz = !!quizStatsBootstrap.isFinalQuiz;
    const isFirstQuiz = !!quizStatsBootstrap.isFirstQuiz;
    const hasFirstQuiz = !!quizStatsBootstrap.hasFirstQuiz;
    const hasFinalQuiz = !!quizStatsBootstrap.hasFinalQuiz;
    const firstQuizScore = Number(quizStatsBootstrap.firstQuizScore || 0);
    const storedFinalScore = Number(quizStatsBootstrap.finalQuizStoredScore || 0);
    const hasFinalScore = !!quizStatsBootstrap.hasFinalScore;
    const polisAverageInitialPHP = <?php echo (int) $polis_average; ?>;
    const ajaxUrl = quizStatsBootstrap.ajaxUrl || '';
    const currentQuizId = Number(quizStatsBootstrap.quizId || <?php echo (int) $quiz_id; ?>);
    const currentUserId = Number(quizStatsBootstrap.userId || 0);
    const ajaxNonce = quizStatsBootstrap.nonce || '';
    const resultsNonce = quizStatsBootstrap.resultsNonce || '';
    const courseSummaryUrl = quizStatsBootstrap.courseSummaryUrl || '';
    const courseId = Number(quizStatsBootstrap.courseId || 0);
    const firstAttemptFound = !!quizStatsBootstrap.firstAttemptFound;
    const finalAttemptFound = !!quizStatsBootstrap.finalAttemptFound;
    const blockingNotices = Array.isArray(quizStatsBootstrap.blockingNotices) ? quizStatsBootstrap.blockingNotices : [];
    const infoNotices = Array.isArray(quizStatsBootstrap.infoNotices) ? quizStatsBootstrap.infoNotices : [];
    const canRenderCharts = quizStatsBootstrap.canRenderCharts !== false;
    const firstQuizCompleted = quizStatsBootstrap.firstQuizCompleted || '';
    const finalQuizCompleted = quizStatsBootstrap.finalQuizCompleted || '';
    const baseDaysBetween = typeof quizStatsBootstrap.daysBetweenAttempts === 'number'
        ? quizStatsBootstrap.daysBetweenAttempts
        : null;

    const phpInitialLatestActivityId = <?php echo $php_rendered_latest_global_activity_id ? (int) $php_rendered_latest_global_activity_id : 0; ?>;

    let FinalScore = storedFinalScore;
    let FirstScore = firstQuizScore;
    let finalAttemptSeen = finalAttemptFound;
    let firstAttemptSeen = firstAttemptFound;

    let radialChartInstance = null;
    let radialChartPromedioInstance = null;

    FirstScore = Number.isFinite(FirstScore) ? FirstScore : 0;
    FinalScore = Number.isFinite(FinalScore) ? FinalScore : 0;

    renderMessages();
    toggleResultsTrigger();

    if (!canRenderCharts) {
        if (ctaContainer) {
            ctaContainer.innerHTML = '';
            ctaContainer.style.display = 'none';
        }
        if (comparisonPanel) {
            comparisonPanel.innerHTML = '';
            comparisonPanel.style.display = 'none';
        }
        if (encouragementContainer) {
            encouragementContainer.innerHTML = '';
            encouragementContainer.style.display = 'none';
        }
        if (resultsTrigger) {
            resultsTrigger.style.display = 'none';
        }
        if (scoreDiv) {
            scoreDiv.innerHTML = '';
        }
        return;
    }

    const resultSpans = pointsMessage ? pointsMessage.querySelectorAll('span') : [];
    if (!pointsMessage || !resultSpans.length) {
        return;
    }

    const percentageSpan = resultSpans[2] || null;
    if (!percentageSpan) {
        return;
    }

    function buildChartOptions(value, labelText) {
        return {
            series: [Math.max(0, Math.round(value))],
            chart: { height: 400, type: 'radialBar' },
            plotOptions: {
                radialBar: {
                    hollow: { size: '60%' },
                    dataLabels: {
                        name: { show: true, offsetY: -10, color: '#666', fontSize: '16px' },
                        value: {
                            show: true,
                            fontSize: '32px',
                            fontWeight: 600,
                            color: '#111',
                            offsetY: 8,
                            formatter: function (val) {
                                return Math.round(val) + '%';
                            }
                        }
                    }
                }
            },
            labels: [labelText],
            colors: ['#d29d01'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'diagonal',
                    gradientToColors: ['#ffd000'],
                    stops: [0, 100],
                    opacityFrom: 1,
                    opacityTo: 1,
                    angle: 145
                }
            }
        };
    }

    function ensureChart(instance, container, value, labelText) {
        if (!container) {
            return instance;
        }
        const options = buildChartOptions(value, labelText);
        if (!instance) {
            instance = new ApexCharts(container, options);
            instance.render();
        } else {
            instance.updateOptions(options);
        }
        return instance;
    }

    function escapeHtml(value) {
        if (typeof value !== 'string') {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return value.replace(/[&<>"']/g, function (char) {
            return map[char] || char;
        });
    }

    function formatDelta(delta) {
        return delta > 0 ? `+${delta}` : `${delta}`;
    }

    function describeDays(days) {
        if (typeof days !== 'number') {
            return '';
        }
        if (days === 0) {
            return 'Mismo día';
        }
        if (days === 1) {
            return '1 día entre intentos';
        }
        return `${days} días entre intentos`;
    }

    function renderMessages() {
        if (!messagesContainer) {
            return;
        }

        const sections = [];

        if (blockingNotices.length) {
            const list = blockingNotices.map(msg => `<li>${escapeHtml(msg)}</li>`).join('');
            sections.push(`<strong>Atención:</strong><ul>${list}</ul>`);
        }

        if (infoNotices.length) {
            const list = infoNotices.map(msg => `<li>${escapeHtml(msg)}</li>`).join('');
            sections.push(`<strong>Nota:</strong><ul>${list}</ul>`);
        }

        if (!sections.length) {
            messagesContainer.innerHTML = '';
            messagesContainer.style.display = 'none';
            return;
        }

        messagesContainer.innerHTML = sections.join('<hr style="border:none;border-top:1px solid #f5d69c;margin:12px 0;" />');
        messagesContainer.style.display = 'block';
    }

    function toggleResultsTrigger() {
        if (!resultsTrigger) {
            return;
        }

        if (isFinalQuiz && firstAttemptSeen && finalAttemptSeen) {
            resultsTrigger.style.display = 'block';
        } else {
            resultsTrigger.style.display = 'none';
        }
    }

    function updateScoreContent(finalScore) {
        if (!scoreDiv) {
            return;
        }

        if (isFinalQuiz) {
            const delta = hasFirstQuiz ? Math.round(finalScore - FirstScore) : null;
            let heading = '¡Felicitaciones por completar el curso!';
            let paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>.`;

            if (hasFirstQuiz && firstAttemptSeen && delta !== null) {
                if (delta > 0) {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong> y mejoraste ${formatDelta(delta)} puntos respecto del First Quiz.`;
                } else if (delta === 0) {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>. Mantienes un desempeño constante entre intentos.`;
                } else {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>. Detectamos una variación de ${formatDelta(delta)} puntos; repasar las lecciones finales puede ayudarte a reforzar lo aprendido.`;
                }
            } else {
                paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>. Revisa el resumen del curso para conocer tus siguientes pasos.`;
            }

            scoreDiv.innerHTML = `
                <h2 style="text-align:center;color:#000;">${heading}</h2>
                <p style="text-align:center;color:#333;font-weight:normal;">${paragraph}</p>
            `;
        } else {
            scoreDiv.innerHTML = `
                <h2 style="text-align:center;color:#000;">Tu puntaje: ${Math.round(finalScore)}%</h2>
                <p style="text-align:center;color:#555;font-weight:normal;">Tu resultado quedó registrado. Continúa con las lecciones del curso para aprovechar este impulso.</p>
            `;
        }
    }

    function renderEncouragement(finalScore) {
        if (!encouragementContainer) {
            return;
        }

        let message = '';
        if (isFinalQuiz) {
            if (hasFirstQuiz && firstAttemptSeen) {
                const delta = Math.round(finalScore - FirstScore);
                if (delta > 0) {
                    message = `Mejoraste ${formatDelta(delta)} puntos desde tu primer intento. Celebra el avance y comparte tu logro.`;
                } else if (delta === 0) {
                    message = 'Mantienes un desempeño constante. Revisa los materiales complementarios para seguir creciendo.';
                } else {
                    message = `Hubo una variación de ${formatDelta(delta)} puntos. Repasa las lecciones clave para reforzar tu conocimiento.`;
                }
            } else {
                message = 'Consulta el resumen del curso y comparte tu logro con tu comunidad.';
            }
        } else {
            if (finalScore >= 90) {
                message = '¡Impresionante comienzo! Estás listo para profundizar en todas las lecciones del curso.';
            } else if (finalScore >= 70) {
                message = 'Vas por muy buen camino. El curso completo te ayudará a convertir este resultado en dominio total.';
            } else {
                message = 'Cada intento suma. Inscríbete para acceder a las lecciones paso a paso y elevar tu puntaje.';
            }
        }

        if (message) {
            encouragementContainer.innerHTML = `<p>${message}</p>`;
            encouragementContainer.style.display = 'block';
        } else {
            encouragementContainer.innerHTML = '';
            encouragementContainer.style.display = 'none';
        }
    }

    function renderCTA(finalScore) {
        if (!ctaContainer) {
            return;
        }

        const context = ctaContainer.dataset.context || 'first';
        const courseUrl = ctaContainer.dataset.courseUrl || '';
        const summaryUrl = ctaContainer.dataset.summaryUrl || '';
        const productUrl = ctaContainer.dataset.productUrl || '';
        const listingUrl = ctaContainer.dataset.coursesListingUrl || '';
        const hasAccess = ctaContainer.dataset.hasAccess === '1';
        const hasPurchased = ctaContainer.dataset.hasPurchased === '1';
        const title = ctaContainer.dataset.courseTitle || '';

        let label = '';
        let href = '';
        let helper = '';

        if (context === 'final') {
            if (summaryUrl) {
                href = summaryUrl;
                label = 'Ver resumen del curso';
                helper = 'Consulta tu avance final y descubre los próximos pasos.';
            } else if (courseUrl && (hasAccess || hasPurchased)) {
                href = courseUrl;
                label = 'Volver al curso';
                helper = 'Revisa el material complementario y celebra tu logro.';
            } else {
                href = listingUrl || courseUrl || '#';
                label = 'Explorar más cursos';
                helper = 'Continúa tu camino de aprendizaje con nuevas rutas recomendadas.';
            }
        } else {
            if (hasAccess || hasPurchased) {
                href = courseUrl || listingUrl || '#';
                label = courseUrl ? 'Ir al curso' : 'Explorar cursos';
                helper = title ? `Ingresa a ${title} y continúa tu avance.` : 'Ingresa al curso para continuar tu progreso.';
            } else if (productUrl) {
                href = productUrl;
                label = 'Comprar el curso';
                helper = finalScore >= 70
                    ? 'Aprovecha el impulso y desbloquea todas las lecciones.'
                    : 'Inscríbete para acceder a las lecciones guiadas y mejorar tu puntaje.';
            } else {
                href = listingUrl || '#';
                label = 'Explorar cursos';
                helper = 'Conoce nuestras rutas de aprendizaje y continúa mejorando.';
            }
        }

        if (!href || href === '#') {
            ctaContainer.innerHTML = '';
            ctaContainer.style.display = 'none';
            return;
        }

        ctaContainer.innerHTML = `
            <a class="button" href="${href}">${label}</a>
            ${helper ? `<p style="margin-top:10px;color:#555;">${helper}</p>` : ''}
        `;
        ctaContainer.style.display = 'block';
    }

    function renderComparison(finalScore) {
        if (!comparisonPanel) {
            return;
        }

        if (!isFinalQuiz) {
            comparisonPanel.innerHTML = '';
            comparisonPanel.style.display = 'none';
            return;
        }

        if (!hasFirstQuiz) {
            comparisonPanel.innerHTML = '<p style="text-align:center;color:#555;">Este curso no tiene configurado un First Quiz para comparar resultados.</p>';
            comparisonPanel.style.display = 'block';
            return;
        }

        if (!firstAttemptSeen) {
            comparisonPanel.innerHTML = '<p style="text-align:center;color:#555;">Completa el First Quiz para desbloquear la comparación de resultados.</p>';
            comparisonPanel.style.display = 'block';
            return;
        }

        if (!finalAttemptSeen) {
            comparisonPanel.innerHTML = '<p style="text-align:center;color:#555;">Estamos registrando tu resultado final. Actualiza la página en unos instantes.</p>';
            comparisonPanel.style.display = 'block';
            return;
        }

        const delta = Math.round(finalScore - FirstScore);
        const deltaLabel = formatDelta(delta);
        const daysCopy = describeDays(baseDaysBetween);

        comparisonPanel.innerHTML = `
            <h3>Tu progreso entre quizzes</h3>
            <div class="metrics">
                <div class="metric">
                    <strong>First Quiz</strong>
                    <div style="font-size:24px;">${Math.round(FirstScore)}%</div>
                    ${firstQuizCompleted ? `<small>${firstQuizCompleted}</small>` : ''}
                </div>
                <div class="metric">
                    <strong>Final Quiz</strong>
                    <div style="font-size:24px;">${Math.round(finalScore)}%</div>
                    ${finalQuizCompleted ? `<small>${finalQuizCompleted}</small>` : ''}
                </div>
                <div class="metric">
                    <strong>Cambio</strong>
                    <div style="font-size:24px;">${deltaLabel}%</div>
                    ${daysCopy ? `<small>${daysCopy}</small>` : ''}
                </div>
            </div>
        `;
        comparisonPanel.style.display = 'block';
    }


    function hydrateInterface(finalScore) {
        FinalScore = Math.max(0, Math.round(finalScore));

        if (isFirstQuiz) {
            firstAttemptSeen = true;
            FirstScore = FinalScore;
        }

        if (isFinalQuiz) {
            finalAttemptSeen = true;
        }

        updateScoreContent(FinalScore);
        renderEncouragement(FinalScore);
        renderCTA(FinalScore);
        renderComparison(FinalScore);
        toggleResultsTrigger();

        radialChartInstance = ensureChart(
            radialChartInstance,
            chartContainer,
            FinalScore,
            'Tu Puntaje'
        );

        if (isFinalQuiz && !hasFirstQuiz) {
            if (radialChartPromedioInstance) {
                radialChartPromedioInstance.destroy();
                radialChartPromedioInstance = null;
            }
            if (chartContainerPromedio) {
                chartContainerPromedio.innerHTML = '<p style="text-align:center;color:#777;">Sin datos del First Quiz configurado.</p>';
            }
        } else {
            const secondaryValue = isFinalQuiz ? FirstScore : polisAverageInitialPHP;
            const secondaryLabel = isFinalQuiz ? 'First Score' : 'Promedio Polis';

            radialChartPromedioInstance = ensureChart(
                radialChartPromedioInstance,
                chartContainerPromedio,
                secondaryValue,
                secondaryLabel
            );
        }
    }

    function updateAttemptStatus(message, tone = 'info', options = {}) {
        if (!datosDelIntentoStatus) {
            return;
        }
        const colors = {
            info: '#555',
            warning: '#D57A00',
            error: '#D32F2F',
            success: '#2E7D32'
        };
        const allowHtml = options && options.allowHtml;
        const safeMessage = allowHtml ? String(message) : escapeHtml(String(message));
        datosDelIntentoStatus.innerHTML = `<p style="color:${colors[tone] || colors.info};">${safeMessage}</p>`;
        datosDelIntentoStatus.style.display = 'block';
    }

    function renderAttemptDetails(attemptData) {
        if (!datosDelIntentoContainer) {
            return;
        }

        const safeActivityId = Number(attemptData.activity_id) || 0;
        const safeStarted = attemptData.started ? escapeHtml(String(attemptData.started)) : '';
        const safeCompleted = attemptData.completed ? escapeHtml(String(attemptData.completed)) : '';
        const safeDuration = Number(attemptData.duration) || 0;
        const scoreData = attemptData.score_data || {};
        const safeScore = Number(scoreData.score) || 0;
        const safeTotalPoints = Number(scoreData.total_points) || 0;
        const safePercentage = Number(scoreData.percentage) || 0;
        const safePassed = !!scoreData.passed;

        datosDelIntentoContainer.innerHTML = `
            <h4 style="margin-bottom:12px;color:#2E7D32;">✅ Datos del Intento</h4>
            <p><strong>Activity ID:</strong> ${safeActivityId}</p>
            <p><strong>Inicio:</strong> ${safeStarted}</p>
            <p><strong>Término:</strong> ${safeCompleted}</p>
            <p><strong>Duración:</strong> ${safeDuration} segundos</p>
            <hr style="margin:16px 0;border-top:1px solid #c8e6c9;" />
            <p><strong>Puntaje:</strong> ${safeScore} de ${safeTotalPoints}</p>
            <p><strong>Porcentaje:</strong> ${safePercentage}%</p>
            <p><strong>Estado:</strong> ${safePassed ? 'Aprobado' : 'Reprobado'}</p>
        `;
        datosDelIntentoContainer.style.display = 'block';
        if (datosDelIntentoStatus) {
            datosDelIntentoStatus.style.display = 'none';
        }
    }


    function setModalContent(html) {
        if (!resultsModalBody) {
            return;
        }
        resultsModalBody.innerHTML = html;
    }

    function renderModalMessagesSection(messages) {
        if (!Array.isArray(messages) || !messages.length) {
            return '';
        }
        const items = messages.map(msg => `<p>${escapeHtml(msg)}</p>`).join('');
        return `<div class="politeia-results-messages">${items}</div>`;
    }

    function renderModalResults(data) {
        if (!resultsModalBody) {
            return;
        }

        const firstSummary = data && data.first_quiz ? data.first_quiz.summary : null;
        const finalSummary = data && data.final_quiz ? data.final_quiz.summary : null;

        if (!firstSummary || !finalSummary) {
            const fallback = data && data.messages ? renderModalMessagesSection(data.messages) : '';
            setModalContent(`<p style="text-align:center;color:#555;">Necesitamos ambos resultados para mostrar la comparación.</p>${fallback}`);
            return;
        }

        const metrics = data && data.metrics ? data.metrics : {};
        const delta = typeof metrics.score_delta === 'number' ? metrics.score_delta : null;
        const daysCopy = typeof metrics.days_elapsed === 'number' ? describeDays(metrics.days_elapsed) : '';

        const firstMeta = [];
        if (typeof firstSummary.score === 'number' && typeof firstSummary.total_points === 'number') {
            firstMeta.push(`${firstSummary.score} / ${firstSummary.total_points} pts`);
        }
        if (firstSummary.completed) {
            firstMeta.push(escapeHtml(firstSummary.completed));
        }

        const finalMeta = [];
        if (typeof finalSummary.score === 'number' && typeof finalSummary.total_points === 'number') {
            finalMeta.push(`${finalSummary.score} / ${finalSummary.total_points} pts`);
        }
        if (finalSummary.completed) {
            finalMeta.push(escapeHtml(finalSummary.completed));
        }

        const variationMeta = [];
        if (daysCopy) {
            variationMeta.push(escapeHtml(daysCopy));
        }

        const messagesHtml = renderModalMessagesSection(data && data.messages);

        setModalContent(`
            <h2 id="politeia-results-modal__title" style="margin-top:0;">Comparación de resultados</h2>
            <div class="politeia-results-grid">
                <div class="politeia-results-card">
                    <strong>First Quiz</strong>
                    <div class="politeia-results-score">${Math.round(firstSummary.percentage || 0)}%</div>
                    ${firstMeta.length ? `<div class="politeia-results-meta">${firstMeta.join('<br>')}</div>` : ''}
                </div>
                <div class="politeia-results-card">
                    <strong>Final Quiz</strong>
                    <div class="politeia-results-score">${Math.round(finalSummary.percentage || 0)}%</div>
                    ${finalMeta.length ? `<div class="politeia-results-meta">${finalMeta.join('<br>')}</div>` : ''}
                </div>
                <div class="politeia-results-card">
                    <strong>Variación</strong>
                    <div class="politeia-results-score">${delta === null ? '–' : `${formatDelta(delta)}%`}</div>
                    ${variationMeta.length ? `<div class="politeia-results-meta">${variationMeta.join('<br>')}</div>` : ''}
                </div>
            </div>
            ${messagesHtml}
        `);
    }

    function openResultsModal() {
        if (!resultsModal) {
            return;
        }
        resultsModal.classList.add('is-open');
        resultsModal.setAttribute('aria-hidden', 'false');
    }

    function closeResultsModal() {
        if (!resultsModal) {
            return;
        }
        resultsModal.classList.remove('is-open');
        resultsModal.setAttribute('aria-hidden', 'true');
    }

    function fetchComparisonData() {
        if (!ajaxUrl) {
            setModalContent('<p style="text-align:center;color:#c0392b;">No pudimos conectar con el servidor.</p>');
            return;
        }
        if (!resultsNonce || !courseId) {
            setModalContent('<p style="text-align:center;color:#c0392b;">Faltan datos para mostrar los resultados del curso.</p>');
            return;
        }

        setModalContent('<div class="politeia-results-modal__loading">Cargando resultados…</div>');

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mostrar_resultados_curso',
                nonce: resultsNonce,
                course_id: courseId,
                first_quiz_id: Number(quizStatsBootstrap.firstQuizId || 0),
                final_quiz_id: Number(quizStatsBootstrap.finalQuizId || 0)
            },
            success: function (response) {
                if (response.success && response.data) {
                    renderModalResults(response.data);
                } else {
                    const message = response && response.data && response.data.message
                        ? escapeHtml(response.data.message)
                        : 'No pudimos obtener los resultados del curso.';
                    const extra = response && response.data ? renderModalMessagesSection(response.data.messages) : '';
                    setModalContent(`<p style="text-align:center;color:#c0392b;">${message}</p>${extra}`);
                }
            },
            error: function () {
                setModalContent('<p style="text-align:center;color:#c0392b;">Ocurrió un problema al consultar los resultados.</p>');
            }
        });
    }

    if (resultsButton) {
        resultsButton.addEventListener('click', function () {
            openResultsModal();
            fetchComparisonData();
        });
    }

    modalCloseTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', closeResultsModal);
    });

    if (resultsModal) {
        resultsModal.addEventListener('click', function (event) {
            if (event.target === resultsModal) {
                closeResultsModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeResultsModal();
        }
    });

    const observer = new MutationObserver(function () {

        const percentageText = percentageSpan.innerText.replace('%', '').trim();
        const percentage = parseFloat(percentageText);
        if (isNaN(percentage)) {
            return;
        }

        observer.disconnect();
        hydrateInterface(percentage);
    });

    observer.observe(percentageSpan, { childList: true, characterData: true, subtree: true });

    if (hasFinalScore) {
        hydrateInterface(storedFinalScore);
    }

    let pollTimeout;
    let pollAttempts = 0;
    const MAX_POLL_ATTEMPTS = 30;
    const DEFAULT_RETRY_MS = 5000;

    function stopPolling() {
        if (pollTimeout) {
            clearTimeout(pollTimeout);
            pollTimeout = null;
        }
    }

    function scheduleNextPoll(delayMs) {
        stopPolling();
        pollTimeout = setTimeout(fetchDatosDelIntento, delayMs);
    }

    function fetchDatosDelIntento() {
        if (!ajaxUrl || !ajaxNonce || !currentQuizId || !currentUserId) {
            stopPolling();
            return;
        }

        pollAttempts++;

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_latest_quiz_activity',
                quiz_id: currentQuizId,
                user_id: currentUserId,
                nonce: ajaxNonce,
                baseline_activity_id: phpInitialLatestActivityId
            },
            success: function (response) {
                const data = response && response.data ? response.data : {};
                const retrySeconds = Number(data.retry_after || DEFAULT_RETRY_MS / 1000);
                const retryDelay = Math.max(1, retrySeconds) * 1000;

                if (typeof data.latest_activity_id === 'number') {
                    phpInitialLatestActivityId = Math.max(phpInitialLatestActivityId, Number(data.latest_activity_id));
                }

                if (data.course_id && courseId && Number(data.course_id) !== Number(courseId)) {
                    updateAttemptStatus('⚠️ No pudimos validar el curso asociado al intento.', 'error');
                    stopPolling();
                    return;
                }

                if (response.success && data.status === 'ready') {
                    if (!isFinalQuiz && chartContainerPromedio && typeof data.average_percentage === 'number') {
                        radialChartPromedioInstance = ensureChart(
                            radialChartPromedioInstance,
                            chartContainerPromedio,
                            Math.round(data.average_percentage),
                            'Promedio Polis'
                        );
                    }

                    const latestActivityDetails = data.latest_activity_details;
                    if (latestActivityDetails && latestActivityDetails.score_data) {
                        renderAttemptDetails(latestActivityDetails);

                        if (isFinalQuiz) {
                            hydrateInterface(latestActivityDetails.score_data.percentage);
                        }

                        updateAttemptStatus('✅ Último intento sincronizado correctamente.', 'success');
                        stopPolling();
                    } else if (pollAttempts < MAX_POLL_ATTEMPTS) {
                        updateAttemptStatus('🔍 Esperando que el intento complete su procesamiento…', 'info');
                        scheduleNextPoll(retryDelay);
                    } else {
                        updateAttemptStatus('⚠️ No fue posible obtener los datos completos del intento.', 'warning');
                        stopPolling();
                    }
                } else if (response.success && data.status === 'pending') {
                    if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                        updateAttemptStatus('⚠️ El intento aún no está disponible. Por favor, recarga la página más tarde.', 'warning');
                        stopPolling();
                        return;
                    }

                    const message = data.message ? escapeHtml(String(data.message)) : 'Esperando nuevo intento…';
                    const htmlMessage = `🔍 ${message}<br><small>Intento ${pollAttempts}/${MAX_POLL_ATTEMPTS}</small>`;
                    updateAttemptStatus(htmlMessage, 'info', { allowHtml: true });
                    scheduleNextPoll(retryDelay);
                } else {
                    const message = data && data.message ? escapeHtml(String(data.message)) : 'Error al consultar los intentos.';

                    if (pollAttempts >= MAX_POLL_ATTEMPTS || data.continue_polling === false) {
                        updateAttemptStatus(`⚠️ ${message}`, 'error');
                        stopPolling();
                    } else {
                        updateAttemptStatus(
                            `⚠️ ${message}<br><small>Reintentando ${pollAttempts}/${MAX_POLL_ATTEMPTS}</small>`,
                            'warning',
                            { allowHtml: true }
                        );
                        scheduleNextPoll(retryDelay);
                    }
                }
            },
            error: function () {
                if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                    updateAttemptStatus(
                        `❌ Error de comunicación después de ${MAX_POLL_ATTEMPTS} intentos. Por favor, recarga la página.`,
                        'error'
                    );
                    stopPolling();
                } else {
                    updateAttemptStatus(
                        `⚠️ Error de conexión - Reintentando ${pollAttempts}/${MAX_POLL_ATTEMPTS}...`,
                        'warning'
                    );
                    scheduleNextPoll(DEFAULT_RETRY_MS);
                }
            }
        });
    }

    fetchDatosDelIntento();
});
</script>

<?php
$politeia_modal_path = defined( 'PQC_PLUGIN_DIR' )
    ? PQC_PLUGIN_DIR . 'partials/ajax-results-box.php'
    : dirname( __DIR__, 3 ) . '/partials/ajax-results-box.php';
if ( file_exists( $politeia_modal_path ) ) {
    include $politeia_modal_path;
}
?>

</div>
