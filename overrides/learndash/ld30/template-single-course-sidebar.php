<?php
/**
 * The template for displaying course sidebar
 *
 * @package BuddyBossTheme
 * @since   BuddyBossTheme 1.0.0
 */

// =============================================================================
// 1. INICIALIZACIÓN Y RECOLECCIÓN DE DATOS
// =============================================================================
// Descripción: Esta sección se encarga de obtener toda la información
// necesaria sobre el curso, el usuario actual, el progreso, los precios y
// otros metadatos que se utilizarán a lo largo del template para mostrar
// el contenido dinámicamente.
// =============================================================================

global $wpdb;
$is_enrolled = false;
$current_user_id = get_current_user_id();
$course_id = get_the_ID(); // Ensure course_id is set for the current course
$course_price = learndash_get_course_meta_setting($course_id, 'course_price');
$course_price_type = learndash_get_course_meta_setting($course_id, 'course_price_type');
$course_button_url = learndash_get_course_meta_setting($course_id, 'custom_button_url');
$paypal_settings = LearnDash_Settings_Section::get_section_settings_all('LearnDash_Settings_Section_PayPal');
$course_video_embed = get_post_meta($course_id, '_buddyboss_lms_course_video', true);
$course_certificate = learndash_get_course_meta_setting($course_id, 'certificate');
$courses_progress = buddyboss_theme()->learndash_helper()->get_courses_progress($current_user_id);
$course_progress = isset($courses_progress[$course_id]) ? $courses_progress[$course_id] : 0;
$course_progress_new = buddyboss_theme()->learndash_helper()->ld_get_progress_course_percentage(get_current_user_id(), $course_id);
$admin_enrolled = LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Section_General_Admin_User', 'courses_autoenroll_admin_users');
$lesson_count = learndash_get_course_lessons_list($course_id, null, array('num' => -1));
$lesson_count = array_column($lesson_count, 'post');
$course_pricing = learndash_get_course_price($course_id); // Correctly get pricing info
$has_access = sfwd_lms_has_access($course_id, $current_user_id);
$file_info = pathinfo($course_video_embed);

if (buddyboss_theme_get_option('learndash_course_participants', null, true)) {
    $course_members_count = buddyboss_theme()->learndash_helper()->buddyboss_theme_ld_course_enrolled_users_list($course_id);
    $members_arr = learndash_get_users_for_course($course_id, array('number' => 5), false);

    if (($members_arr instanceof WP_User_Query) && (property_exists($members_arr, 'results')) && (!empty($members_arr->results))) {
        $course_members = $members_arr->get_results();
    } else {
        $course_members = array();
    }
}

if ('' !== trim($course_video_embed)) {
    $thumb_mode = 'thumbnail-container-vid';
} else {
    $thumb_mode = 'thumbnail-container-img';
}

// Ensure $course is available for learndash_payment_buttons()
$course = get_post($course_id); // Get the WP_Post object for the course

if (sfwd_lms_has_access($course->ID, $current_user_id)) {
    $is_enrolled = true;
} else {
    $is_enrolled = false;
}

$ld_product = null;
if (class_exists('LearnDash\Core\Models\Product') && isset($course_id)) {
    $ld_product = LearnDash\Core\Models\Product::find((int) $course_id);
}

$progress = learndash_course_progress(
    array(
        'user_id' => $current_user_id,
        'course_id' => $course_id,
        'array' => true,
    )
);

if (empty($progress)) {
    $progress = array(
        'percentage' => 0,
        'completed' => 0,
        'total' => 0,
    );
}
$progress_status = (100 == $progress['percentage']) ? 'completed' : 'notcompleted';
if (0 < $progress['percentage'] && 100 !== $progress['percentage']) {
    $progress_status = 'progress';
}
?>

