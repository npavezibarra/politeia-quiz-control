<?php
/**
 * Template for the first quiz notification email.
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
                'cutoutPercentage' => 75, // Match Result Page look (slightly thinner)
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

function pqc_get_first_quiz_email_content($quiz_data, $user)
{
    global $wpdb;

    // Ensure Polis_Quiz_Attempts_Shortcode class is loaded if not already
    // This is crucial because we need its static property.
    if (!class_exists('Polis_Quiz_Attempts_Shortcode')) {
        // Assuming your plugin structure for this class:
        // You might need to adjust this path if Polis_Quiz_Attempts_Shortcode is elsewhere.
        // Example path if it's in a 'classes' or 'includes' folder relative to your email template:
        if (file_exists(plugin_dir_path(__FILE__) . 'polis-average-quiz-result.php')) {
            include_once plugin_dir_path(__FILE__) . 'polis-average-quiz-result.php';
        }
        // If it's in includes/polis-average-quiz-result.php from the plugin root:
        // include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/polis-average-quiz-result.php';
    }

    // CRITICAL: Call the shortcode's render method to ensure $last_average is populated
    // We call it here and capture the output to discard it, but the side effect is setting $last_average.
    ob_start();
    do_shortcode('[polis_quiz_attempts id="' . (isset($quiz_data['quiz']) ? intval($quiz_data['quiz']) : 0) . '"]');
    ob_end_clean(); // Discard the HTML output from the shortcode

    $debug_data = pqc_get_quiz_debug_data($quiz_data, $user);
    $subject = '✔️ New Quiz Completed: ' . $debug_data['quiz_title'];

    $svg_url = content_url('uploads/2025/06/LogoNewBlackPoliteia.svg');

    $raw_score = isset($debug_data['first_quiz_attempt']) ? $debug_data['first_quiz_attempt'] : '0';
    $user_score = (int) preg_replace('/[^0-9]/', '', $raw_score);
    $user_score = min(100, max(0, $user_score));

    // --- CAMBIO CLAVE AQUÍ: Obtener el promedio de la clase del shortcode ---
    $avg_score = class_exists('Polis_Quiz_Attempts_Shortcode') ? Polis_Quiz_Attempts_Shortcode::$last_average : 0;
    // Asegurarse de que sea un número válido y dentro del rango
    $avg_score = min(100, max(0, intval($avg_score)));


    $chart_url_user = politeia_generate_quickchart_url($user_score, 'Your Score');
    $chart_url_avg = politeia_generate_quickchart_url($avg_score);
    $quiz_id = isset($quiz_data['quiz']) ? intval($quiz_data['quiz']) : 0;

    $course_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_first_quiz_id'
          AND meta_value = %d
        LIMIT 1
    ", $quiz_id));

    $course_title = get_the_title($course_id);
    $course_url = $course_id ? get_permalink($course_id) : home_url();
    $login_redirect = wp_login_url($course_url);

    $completion_date = date_i18n(
        get_option('date_format'),
        current_time('timestamp')
    );

    $debug_data['completion_date'] = $completion_date;

    $product_id = 0;
    if ($course_id) {
        if (!class_exists('PoliteiaCourse') && file_exists(plugin_dir_path(__FILE__) . '../classes/class-politeia-course.php')) {
            include_once plugin_dir_path(__FILE__) . '../classes/class-politeia-course.php';
        }
        if (class_exists('PoliteiaCourse')) {
            $course_object = new PoliteiaCourse($course_id);
            $product_id = $course_object->getRelatedProductId();
        }
    }

    $checkout_url = $product_id ? wc_get_checkout_url() : '#';
    $add_to_cart_url = $product_id ? add_query_arg('add-to-cart', $product_id, $checkout_url) : '#';

    // Detectar si el curso es FREE usando LearnDash API
    $access_type = function_exists('learndash_get_setting') ? learndash_get_setting($course_id, 'course_price_type') : '';
    $is_free_course = ($access_type === 'free');

    // Inicia cuerpo del correo con estructura de TABLAS para compatibilidad
    $body = '<div style="background-color: #f8f8f8; padding: 30px 0;">'; // Wrapper externo

    // Tabla Principal (Container)
    $body .= '<table align="center" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; margin: 0 auto; max-width: 600px; width: 100%; border: 1px solid #d5d5d5; border-radius: 6px; font-family: sans-serif;">';

    // 1. Logo
    $body .= '<tr><td align="center" style="padding: 30px 0;">';
    $body .= '<img src="' . esc_url($svg_url) . '" alt="Politeia Logo" width="200" style="display: block; margin: 0 auto; max-width: 200px; height: auto;">';
    $body .= '</td></tr>';

    // Separator
    $body .= '<tr><td style="border-bottom: 1px solid #eeeeee;"></td></tr>';

    // 2. Text Content
    $body .= '<tr><td align="center" style="padding: 30px 30px 0 30px;">';
    $body .= '<p style="font-size:12px; color: #666; margin-top: 0;">Completion date: <strong>' . esc_html($completion_date) . '</strong>.</p>';
    $body .= '<h2 style="color: #333333; font-size: 22px; margin: 10px 0;">🎉 Congratulations ' . esc_html($debug_data['user_display_name']) . '!</h2>';
    $body .= '<p style="color: #555; line-height: 1.5; font-size: 18px;">You have completed the quiz <br><strong>' . esc_html($debug_data['quiz_title']) . '</strong><br> part of the course<br> <strong>' . esc_html($debug_data['course_title']) . '</strong>.</p>';
    $body .= '<p style="color: #555;">Here are the statistics:</p>';
    $body .= '</td></tr>';

    // Separator
    $body .= '<tr><td style="padding-top: 20px; border-bottom: 1px solid #eeeeee;"></td></tr>';

    // 3. Charts Section (Nested Table)
    $body .= '<tr><td align="center" style="padding: 30px 10px;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';

    // Chart 1: Your Score (Centered, Full Width)
    $body .= '<td width="100%" align="center" valign="top" style="padding: 0 5px;">';
    $body .= '<img src="' . esc_url($chart_url_user) . '" alt="Your Score" width="300" style="display: block; max-width: 100%; height: auto;">';
    $body .= '</td>';

    $body .= '</tr></table>';
    $body .= '</td></tr>';

    // Separator
    $body .= '<tr><td style="border-bottom: 1px solid #eeeeee;"></td></tr>';

    // 4. CTA / Footer Message
    $body .= '<tr><td align="center" style="padding: 30px;">';

    // Verificar si el usuario compró el curso
    $course_bought = false;
    if ($product_id && is_array($user) && isset($user['ID'])) {
        $user = get_user_by('ID', $user['ID']);
    }
    if ($product_id && $user instanceof WP_User) {
        $customer_orders = wc_get_orders([
            'customer_id' => $user->ID,
            'limit' => -1,
            'status' => ['processing', 'completed', 'course-on-hold'],
        ]);
        foreach ($customer_orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $course_bought = true;
                    break 2;
                }
            }
        }
    }

    if ($is_free_course || $course_bought) {
        if ($is_free_course) {
            $body .= '<h3 style="margin-top:0;">This is a free course — get started now!</h3>';
        }
        $body .= '<a href="' . esc_url($login_redirect) . '" style="display: inline-block; background-color: #000000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;" target="_blank">Go to Course</a>';
        if (!$is_free_course) {
            $body .= '<p style="color: #777; font-size: 14px; margin-top: 20px;">🚨 Remember<br>After completing all the lessons, you will be able to take the final quiz<br> to see how your results evolve over time.</p>';
        }
    } else {
        $body .= '<p style="margin-top:0;">To access the course content and take the quiz, please purchase the course first.</p>';
        $body .= '<a href="' . esc_url($add_to_cart_url) . '" style="display: inline-block; background-color: #000000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">Buy Course</a>';
    }

    $body .= '<p style="margin-top: 30px; color: #555;">We hope you learn and have fun on this educational journey!</p>';

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