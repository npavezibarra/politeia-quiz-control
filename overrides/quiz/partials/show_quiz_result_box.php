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

// --- Quiz Type and Course Association Logic (no change) ---
$analytics = class_exists( 'Politeia_Quiz_Analytics' )
    ? new Politeia_Quiz_Analytics( (int) $quiz_id )
    : null;

$course_id      = $analytics ? $analytics->getCourseId() : 0;
$is_first_quiz  = $analytics ? $analytics->isFirstQuiz() : false;
$is_final_quiz  = $analytics ? $analytics->isFinalQuiz() : false;
$first_quiz_id  = $analytics ? $analytics->getFirstQuizId() : 0;
$final_quiz_id  = $analytics ? $analytics->getFinalQuizId() : 0;

if ( ! $analytics ) {
    // Fallback legacy detection if helper is unavailable.
    $course_id_from_first_quiz = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_first_quiz_id' AND meta_value = %d",
        $quiz_id
    ) );
    if ( ! empty( $course_id_from_first_quiz ) ) {
        $course_id     = $course_id_from_first_quiz;
        $is_first_quiz = true;
        $first_quiz_id = (int) get_post_meta( $course_id, '_first_quiz_id', true );
    }

    $course_id_from_final_quiz = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_final_quiz_id' AND meta_value = %d",
        $quiz_id
    ) );
    if ( ! empty( $course_id_from_final_quiz ) ) {
        $course_id     = $course_id_from_final_quiz;
        $is_final_quiz = true;
        $final_quiz_id = (int) get_post_meta( $course_id, '_final_quiz_id', true );
    }
}

// Buscar producto relacionado usando meta_value serializado (no change)
$related_product_id = null;
if ( $course_id ) {
    $like               = '%i:0;i:' . (int) $course_id . ';%';
    $related_product_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_related_course' 
         AND meta_value LIKE %s",
        $like
    ) );
}

// Verificar si el usuario compró ese producto (no change)
$has_bought   = false;
$order_number = null;
$order_status = null;