<?php
// =============================================================================
// 2. ESTRUCTURA HTML PRINCIPAL DE LA BARRA LATERAL (SIDEBAR)
// =============================================================================
// Descripción: Aquí comienza la estructura HTML del sidebar. Incluye el
// contenedor principal y el widget de inscripción, que alojará la vista previa
// del curso (imagen o video) y los botones de acción.
// =============================================================================
?>
<?php
// SVG icons for buttons
$icon_quiz = '<svg style="width:20px;height:20px;margin-right:8px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>';
$icon_cart = '<svg style="width:20px;height:20px;margin-right:8px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>';
?>
<style>
    /* ── Shared base for ALL sidebar buttons ── */
    .bb-single-course-sidebar .btn-advance,
    .bb-single-course-sidebar a.btn-advance,
    .bb-single-course-sidebar a.btn-advance.ld-primary-background,
    .bb-single-course-sidebar .btn-join,
    .bb-single-course-sidebar a.btn-join,
    .bb-single-course-sidebar #btn-join,
    .bb-single-course-sidebar #learndash-course-enroll-button,
    body .bb-single-course-sidebar .btn-advance,
    body .bb-single-course-sidebar a.btn-advance.ld-primary-background {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        padding: 12px !important;
        font-weight: 600 !important;
        font-size: 16px !important;
        cursor: pointer !important;
        border: none !important;
        border-top: none !important;
        border-right: none !important;
        border-bottom: none !important;
        border-left: none !important;
        border-width: 0 !important;
        outline: none !important;
        text-decoration: none !important;
        border-radius: 6px !important;
        transition: all 0.2s ease !important;
        box-sizing: border-box !important;
    }

    .bb-single-course-sidebar .btn-advance:active,
    .bb-single-course-sidebar a.btn-advance:active,
    .bb-single-course-sidebar .btn-join:active,
    .bb-single-course-sidebar a.btn-join:active {
        transform: scale(0.95) !important;
    }

    /* ── "Take First Quiz" — gold gradient ── */
    .bb-single-course-sidebar .btn-advance,
    .bb-single-course-sidebar a.btn-advance,
    .bb-single-course-sidebar a.btn-advance.ld-primary-background,
    body .bb-single-course-sidebar .btn-advance,
    body .bb-single-course-sidebar a.btn-advance.ld-primary-background {
        background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A) !important;
        background-color: transparent !important;
        color: #ffffff !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    }

    .bb-single-course-sidebar .btn-advance:hover,
    .bb-single-course-sidebar a.btn-advance:hover,
    .bb-single-course-sidebar a.btn-advance.ld-primary-background:hover {
        opacity: 0.95 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        color: #ffffff !important;
    }

    /* ── "Comprar Curso" — black bg, white text, NO border ── */
    .bb-single-course-sidebar .btn-join,
    .bb-single-course-sidebar a.btn-join,
    .bb-single-course-sidebar #btn-join,
    body .bb-single-course-sidebar .btn-join,
    body .bb-single-course-sidebar a.btn-join,
    .bb-single-course-sidebar .btn-join:hover,
    .bb-single-course-sidebar a.btn-join:hover,
    .bb-single-course-sidebar #btn-join:hover,
    .bb-single-course-sidebar .btn-join:focus,
    .bb-single-course-sidebar .btn-join:active,
    .bb-single-course-sidebar .btn-join:visited {
        background: #000000 !important;
        background-color: #000000 !important;
        color: #ffffff !important;
        border: 0 none transparent !important;
        border-top: 0 none transparent !important;
        border-right: 0 none transparent !important;
        border-bottom: 0 none transparent !important;
        border-left: 0 none transparent !important;
        border-width: 0 !important;
        border-style: none !important;
        border-color: transparent !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .bb-single-course-sidebar .btn-join:hover,
    .bb-single-course-sidebar a.btn-join:hover,
    .bb-single-course-sidebar #btn-join:hover {
        background: #222222 !important;
        background-color: #222222 !important;
    }

    /* ── Price spacing ── */
    .bb-single-course-sidebar .bb-course-type {
        margin-top: 20px !important;
        color: #000000 !important;
        font-weight: 700 !important;
        font-family: 'Poppins', sans-serif !important;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btnJoin = document.querySelector('.bb-single-course-sidebar .btn-join, .bb-single-course-sidebar #btn-join');
        if (btnJoin && !btnJoin.querySelector('svg')) {
            var icon = '<svg style="width:20px;height:20px;margin-right:8px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>';
            btnJoin.innerHTML = icon + '<span>' + btnJoin.textContent.trim() + '</span>';
        }
    });
