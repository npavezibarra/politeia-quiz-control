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

$course         = $course_id ? new PoliteiaCourse( $course_id ) : null;
$first_quiz_id  = $analytics && $analytics->getFirstQuizId()
    ? $analytics->getFirstQuizId()
    : ( $course_id ? PoliteiaCourse::getFirstQuizId( $course_id ) : 0 );
$final_quiz_id  = $analytics && $analytics->getFinalQuizId()
    ? $analytics->getFinalQuizId()
    : ( $course_id ? PoliteiaCourse::getFinalQuizId( $course_id ) : 0 );
$is_first_quiz  = $first_quiz_id && (int) $first_quiz_id === (int) $quiz_id;
$is_final_quiz  = $final_quiz_id && (int) $final_quiz_id === (int) $quiz_id;

$course_title         = $course ? $course->getTitle() : '';
$course_url           = $course_id ? get_permalink( $course_id ) : '';
$courses_listing_url  = home_url( '/courses/' );
$related_product_id   = $course ? $course->getRelatedProductId() : 0;
$product_url          = $related_product_id ? get_permalink( $related_product_id ) : '';
$user_has_course_access = $course && $current_user_id
    ? $course->isUserEnrolled( $current_user_id )
    : false;
$has_bought = false;

if ( $current_user_id && $related_product_id && function_exists( 'wc_customer_bought_product' ) ) {
    $has_bought = wc_customer_bought_product( '', $current_user_id, $related_product_id );
}

$first_attempt_summary = null;
$final_attempt_summary = null;
$first_quiz_score      = 0;
$final_quiz_score      = 0;

if ( $current_user_id && class_exists( 'Politeia_Quiz_Stats' ) ) {
    if ( $first_quiz_id ) {
        $first_attempt_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $current_user_id, $first_quiz_id );
        if ( $first_attempt_summary ) {
            $first_quiz_score = (int) $first_attempt_summary['percentage'];
        }
    }

    if ( $is_final_quiz ) {
        $final_attempt_summary = Politeia_Quiz_Stats::get_latest_attempt_summary( $current_user_id, $quiz_id );
        if ( $final_attempt_summary ) {
            $final_quiz_score = (int) $final_attempt_summary['percentage'];
        }
    }
}

$progress_delta         = null;
$days_between_attempts  = null;
if ( $is_final_quiz && $first_attempt_summary && $final_attempt_summary ) {
    $progress_delta = (int) $final_attempt_summary['percentage'] - (int) $first_attempt_summary['percentage'];

    if ( ! empty( $first_attempt_summary['completed_timestamp'] ) && ! empty( $final_attempt_summary['completed_timestamp'] ) ) {
        $seconds_between       = max( 0, (int) $final_attempt_summary['completed_timestamp'] - (int) $first_attempt_summary['completed_timestamp'] );
        $days_between_attempts = (int) floor( $seconds_between / DAY_IN_SECONDS );
    }
}

$ajax_nonce = wp_create_nonce( 'politeia_quiz_stats' );

$quiz_stats_bootstrap = array(
    'quizId'               => (int) $quiz_id,
    'userId'               => (int) $current_user_id,
    'isFinalQuiz'          => (bool) $is_final_quiz,
    'hasFirstQuiz'         => (bool) $first_quiz_id,
    'firstQuizScore'       => (int) $first_quiz_score,
    'finalQuizStoredScore' => (int) $final_quiz_score,
    'hasFinalScore'        => (bool) $final_attempt_summary,
    'progressDelta'        => null === $progress_delta ? null : (int) $progress_delta,
    'daysBetweenAttempts'  => null === $days_between_attempts ? null : (int) $days_between_attempts,
    'firstQuizCompleted'   => $first_attempt_summary['completed'] ?? '',
    'finalQuizCompleted'   => $final_attempt_summary['completed'] ?? '',
    'ajaxUrl'              => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
    'nonce'                => $ajax_nonce,
    'courseUrl'            => $course_url ? esc_url_raw( $course_url ) : '',
    'productUrl'           => $product_url ? esc_url_raw( $product_url ) : '',
    'coursesListingUrl'    => esc_url_raw( $courses_listing_url ),
    'courseTitle'          => $course_title ? wp_strip_all_tags( $course_title ) : '',
    'hasCourseAccess'      => (bool) $user_has_course_access,
    'hasPurchased'         => (bool) $has_bought,
);