if ( $current_user_id && $related_product_id ) {
    $orders = wc_get_orders( array(
        'customer_id' => $current_user_id,
        'status'      => array( 'completed', 'processing', 'on-hold', 'course-on-hold' ),
        'limit'       => -1,
        'return'      => 'ids',
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

// Obtener el puntaje del First Quiz si estamos en Final Quiz (no change)
$first_quiz_score = 0;
if ( $is_final_quiz && $course_id && $first_quiz_id ) {
    if ( $current_user_id ) {
        if ( class_exists( 'Politeia_Quiz_Stats' ) ) {
            $latest_id = Politeia_Quiz_Stats::get_latest_attempt_id( $current_user_id, $first_quiz_id );
            if ( $latest_id ) {
                $data = Politeia_Quiz_Stats::get_score_and_pct_by_activity( $latest_id );
                if ( $data && isset( $data->percentage ) ) {
                    $first_quiz_score = round( floatval( $data->percentage ) );
                }
            }
        } else {
            error_log( 'Politeia_Quiz_Stats class not found when trying to get first quiz score.' );
        }
    }
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

    <div id="datos-del-intento-container">
        <p style="text-align:center; color:#555;">
            Buscando el registro del último intento (mayor a
            <?php echo esc_html( $php_rendered_latest_global_activity_id ? $php_rendered_latest_global_activity_id : '0' ); ?>)…
        </p>
    </div>

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

    <?php
    echo '<div style="margin-top:20px;">';

    if ( $is_first_quiz && $course_id ) {
        if ( $related_product_id && ! $has_bought ) {
            $product_link = get_permalink( $related_product_id );
            echo '<a href="' . esc_url( $product_link ) . '" class="button"'
               . ' style="background:black;color:white;padding:10px 20px;text-decoration:none;">'
               . esc_html__( 'Buy Course', 'text-domain' )
               . '</a>';
        } else {
            $course_link = get_permalink( $course_id );
            echo '<a href="' . esc_url( $course_link ) . '" class="button"'
               . ' style="background:black;color:white;padding:10px 20px;text-decoration:none;">'
               . esc_html__( 'Go to Course', 'text-domain' )
               . '</a>';
        }
    } elseif ( $is_final_quiz ) {
        echo '<a href="' . esc_url( home_url( '/courses/' ) ) . '" class="button"'
           . ' style="background:black;color:white;padding:10px 20px;text-decoration:none;">'
           . esc_html__( 'More Courses', 'text-domain' )
           . '</a>';
    }

    echo '</div>';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const target = document.querySelector(".wpProQuiz_points.wpProQuiz_points--message");
    if (!target) return;

    const span = target.querySelectorAll("span")[2];
    if (!span) return;

    const chartContainer         = document.querySelector("#radial-chart");
    const chartContainerPromedio = document.querySelector("#radial-chart-promedio");
    const datosDelIntentoContainer = document.getElementById("datos-del-intento-container");

    const isFinalQuiz = <?php echo $is_final_quiz ? 'true' : 'false'; ?>;
    const firstQuizScore = <?php echo $first_quiz_score; ?>;
    const polisAverageInitialPHP = <?php echo $polis_average; ?>; 
    const currentQuizId = <?php echo $quiz_id; ?>;
    const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    const phpInitialLatestActivityId = <?php echo esc_html( $php_rendered_latest_global_activity_id ? $php_rendered_latest_global_activity_id : '0' ); ?>; 

    let FinalScore = 0;
    let FirstScore = firstQuizScore;

    const observer = new MutationObserver(function () {
        const percentageText = span.innerText.replace('%', '').trim();
        const percentage = parseFloat(percentageText);
        if (isNaN(percentage)) return;

        FinalScore = percentage;
        observer.disconnect();

        const options = (value, labelText, colorStart, colorEnd) => ({
            series: [value],
            chart: { height: 400, type: 'radialBar' },
            plotOptions: {
                radialBar: {
                    hollow: { size: '60%' },
                    dataLabels: {
                        name: { show: true, offsetY: -10, color: '#666', fontSize: '16px' },
                        value: { show: true, fontSize: '32px', fontWeight: 600, color: '#111', offsetY: 8, formatter: val => val + '%' }
                    }
                }
            },
            labels: [labelText],
            colors: [colorStart],
            fill: {
                type: 'gradient',
                gradient: { shade: 'light', type: 'diagonal', gradientToColors: [colorEnd], stops: [0, 100], opacityFrom: 1, opacityTo: 1, angle: 145 }
            }
        });

        // Render "Tu Puntaje"
        if (chartContainer) {
            new ApexCharts(chartContainer, options(FinalScore, 'Tu Puntaje', '#d29d01', '#ffd000')).render();
        }

        // Render "Promedio Polis" o "First Score"
        if (chartContainerPromedio) {
            if (isFinalQuiz) {
                new ApexCharts(chartContainerPromedio, options(FirstScore, 'First Score', '#d29d01', '#ffd000')).render();
            } else {
                new ApexCharts(chartContainerPromedio, options(polisAverageInitialPHP, 'Promedio Polis', '#d29d01', '#ffd000')).render();
            }
        }

        // Si es Final Quiz, mostrar mensaje de progreso
        const scoreDiv = document.getElementById("score");
        if (scoreDiv && isFinalQuiz) {
            const progreso = FinalScore - FirstScore;
            let mensajeHTML = '';

            if (progreso > 0) {
                mensajeHTML = `
                    <h2 style="text-align: center; color: #000;">Congratulations on your progress!</h2>
                    <p style="font-weight: normal; color: #333; text-align: center;">
                        You improved your score by <strong style="color: #4CAF50;">+${progreso} points</strong>. Great job!
                    </p>
                `;
            } else if (progreso === 0) {
                mensajeHTML = `
                    <h2 style="text-align: center; color: #000;">Congratulations on completing the course!</h2>
                    <p style="font-weight: normal; color: #333; text-align: center;">
                        Your knowledge has been reinforced. Your progress was <strong>${progreso} points</strong>.
                    </p>
                `;
            } else {
                mensajeHTML = `
                    <h2 style="text-align: center; color: #000;">Keep going – you're not alone!</h2>
                    <p style="font-weight: normal; color: #333; text-align: center;">
                        Your score changed by <strong style="color: #D32F2F;">${progreso} points</strong>.
                        This is uncommon, but nothing to worry about. You can review the lessons and take the Final Quiz again in 10 days.
                    </p>
                `;
            }

            scoreDiv.innerHTML = mensajeHTML;
        }

        // --- JavaScript Polling for Datos del Intento (MODIFIED) ---
        let pollInterval;
        let currentChartPromedioInstance;
        let pollAttempts = 0;
        const MAX_POLL_ATTEMPTS = 30; // Increased since we're waiting for a specific condition

        const fetchDatosDelIntento = () => {
            pollAttempts++;

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_latest_quiz_activity',
                    quiz_id: currentQuizId,
                    baseline_activity_id: phpInitialLatestActivityId  // Pass the baseline ID
                },
                success: function(response) {
                    let shouldStopPolling = false;

                    if (response.success && response.data) {
                        const latestActivityDetails = response.data.latest_activity_details;
                        const allAttemptsPercentagesFromAjax = response.data.all_attempts_percentages;
                        const newAttemptFound = response.data.new_attempt_found;

                        // Solo en First Quiz se actualiza Promedio Polis con AJAX
                        if (!isFinalQuiz && chartContainerPromedio && allAttemptsPercentagesFromAjax && allAttemptsPercentagesFromAjax.length > 0) {
                            let totalSum = 0;
                            let totalCount = 0;
                            allAttemptsPercentagesFromAjax.forEach(attempt => {
                                totalSum += attempt.percentage;
                                totalCount++;
                            });

                            let newPolisAverage = 0;
                            if (totalCount > 0) {
                                newPolisAverage = Math.round(totalSum / totalCount);
                            }

                            if (!currentChartPromedioInstance) {
                                currentChartPromedioInstance = new ApexCharts(chartContainerPromedio, options(newPolisAverage, 'Promedio Polis', '#d29d01', '#ffd000'));
                                currentChartPromedioInstance.render();
                            } else {
                                currentChartPromedioInstance.updateOptions(options(newPolisAverage, 'Promedio Polis', '#d29d01', '#ffd000'));
                            }
                        }

                        // SUCCESS: We found the new attempt with complete data
                        if (newAttemptFound && latestActivityDetails && latestActivityDetails.score_data) {
                            const scoreData = latestActivityDetails.score_data;
                            const html = `
                                <div style="margin: 30px auto; max-width: 600px; padding: 20px; border: 2px solid #4CAF50; font-size: 15px; background-color: #f0f8f0; display:none;">
                                    <h4 style="margin-bottom: 10px; color: #4CAF50;">✅ Datos del Intento (NUEVO INTENTO ENCONTRADO)</h4>
                                    <p><strong>Activity ID:</strong> ${latestActivityDetails.activity_id} <em>(Mayor que baseline: ${phpInitialLatestActivityId})</em></p>
                                    <p><strong>Inicio:</strong> ${latestActivityDetails.started}</p>
                                    <p><strong>Término:</strong> ${latestActivityDetails.completed}</p>
                                    <p><strong>Duración:</strong> ${latestActivityDetails.duration} segundos</p>
                                    <hr style="border-top: 1px solid #4CAF50; margin: 15px 0;">
                                    <p><strong>Puntaje:</strong> ${scoreData.score} de ${scoreData.total_points}</p>
                                    <p><strong>Porcentaje:</strong> ${scoreData.percentage}%</p>
                                    <p><strong>Estado:</strong> ${scoreData.passed ? 'Aprobado' : 'Reprobado'}</p>
                                </div>
                            `;
                            datosDelIntentoContainer.innerHTML = html;
                            shouldStopPolling = true;
                        } else if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                            datosDelIntentoContainer.innerHTML = `
                                <p style="color:orange; font-weight:bold; text-align:center;">
                                    ⚠️ No se encontró un nuevo intento después de ${MAX_POLL_ATTEMPTS} intentos.<br>
                                    <small>Esperaba Activity ID mayor que ${phpInitialLatestActivityId}</small><br>
                                    Por favor, recarga la página si completaste el quiz.
                                </p>
                            `;
                            shouldStopPolling = true;
                        } else {
                            // Still polling - show progress
                            let loadingMessage = `🔍 Esperando nuevo intento (mayor que ID: ${phpInitialLatestActivityId}) - Intento ${pollAttempts}/${MAX_POLL_ATTEMPTS}`;
                            if (allAttemptsPercentagesFromAjax && allAttemptsPercentagesFromAjax.length > 0) {
                                loadingMessage += ` | Promedio Polis actualizado ✓`;
                            }
                            datosDelIntentoContainer.innerHTML = `<p style="text-align:center; color:#555;">${loadingMessage}</p>`;
                        }
                    } else {
                        // Error response - but check if we should continue polling
                        const errorData = response.data || {};
                        const continuePolling = errorData.continue_polling;
                        
                        if (continuePolling && pollAttempts < MAX_POLL_ATTEMPTS) {
                            const message = errorData.message || 'Esperando nuevo intento...';
                            datosDelIntentoContainer.innerHTML = `
                                <p style="text-align:center; color:#666;">
                                    🔍 ${message}<br>
                                    <small>Buscando Activity ID mayor que ${phpInitialLatestActivityId} - Intento ${pollAttempts}/${MAX_POLL_ATTEMPTS}</small>
                                </p>
                            `;
                        } else {
                            if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                                datosDelIntentoContainer.innerHTML = `<p style="color:orange; font-weight:bold; text-align:center;">⚠️ Fallo al cargar los detalles del intento después de varios intentos. Por favor, recarga la página o inténtalo más tarde.</p>`;
                                shouldStopPolling = true;
                            } else {
                                datosDelIntentoContainer.innerHTML = `<p style="color:red; font-weight:bold; text-align:center;">⚠️ Error al cargar datos del intento. Reintento ${pollAttempts}/${MAX_POLL_ATTEMPTS}...</p>`;
                            }
                        }
                    }

                    if (shouldStopPolling) {
                        clearInterval(pollInterval);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    
                    if (pollAttempts >= MAX_POLL_ATTEMPTS) {
                        datosDelIntentoContainer.innerHTML = `
                            <p style="color:red; font-weight:bold; text-align:center;">
                                ❌ Error de comunicación después de ${MAX_POLL_ATTEMPTS} intentos.<br>
                                Por favor, recarga la página.
                            </p>
                        `;
                        clearInterval(pollInterval);
                    } else {
                        datosDelIntentoContainer.innerHTML = `
                            <p style="color:orange; text-align:center;">
                                ⚠️ Error de conexión - Reintentando ${pollAttempts}/${MAX_POLL_ATTEMPTS}...
                            </p>
                        `;
                    }
                }
            });
        };

        pollInterval = setInterval(fetchDatosDelIntento, 1000);
        fetchDatosDelIntento();
    });

    observer.observe(span, { childList: true, characterData: true, subtree: true });
});
</script>


</div>