</script>
<div class="bb-single-course-sidebar bb-preview-wrap">
    <div class="bb-ld-sticky-sidebar">
        <div class="widget bb-enroll-widget">
            <div class="bb-enroll-widget flex-1 push-right">
                <div class="bb-course-preview-wrap bb-thumbnail-preview">
                    <div class="bb-preview-course-link-wrap">
                        <div class="thumbnail-container <?php echo esc_attr($thumb_mode); ?>">
                            <div class="bb-course-video-overlay">
                                <div>
                                    <span class="bb-course-play-btn-wrapper"><span
                                            class="bb-course-play-btn"></span></span>
                                    <div>
                                        <?php printf(__('Preview this %s', 'buddyboss-theme'), LearnDash_Custom_Label::get_label('course')); ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail();
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bb-course-preview-content">
                <div class="bb-button-wrap">
                    <?php
                    // =============================================================================
                    // 3. PREPARACIÓN DE VARIABLES PARA LA LÓGICA DE BOTONES
                    // =============================================================================
                    // Descripción: En esta sección se calculan y definen todas las variables
                    // necesarias para la lógica condicional que mostrará los botones correctos.
                    // Incluye URLs, estado de los quizzes, progreso del curso y datos de intentos.
                    // =============================================================================
                    
                    // --- Re-calculate values that might be needed by the new button logic ---
                    // These were already done above the first button wrap, but defining here for clarity
                    // within this specific section if any new logic requires it.
                    // Assuming $course_pricing, $is_enrolled, $has_access, $login_url, $resume_link are correctly set.
                    
                    // Calcula clases y labels de avance
                    $resume_link = '';
                    if (empty($progress['percentage']) && 100 > $progress['percentage']) {
                        $btn_advance_class = 'btn-advance-start';
                        $btn_advance_label = sprintf(__('Start %s', 'buddyboss-theme'), LearnDash_Custom_Label::get_label('course'));
                        $resume_link = buddyboss_theme()->learndash_helper()->boss_theme_course_resume($course_id);
                    } elseif (100 == $progress['percentage']) {
                        $btn_advance_class = 'btn-advance-completed';
                        $btn_advance_label = __('Completed', 'buddyboss-theme');
                    } else {
                        $btn_advance_class = 'btn-advance-continue';
                        $btn_advance_label = __('Continue', 'buddyboss-theme');
                        $resume_link = buddyboss_theme()->learndash_helper()->boss_theme_course_resume($course_id);
                    }
                    if (0 === learndash_get_course_steps_count($course_id) && false !== $is_enrolled) {
                        $btn_advance_class .= ' btn-advance-disable';
                    }

                    // Login URL (ensuring it's correctly built for redirect if modal is off)
                    $login_model = LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Theme_LD30', 'login_mode_enabled');
                    $login_url = apply_filters('learndash_login_url', ($login_model === 'yes' ? '#login' : wp_login_url(get_the_permalink($course_id))));

                    // --- START: Lógica para verificar orden en espera y ocultar botón ---
                    $hide_enroll_button = false;
                    $product_id_for_course = null;

                    // Verifica si las clases personalizadas existen para evitar errores fatales.
                    if (class_exists('PoliteiaCourse') && class_exists('PoliteiaOrderFinder')) {
                        $course_object = new PoliteiaCourse($course_id);
                        $product_id_for_course = $course_object->getRelatedProductId();

                        if ($product_id_for_course) {
                            $order_finder = new PoliteiaOrderFinder();
                            $order_id = $order_finder->findOrderForUser($current_user_id, $product_id_for_course);

                            if ($order_id) {
                                $order = wc_get_order($order_id);

                                // Si la orden existe y tiene el estado 'course-on-hold', marcamos que el botón debe ocultarse.
                                if ($order && $order->has_status('course-on-hold')) {
                                    $hide_enroll_button = true;
                                }
                            }
                        }
                    }
                    // --- END: Lógica para verificar orden en espera ---
                    
                    // ——— START CUSTOM BUTTON LOGIC (BASED ON DIAGRAM AND CLARIFICATIONS) ———
                    
                    // IDs básicos para Quizzes
                    $first_quiz_id = get_post_meta($course_id, '_first_quiz_id', true);
                    $final_quiz_id = get_post_meta($course_id, '_final_quiz_id', true);

                    // URLs para Quizzes
                    $first_quiz_url = $first_quiz_id
                        ? home_url('/evaluaciones/' . get_post_field('post_name', $first_quiz_id) . '/')
                        : '';
                    $final_quiz_url = $final_quiz_id
                        ? home_url('/evaluaciones/' . get_post_field('post_name', $final_quiz_id) . '/')
                        : '';

                    // Intentos de Quizzes
                    $first_attempts = (class_exists('Politeia_Quiz_Stats') && $first_quiz_id)
                        ? Politeia_Quiz_Stats::get_all_attempts_data($current_user_id, $first_quiz_id)
                        : [];
                    $final_attempts = (class_exists('Politeia_Quiz_Stats') && $final_quiz_id)
                        ? Politeia_Quiz_Stats::get_all_attempts_data($current_user_id, $final_quiz_id)
                        : [];

                    // Determine course completion status for Final Quiz (column 4)
                    $all_lessons_completed = (isset($progress['percentage']) && intval($progress['percentage']) === 100);

                    // Column 5: Check if both quizzes are completed (for final state)
                    $first_quiz_completed = !empty($first_attempts) && isset(reset($first_attempts)['percentage']) && intval(reset($first_attempts)['percentage']) === 100;
                    $final_quiz_completed = !empty($final_attempts) && isset(reset($final_attempts)['percentage']) && intval(reset($final_attempts)['percentage']) === 100;


                    // =============================================================================