$quiz_stats_bootstrap_json = wp_json_encode( $quiz_stats_bootstrap );
$cta_data_context         = $is_final_quiz ? 'final' : 'first';
$cta_course_url_attr      = $course_url ? esc_url( $course_url ) : '';
$cta_product_url_attr     = $product_url ? esc_url( $product_url ) : '';
$cta_courses_listing_attr = esc_url( $courses_listing_url );
$cta_course_title_attr    = $course_title ? esc_attr( $course_title ) : '';

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

    <div id="quiz-comparison-panel" class="politeia-quiz-comparison" style="display:none;"></div>

    <div id="quiz-encouragement" class="politeia-quiz-encouragement" style="display:none;"></div>

    <div
        id="quiz-cta-container"
        class="politeia-quiz-cta"
        data-context="<?php echo esc_attr( $cta_data_context ); ?>"
        data-course-url="<?php echo esc_attr( $cta_course_url_attr ); ?>"
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
    if (!pointsMessage) {
        return;
    }

    const resultSpans = pointsMessage.querySelectorAll('span');
    if (!resultSpans.length) {
        return;
    }

    const percentageSpan = resultSpans[2];
    if (!percentageSpan) {
        return;
    }

    const chartContainer = document.querySelector('#radial-chart');
    const chartContainerPromedio = document.querySelector('#radial-chart-promedio');
    const datosDelIntentoContainer = document.getElementById('datos-del-intento-container');
    const datosDelIntentoStatus = document.getElementById('datos-del-intento-status');
    const comparisonPanel = document.getElementById('quiz-comparison-panel');
    const encouragementContainer = document.getElementById('quiz-encouragement');
    const ctaContainer = document.getElementById('quiz-cta-container');
    const scoreDiv = document.getElementById('score');

    const quizStatsBootstrap = <?php echo $quiz_stats_bootstrap_json ? $quiz_stats_bootstrap_json : '{}'; ?> || {};
    const isFinalQuiz = !!quizStatsBootstrap.isFinalQuiz;
    const hasFirstQuiz = !!quizStatsBootstrap.hasFirstQuiz;
    const firstQuizScore = Number(quizStatsBootstrap.firstQuizScore || 0);
    const storedFinalScore = Number(quizStatsBootstrap.finalQuizStoredScore || 0);
    const hasFinalScore = !!quizStatsBootstrap.hasFinalScore;
    const polisAverageInitialPHP = <?php echo (int) $polis_average; ?>;
    const ajaxUrl = quizStatsBootstrap.ajaxUrl || '';
    const currentQuizId = Number(quizStatsBootstrap.quizId || <?php echo (int) $quiz_id; ?>);
    const currentUserId = Number(quizStatsBootstrap.userId || 0);
    const ajaxNonce = quizStatsBootstrap.nonce || '';
    const firstQuizCompleted = quizStatsBootstrap.firstQuizCompleted || '';
    const finalQuizCompleted = quizStatsBootstrap.finalQuizCompleted || '';
    const baseDaysBetween = typeof quizStatsBootstrap.daysBetweenAttempts === 'number'
        ? quizStatsBootstrap.daysBetweenAttempts
        : null;

    const phpInitialLatestActivityId = <?php echo $php_rendered_latest_global_activity_id ? (int) $php_rendered_latest_global_activity_id : 0; ?>;

    let FinalScore = storedFinalScore;
    let FirstScore = firstQuizScore;

    let radialChartInstance = null;
    let radialChartPromedioInstance = null;

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

    function updateScoreContent(finalScore) {
        if (!scoreDiv) {
            return;
        }

        if (isFinalQuiz) {
            const delta = hasFirstQuiz ? Math.round(finalScore - FirstScore) : null;
            let heading = '¡Felicitaciones por completar el curso!';
            let paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>.`;

            if (hasFirstQuiz && delta !== null) {
                if (delta > 0) {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong> y mejoraste ${formatDelta(delta)} puntos desde tu primer intento.`;
                } else if (delta === 0) {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>. Mantienes un desempeño constante entre intentos.`;
                } else {
                    paragraph = `Tu puntaje final fue <strong>${Math.round(finalScore)}%</strong>. Detectamos una variación de ${formatDelta(delta)} puntos; repasar las lecciones finales puede ayudarte a reforzar lo aprendido.`;
                }
            }

            scoreDiv.innerHTML = `
                <h2 style="text-align:center;color:#000;">${heading}</h2>
                <p style="text-align:center;color:#333;font-weight:normal;">${paragraph}</p>
            `;
        } else {
            scoreDiv.innerHTML = `
                <h2 style="text-align:center;color:#000;">Tu puntaje: ${Math.round(finalScore)}%</h2>
                <p style="text-align:center;color:#555;font-weight:normal;">Tu resultado quedó registrado. ¡Sigue aprendiendo!</p>
            `;
        }
    }

    function renderEncouragement(finalScore) {
        if (!encouragementContainer) {
            return;
        }

        let message = '';
        if (isFinalQuiz) {
            if (hasFirstQuiz) {
                const delta = Math.round(finalScore - FirstScore);
                if (delta > 0) {
                    message = `Mejoraste ${formatDelta(delta)} puntos desde tu primer intento. Celebra el avance y comparte tu logro.`;
                } else if (delta === 0) {
                    message = 'Mantienes un desempeño constante. Revisa los materiales complementarios para seguir creciendo.';
                } else {
                    message = `Hubo una variación de ${formatDelta(delta)} puntos. Repasa las lecciones clave para reforzar tu conocimiento.`;
                }
            } else {
                message = '¡Excelente cierre! Aprovecha el contenido complementario del curso para seguir profundizando.';
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
        const productUrl = ctaContainer.dataset.productUrl || '';
        const listingUrl = ctaContainer.dataset.coursesListingUrl || '';
        const hasAccess = ctaContainer.dataset.hasAccess === '1';
        const hasPurchased = ctaContainer.dataset.hasPurchased === '1';
        const title = ctaContainer.dataset.courseTitle || '';

        let label = '';
        let href = '';
        let helper = '';

        if (context === 'final') {
            href = courseUrl || listingUrl || '#';
            if (courseUrl && hasAccess) {
                label = 'Volver al curso';
                helper = 'Revisa los recursos finales y comparte tu certificado.';
            } else {
                label = 'Explorar más cursos';
                helper = 'Continúa tu camino de aprendizaje con nuevas rutas recomendadas.';
            }
        } else {
            if (hasAccess || hasPurchased || !productUrl) {
                href = courseUrl || listingUrl || '#';
                label = courseUrl ? 'Ir al curso' : 'Explorar cursos';
                helper = title ? `Ingresa a ${title} y continúa tu avance.` : 'Ingresa al curso para continuar tu progreso.';
            } else {
                href = productUrl;
                label = 'Comprar el curso';
                if (finalScore >= 80) {
                    helper = 'Aprovecha tu impulso y desbloquea todas las lecciones y evaluaciones.';
                } else if (finalScore >= 60) {
                    helper = 'Refuerza los temas clave con acceso completo al contenido del curso.';
                } else {
                    helper = 'Obtén el curso completo y avanza con acompañamiento paso a paso.';
                }
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

        if (!isFinalQuiz || !hasFirstQuiz) {
            comparisonPanel.innerHTML = '';
            comparisonPanel.style.display = 'none';
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
        updateScoreContent(FinalScore);
        renderEncouragement(FinalScore);
        renderCTA(FinalScore);
        renderComparison(FinalScore);

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

    function updateAttemptStatus(message, tone = 'info') {
        if (!datosDelIntentoStatus) {
            return;
        }
        const colors = {
            info: '#555',
            warning: '#D57A00',
            error: '#D32F2F',
            success: '#2E7D32'
        };
        datosDelIntentoStatus.innerHTML = `<p style="color:${colors[tone] || colors.info};">${message}</p>`;
        datosDelIntentoStatus.style.display = 'block';
    }

    function renderAttemptDetails(attemptData) {
        if (!datosDelIntentoContainer) {
            return;
        }

        datosDelIntentoContainer.innerHTML = `
            <h4 style="margin-bottom:12px;color:#2E7D32;">✅ Datos del Intento</h4>
            <p><strong>Activity ID:</strong> ${attemptData.activity_id}</p>
            <p><strong>Inicio:</strong> ${attemptData.started}</p>
            <p><strong>Término:</strong> ${attemptData.completed}</p>
            <p><strong>Duración:</strong> ${attemptData.duration} segundos</p>
            <hr style="margin:16px 0;border-top:1px solid #c8e6c9;" />
            <p><strong>Puntaje:</strong> ${attemptData.score_data.score} de ${attemptData.score_data.total_points}</p>
            <p><strong>Porcentaje:</strong> ${attemptData.score_data.percentage}%</p>
            <p><strong>Estado:</strong> ${attemptData.score_data.passed ? 'Aprobado' : 'Reprobado'}</p>
        `;
        datosDelIntentoContainer.style.display = 'block';
        if (datosDelIntentoStatus) {
            datosDelIntentoStatus.style.display = 'none';
        }
    }

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

    let pollInterval;
    let pollAttempts = 0;
    const MAX_POLL_ATTEMPTS = 30;

    function fetchDatosDelIntento() {
        pollAttempts++;

        if (!ajaxUrl) {
            clearInterval(pollInterval);
            return;
        }

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_latest_quiz_activity',
                quiz_id: currentQuizId,
                user_id: currentUserId,
                nonce: ajaxNonce,
                baseline_activity_id: phpInitialLatestActivityId
            },
            success: function (response) {
                let shouldStopPolling = false;

                if (response.success && response.data) {
                    const latestActivityDetails = response.data.latest_activity_details;
                    const allAttemptsPercentagesFromAjax = response.data.all_attempts_percentages;
                    const newAttemptFound = response.data.new_attempt_found;

                    if (!isFinalQuiz && chartContainerPromedio && Array.isArray(allAttemptsPercentagesFromAjax) && allAttemptsPercentagesFromAjax.length > 0) {
                        const totalSum = allAttemptsPercentagesFromAjax.reduce((acc, attempt) => acc + attempt.percentage, 0);
                        const newAverage = Math.round(totalSum / allAttemptsPercentagesFromAjax.length);
                        radialChartPromedioInstance = ensureChart(
                            radialChartPromedioInstance,
                            chartContainerPromedio,
                            newAverage,
                            'Promedio Polis'
                        );
                    }

                    if (newAttemptFound && latestActivityDetails && latestActivityDetails.score_data) {
                        renderAttemptDetails(latestActivityDetails);

                        if (isFinalQuiz) {
                            hydrateInterface(latestActivityDetails.score_data.percentage);
                        }

                        shouldStopPolling = true;
                    } else if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                        updateAttemptStatus(
                            `⚠️ No se encontró un nuevo intento después de ${MAX_POLL_ATTEMPTS} comprobaciones.`,
                            'warning'
                        );
                        shouldStopPolling = true;
                    } else {
                        updateAttemptStatus(
                            `🔍 Esperando nuevo intento (mayor que ID: ${phpInitialLatestActivityId}) - Intento ${pollAttempts}/${MAX_POLL_ATTEMPTS}`,
                            'info'
                        );
                    }
                } else {
                    const errorData = response.data || {};
                    const continuePolling = errorData.continue_polling;

                    if (continuePolling && pollAttempts < MAX_POLL_ATTEMPTS) {
                        const message = errorData.message || 'Esperando nuevo intento...';
                        updateAttemptStatus(
                            `🔍 ${message}<br><small>Buscando Activity ID mayor que ${phpInitialLatestActivityId} - Intento ${pollAttempts}/${MAX_POLL_ATTEMPTS}</small>`,
                            'info'
                        );
                    } else if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                        updateAttemptStatus(
                            '⚠️ Fallo al cargar los detalles del intento después de varios intentos. Por favor, recarga la página o inténtalo más tarde.',
                            'warning'
                        );
                        shouldStopPolling = true;
                    } else {
                        updateAttemptStatus(
                            `⚠️ Error al cargar datos del intento. Reintento ${pollAttempts}/${MAX_POLL_ATTEMPTS}...`,
                            'error'
                        );
                    }
                }

                if (shouldStopPolling) {
                    clearInterval(pollInterval);
                }
            },
            error: function () {
                if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                    updateAttemptStatus(
                        `❌ Error de comunicación después de ${MAX_POLL_ATTEMPTS} intentos. Por favor, recarga la página.`,
                        'error'
                    );
                    clearInterval(pollInterval);
                } else {
                    updateAttemptStatus(
                        `⚠️ Error de conexión - Reintentando ${pollAttempts}/${MAX_POLL_ATTEMPTS}...`,
                        'warning'
                    );
                }
            }
        });
    }

    pollInterval = setInterval(fetchDatosDelIntento, 1000);
    fetchDatosDelIntento();
});
</script>


</div>