// 4. LÓGICA DE RENDERIZADO CONDICIONAL DE BOTONES
// =============================================================================
                    
                    if (!is_user_logged_in()) {
                        // 1) USUARIO NO CONECTADO → sólo Take First Quiz
                        if ($first_quiz_id) {
                            $quiz_login_redirect_url = wp_login_url($first_quiz_url);
                            ?>
                            <a id="first-test-button" href="<?php echo esc_url($quiz_login_redirect_url); ?>"
                                class="btn-advance-start btn-advance ld-primary-background"
                                style="display:block;width:100%;margin:12px 0;">
                                <?php echo $icon_quiz; ?>         <?php esc_html_e('Take First Quiz', 'buddyboss-theme'); ?>
                            </a>
                            <?php
                        }

                    } else {
                        // --- USUARIO CONECTADO ---
                    
                        // Recuperar datos del First Quiz
                        if ($first_quiz_id) {
                            $first_attempts = Politeia_Quiz_Stats::get_all_attempts_data($current_user_id, $first_quiz_id);

                            // Si es el mismo quiz (Single Quiz ID), el "First Quiz" es el intento más antiguo (baseline).
                            // Si son distintos, tomamos el más reciente (comportamiento default).
                            if ($first_quiz_id == $final_quiz_id) {
                                $last_first_attempt = !empty($first_attempts) ? end($first_attempts) : null;
                            } else {
                                $last_first_attempt = reset($first_attempts) ?: null;
                            }

                            $first_quiz_completed = !empty($first_attempts);
                        } else {
                            $first_attempts = [];
                            $first_quiz_completed = false;
                        }

                        // Recuperar datos del Final Quiz
                        if ($final_quiz_id) {
                            // Optimización: si son el mismo ID, reutilizamos los datos.
                            if ($first_quiz_id == $final_quiz_id) {
                                $final_attempts = $first_attempts;

                                // LOGICA SINGLE QUIZ:
                                // Si solo hay 1 intento, ese es el First Quiz. El Final Quiz AUN NO se ha rendido.
                                // Si hay > 1 intentos, el más reciente es el Final Quiz.
                                if (count($final_attempts) > 1) {
                                    $final_quiz_completed = true;
                                    $last_final_attempt = reset($final_attempts); // El más reciente
                                } else {
                                    $final_quiz_completed = false;
                                    $last_final_attempt = null; // No mostrar score del Final Quiz todavía
                                }

                            } else {
                                // Caso normal: IDs distintos
                                $final_attempts = Politeia_Quiz_Stats::get_all_attempts_data($current_user_id, $final_quiz_id);
                                $final_quiz_completed = !empty($final_attempts);
                                $last_final_attempt = reset($final_attempts) ?: null;
                            }

                        } else {
                            $final_attempts = [];
                            $final_quiz_completed = false;
                        }

                        // 2) FREE COURSE + First Quiz NO completado
                        if (
                            'free' === $course_pricing['type']
                            && $first_quiz_id
                            && !$first_quiz_completed
                        ) {
                            // Take First Quiz
                            ?>
                            <a id="first-test-button" href="<?php echo esc_url($first_quiz_url); ?>"
                                class="btn-advance-start btn-advance ld-primary-background"
                                style="display:block;width:100%;margin:12px 0;">
                                <?php echo $icon_quiz; ?>         <?php esc_html_e('Take First Quiz', 'buddyboss-theme'); ?>
                            </a>
                            <?php
                            // Start Course DESHABILITADO
                            ?>
                            <a class="btn-advance ld-primary-background disabled"
                                style="pointer-events:none;opacity:0.5;display:block;width:100%;margin:12px 0;">
                                <?php esc_html_e('Start Course', 'buddyboss-theme'); ?>
                            </a>
                            <?php
                            // Take Final Quiz DESHABILITADO
                            if ($final_quiz_id) {
                                ?>
                                <a class="btn-advance btn-advance-start ld-primary-background disabled"
                                    style="pointer-events:none;opacity:0.5;display:block;width:100%;margin-bottom:12px;">
                                    <?php esc_html_e('Take Final Quiz', 'buddyboss-theme'); ?>
                                </a>
                                <?php
                            }

                            // 3) Ambos quizzes completados → mostrar “COMPLETED COURSE” primero, luego porcentajes, y salir
                        } elseif ($first_quiz_completed && $final_quiz_completed) {
                            ?>
                            <p style="margin-top:8px; font-size:14px; color:#007bff; font-weight:bold;">
                                <?php esc_html_e('COMPLETED COURSE', 'buddyboss-theme'); ?>
                            </p>
                            <p style="margin-top:8px; font-size:14px; color:#666;">
                                <?php printf(esc_html__('First Quiz: %s', 'buddyboss-theme'), esc_html($last_first_attempt['percentage'])); ?>
                            </p>
                            <p style="margin-top:8px; font-size:14px; color:#666;">
                                <?php printf(esc_html__('Final Quiz: %s', 'buddyboss-theme'), esc_html($last_final_attempt['percentage'])); ?>
                            </p>
                            <?php
                            // Evitamos que se renderice lo que venga después (Free, Course Includes, etc.)
                            return;

                            // 4) Usuario inscrito (ya pasó el First Quiz o es un curso pagado)
                        } elseif ($is_enrolled) {

                            // 4.1) First Quiz: resultado o botón
                            if ($first_quiz_id) {
                                if ($first_quiz_completed) {
                                    ?>
                                    <p style="margin-top:8px; font-size:14px; color:#666;">
                                        <?php printf(esc_html__('First Quiz: %s', 'buddyboss-theme'), esc_html($last_first_attempt['percentage'])); ?>
                                    </p>
                                <?php } else { ?>
                                    <a id="first-test-button" href="<?php echo esc_url($first_quiz_url); ?>"
                                        class="btn-advance-start btn-advance ld-primary-background"
                                        style="display:block;width:100%;margin:12px 0;">
                                        <?php echo $icon_quiz; ?>                 <?php esc_html_e('Take First Quiz', 'buddyboss-theme'); ?>
                                    </a>
                                <?php }
                            }

                            // 4.2) Start/Continue Course or “All Lessons Finished”
                            if (in_array($course_pricing['type'], array('paynow', 'closed'), true) && $first_quiz_id && !$first_quiz_completed) {
                                // Paid/Closed + First Quiz exists but NOT completed → disable Start Course
                                ?>
                                <div class="learndash_join_button <?php echo esc_attr($btn_advance_class); ?>">
                                    <a class="btn-advance ld-primary-background disabled"
                                        style="pointer-events:none;opacity:0.5;display:block;width:100%;margin:12px 0;">
                                        <?php esc_html_e('Start Course', 'buddyboss-theme'); ?>
                                    </a>
                                </div>
                                <?php
                            } elseif (!$all_lessons_completed) {
                                // Not all lessons done → show Start or Continue
                                ?>
                                <div class="learndash_join_button <?php echo esc_attr($btn_advance_class); ?>">
                                    <a href="<?php echo esc_url($resume_link); ?>" class="btn-advance ld-primary-background"
                                        style="display:block;width:100%;margin:12px 0;">
                                        <?php echo esc_html($btn_advance_label); ?>
                                    </a>
                                </div>
                                <?php
                            } else {
                                // All lessons finished → show message
                                ?>
                                <p style="margin-top:8px; font-size:14px; color:#007bff; font-weight:bold;">
                                    <?php esc_html_e('All Lessons Finished', 'buddyboss-theme'); ?>
                                </p>
                                <?php
                            }

                            // 4.3) Final Quiz: botón o porcentaje
                            if ($final_quiz_id) {
                                if (!$all_lessons_completed) {
                                    // aún no terminó lecciones
                                    ?>
                                    <a class="btn-advance btn-advance-start ld-primary-background disabled"
                                        style="pointer-events:none;opacity:0.5;display:block;width:100%;margin-bottom:12px;">
                                        <?php esc_html_e('Take Final Quiz', 'buddyboss-theme'); ?>
                                    </a>
                                    <?php
                                } elseif (!$final_quiz_completed) {
                                    // habilitado: aún no lo rindió
                                    ?>
                                    <a href="<?php echo esc_url($final_quiz_url); ?>"
                                        class="btn-advance btn-advance-start ld-primary-background"
                                        style="display:block;width:100%;margin-bottom:12px;">
                                        <?php esc_html_e('Take Final Quiz', 'buddyboss-theme'); ?>
                                    </a>
                                    <?php
                                } else {
                                    // mostrar porcentaje final si ya hay intento
                                    ?>
                                    <p style="margin-top:8px; font-size:14px; color:#666;">
                                        <?php printf(esc_html__('Final Quiz: %s', 'buddyboss-theme'), esc_html($last_final_attempt['percentage'])); ?>
                                    </p>
                                    <?php
                                }
                            }

                            // 5) Usuario conectado pero NO inscrito
                        } else {

                            // 5.1) First Quiz: resultado o botón
                            if ($first_quiz_id) {
                                if (!empty($first_attempts)) {
                                    ?>
                                    <p style="margin-top:8px; font-size:14px; color:#666;">
                                        <?php printf(esc_html__('First Quiz: %s', 'buddyboss-theme'), esc_html($last_first_attempt['percentage'])); ?>
                                    </p>
                                <?php } else { ?>
                                    <a id="first-test-button" href="<?php echo esc_url($first_quiz_url); ?>"
                                        class="btn-advance-start btn-advance ld-primary-background"
                                        style="display:block;width:100%;margin:12px 0;">
                                        <?php echo $icon_quiz; ?>                 <?php esc_html_e('Take First Quiz', 'buddyboss-theme'); ?>
                                    </a>
                                <?php }
                            }

                            // 5.2) Acción principal (Start / Buy / Subscribe / Open)
                            if ('free' === $course_pricing['type']) {
                                if (!$is_enrolled) {
                                    $join_nonce = wp_create_nonce('course_join_' . $current_user_id . '_' . $course_id);
                                    ?>
                                    <form method="post" style="margin:12px 0;display:block;">
                                        <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />
                                        <input type="hidden" name="course_join" value="<?php echo esc_attr($join_nonce); ?>" />
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($resume_link); ?>" />
                                        <button type="submit" class="btn-advance ld-primary-background"
                                            style="display:block;width:100%;">
                                            <?php esc_html_e('Start Course', 'buddyboss-theme'); ?>
                                        </button>
                                    </form>
                                    <?php
                                } else {
                                    ?>
                                    <a href="<?php echo esc_url($resume_link); ?>" class="btn-advance ld-primary-background"
                                        style="display:block;width:100%;margin:12px 0;">
                                        <?php esc_html_e('Continue', 'buddyboss-theme'); ?>
                                    </a>
                                    <?php
                                }

                            } elseif (in_array($course_pricing['type'], ['closed', 'paynow', 'subscribe'], true)) {
                                // Solo muestra los botones de pago si nuestra bandera de "ocultar" es falsa.
                                if (!$hide_enroll_button) {
                                    echo learndash_payment_buttons($course);
                                }
                            } else {
                                ?>
                                <a href="<?php echo esc_url($resume_link); ?>" class="btn-advance ld-primary-background"
                                    style="display:block;width:100%;margin:12px 0;">
                                    <?php echo esc_html($btn_advance_label); ?>
                                </a>
                                <?php
                            }
                        }
                    }
                    ?>


                    <?php
                    // =============================================================================
                    // 5. DATOS OCULTOS DE QUIZZES PARA DEPURACIÓN
                    // =============================================================================
                    // Descripción: Esta sección renderiza información detallada sobre los intentos
                    // de los quizzes. Está oculta por defecto (`display:none`) y sirve
                    // principalmente para propósitos de depuración o desarrollo.
                    // =============================================================================
                    ?>
                    <?php if ($first_quiz_id || $final_quiz_id): ?>
                        <div id="test-data" style="display:none">
                            <h3>
                                <?php
                                if ($first_quiz_id) {
                                    echo esc_html(get_the_title($first_quiz_id));
                                } else {
                                    esc_html_e('No First Quiz', 'buddyboss-theme');
                                }
                                ?>
                            </h3>
                            <?php if ($first_quiz_id): ?>
                                <p style="color:#666;">First Quiz ID: <?php echo esc_html($first_quiz_id); ?></p>
                                <?php if (empty($first_attempts)): ?>
                                    <p class="quiz-status-text"><?php esc_html_e('No attempts yet', 'buddyboss-theme'); ?></p>
                                <?php else: ?>
                                    <p style="color:#666;">
                                        <?php printf(
                                            _n('Has taken %d time:', 'Has taken %d times:', count($first_attempts), 'buddyboss-theme'),
                                            count($first_attempts)
                                        ); ?>
                                    </p>
                                    <ul style="list-style:none;padding:0;color:#666;">
                                        <?php foreach ($first_attempts as $a): ?>
                                            <li>
                                                <?php printf(
                                                    __('Attempt #%1$s: %2$s%% · %3$s points', 'buddyboss-theme'),
                                                    esc_html($a['activity_id']),
                                                    esc_html($a['percentage']),
                                                    esc_html($a['points'])
                                                ); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>

                            <h3 style="margin-top:1em;">
                                <?php
                                if ($final_quiz_id) {
                                    echo esc_html(get_the_title($final_quiz_id));
                                } else {
                                    esc_html_e('No Final Quiz', 'buddyboss-theme');
                                }
                                ?>
                            </h3>
                            <?php if ($final_quiz_id): ?>
                                <p style="color:#666;">Final Quiz ID: <?php echo esc_html($final_quiz_id); ?></p>
                                <?php if (empty($final_attempts)): ?>
                                    <p class="quiz-status-text"><?php esc_html_e('No attempts yet', 'buddyboss-theme'); ?></p>
                                <?php else: ?>
                                    <p style="color:#666;">
                                        <?php printf(
                                            _n('Has taken %d time:', 'Has taken %d times:', count($final_attempts), 'buddyboss-theme'),
                                            count($final_attempts)
                                        ); ?>
                                    </p>
                                    <ul style="list-style:none;padding:0;color:#666;">
                                        <?php foreach ($final_attempts as $a): ?>
                                            <li>
                                                <?php printf(
                                                    __('Attempt #%1$s: %2$s%% · %3$s points', 'buddyboss-theme'),
                                                    esc_html($a['activity_id']),
                                                    esc_html($a['percentage']),
                                                    esc_html($a['points'])
                                                ); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>

                            <p style="margin-top:1em; color:#666;"><strong>Course ID:</strong>
                                <?php echo esc_html($course_id); ?></p>

                            <?php
                            // Mostrar Order Number usando PoliteiaOrderFinder
                            $order_number = null;
                            $order_for_product = null;
                            $product_title = null;

                            if (class_exists('PoliteiaCourse') && class_exists('PoliteiaOrderFinder')) {
                                $course_object = new PoliteiaCourse($course_id);
                                $product_id = $course_object->getRelatedProductId();
                                $order_finder = new PoliteiaOrderFinder();
                                $order_id = $order_finder->findOrderForUser($current_user_id, $product_id);

                                if ($order_id) {
                                    $order = wc_get_order($order_id);
                                    if ($order) {
                                        $order_number = $order->get_order_number();
                                        $order_for_product = $order;
                                        foreach ($order->get_items() as $item) {
                                            if ($item->get_product_id() == $product_id) {
                                                $product_title = $item->get_name() . ' (' . $product_id . ')';
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if ($order_number) {
                                echo '<p style="color:#666;"><strong>Order Number:</strong> ' . esc_html($order_number) . '</p>';
                            } else {
                                echo '<p style="color:#999;"><strong>Order Number:</strong> (none)</p>';
                            }

                            if ($product_title) {
                                echo '<p style="color:#666;"><strong>Item Name:</strong> ' . esc_html($product_title) . '</p>';
                            } else {
                                echo '<p style="color:#999;"><strong>Item Name:</strong> (none)</p>';
                            }

                            // Lógica para ocultar botón "Enroll in this course"
                            if ($order_for_product && $order_for_product->has_status('course-on-hold')) {
                                foreach ($order_for_product->get_items() as $item) {
                                    if ($item->get_product_id() == $product_id) {
                                        echo '<style>#learndash-course-enroll-button { display: none !important; }</style>';
                                        break;
                                    }
                                }
                            }
                            ?>
                        </div>
                    <?php endif; ?>



                    <?php // ——— END CUSTOM BUTTON LOGIC ——— ?>




                    <?php
                    // =============================================================================
// 6. ETIQUETA DE PRECIO Y TIPO DE CURSO
// =============================================================================
// Descripción: Muestra el tipo de registro del curso, como "Gratis",
// "Registro Abierto", el precio o los detalles de la suscripción.
// Esta información se muestra debajo de los botones de acción.
// =============================================================================
                    if ('open' === $course_pricing['type']) {
                        echo '<span class="bb-course-type bb-course-type-open">'
                            . __('Open Registration', 'buddyboss-theme')
                            . '</span>';
                    } elseif ('free' === $course_pricing['type']) {
                        echo '<span class="bb-course-type bb-course-type-free">'
                            . __('Free', 'buddyboss-theme')
                            . '</span>';
                    } elseif (!empty($course_pricing['price']) && ('paynow' === $course_pricing['type'] || 'closed' === $course_pricing['type'])) {
                        echo '<span class="bb-course-type bb-course-type-paynow">'
                            . wp_kses_post(learndash_get_price_formatted($course_pricing['price']))
                            . '</span>';
                    } elseif ('subscribe' === $course_pricing['type']) {
                        $course_price_billing_p3 = get_post_meta($course_id, 'course_price_billing_p3', true);
                        $course_price_billing_t3 = get_post_meta($course_id, 'course_price_billing_t3', true);

                        if ($course_price_billing_t3 == 'D') {
                            $course_price_billing_t3 = 'day(s)';
                        } elseif ($course_price_billing_t3 == 'W') {
                            $course_price_billing_t3 = 'week(s)';
                        } elseif ($course_price_billing_t3 == 'M') {
                            $course_price_billing_t3 = 'month(s)';
                        } elseif ($course_price_billing_t3 == 'Y') {
                            $course_price_billing_t3 = 'year(s)';
                        }

                        $recurring = ('' === $course_price_billing_p3) ? 0 : $course_price_billing_p3;

                        $recurring_label = '<span class="bb-course-type bb-course-type-subscribe">';
                        if ('' === $course_pricing['price'] && 'subscribe' === $course_pricing['type']) {
                            $recurring_label .= '<span class="bb-course-type bb-course-type-subscribe">' . __('Free', 'buddyboss-theme') . '</span>';
                        } else {
                            $recurring_label .= wp_kses_post(learndash_get_price_formatted($course_pricing['price']));
                        }
                        $recurring_label .= '<span class="course-bill-cycle"> / ' . $recurring . ' ' . $course_price_billing_t3 . '</span></span>';
                        echo $recurring_label;
                    }
                    ?>
                </div>

                <?php
                // =============================================================================
                // 7. CÁLCULO Y VISUALIZACIÓN DEL CONTENIDO DEL CURSO
                // =============================================================================
                // Descripción: Esta sección calcula el número total de lecciones, temas y
                // quizzes en el curso. Luego, muestra un resumen de lo que el curso incluye,
                // como "X Lecciones", "Y Temas" y si ofrece un certificado.
                // =============================================================================
                $topics_count = 0;
                foreach ($lesson_count as $lesson) {
                    $lesson_topics = learndash_get_topic_list($lesson->ID);
                    if ($lesson_topics) {
                        $topics_count += sizeof($lesson_topics);
                    }
                }

                // course quizzes.
                $course_quizzes = learndash_get_course_quiz_list($course_id);
                $course_quizzes_count = sizeof($course_quizzes);

                // lessons quizzes.
                if (is_array($lesson_count) || is_object($lesson_count)) {
                    foreach ($lesson_count as $lesson) {
                        $quizzes = learndash_get_lesson_quiz_list($lesson->ID, null, $course_id);
                        $lesson_topics = learndash_topic_dots($lesson->ID, false, 'array', null, $course_id);
                        if ($quizzes && !empty($quizzes)) {
                            $course_quizzes_count += count($quizzes);
                        }
                        if ($lesson_topics && !empty($lesson_topics)) {
                            foreach ($lesson_topics as $topic) {
                                $quizzes = learndash_get_lesson_quiz_list($topic, null, $course_id);
                                if (!$quizzes || empty($quizzes)) {
                                    continue;
                                }
                                $course_quizzes_count += count($quizzes);
                            }
                        }
                    }
                }

                if (0 < sizeof($lesson_count) || 0 < $topics_count || 0 < $course_quizzes_count || $course_certificate) {
                    $course_label = LearnDash_Custom_Label::get_label('course');
                    ?>
                    <div class="bb-course-volume">
                        <h4><?php echo sprintf(esc_html__('%s Includes', 'buddyboss-theme'), $course_label); ?></h4>
                        <ul class="bb-course-volume-list">
                            <?php if (sizeof($lesson_count) > 0) { ?>
                                <li>
                                    <i class="bb-icon-l bb-icon-book"></i><?php echo sizeof($lesson_count); ?>
                                    <?php echo sizeof($lesson_count) > 1 ? LearnDash_Custom_Label::get_label('lessons') : LearnDash_Custom_Label::get_label('lesson'); ?>
                                </li>
                            <?php } ?>
                            <?php if ($topics_count > 0) { ?>
                                <li>
                                    <i class="bb-icon-l bb-icon-text"></i><?php echo $topics_count; ?>
                                    <?php echo $topics_count != 1 ? LearnDash_Custom_Label::get_label('topics') : LearnDash_Custom_Label::get_label('topic'); ?>
                                </li>
                            <?php } ?>
                            <?php if ($course_quizzes_count > 0) { ?>
                                <li>
                                    <i class="bb-icon-rl bb-icon-question"></i><?php echo $course_quizzes_count; ?>
                                    <?php echo $course_quizzes_count != 1 ? LearnDash_Custom_Label::get_label('quizzes') : LearnDash_Custom_Label::get_label('quiz'); ?>
                                </li>
                            <?php } ?>
                            <?php if ($course_certificate) { ?>
                                <li>
                                    <i
                                        class="bb-icon-l bb-icon-certificate"></i><?php echo sprintf(esc_html__('%s Certificate', 'buddyboss-theme'), $course_label); ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
        // =============================================================================
        // 8. ÁREA DE WIDGETS ADICIONALES DEL SIDEBAR
        // =============================================================================
        // Descripción: Si hay widgets asignados al área de sidebar específica para
        // cursos de LearnDash (`learndash_course_sidebar`), se mostrarán aquí.
        // Permite añadir contenido extra al sidebar de forma modular.
        // =============================================================================
        if (is_active_sidebar('learndash_course_sidebar')) {
            ?>
            <ul class="ld-sidebar-widgets">
                <?php dynamic_sidebar('learndash_course_sidebar'); ?>
            </ul>
            <?php
        }
        ?>
    </div>
</div>

<?php
// =============================================================================
// 9. MODAL PARA EL VIDEO DE VISTA PREVIA
// =============================================================================
// Descripción: Define la estructura del modal (ventana emergente) que
// contendrá el video de vista previa del curso. Este modal está oculto por
// defecto y se activa cuando el usuario hace clic en la miniatura del video.
// Maneja diferentes formatos de video (oEmbed, MP4).
// =============================================================================
?>
<div class="bb-modal bb_course_video_details mfp-hide">
    <?php
    if ('' !== $course_video_embed) {
        if (wp_oembed_get($course_video_embed)) {
            echo wp_oembed_get($course_video_embed);
        } elseif (isset($file_info['extension']) && 'mp4' === $file_info['extension']) {
            ?>
            <video width="100%" controls>
                <source src="<?php echo $course_video_embed; ?>" type="video/mp4">
                <?php _e('Your browser does not support HTML5 video.', 'buddyboss-theme'); ?>
            </video>
            <?php
        } else {
            _e('Video format is not supported, use Youtube video or MP4 format.', 'buddyboss-theme');
        }
    }
    ?>
</div>