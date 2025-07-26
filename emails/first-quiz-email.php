<?php
/**
 * Template for the first quiz notification email.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1) Asegúrate de que la clase de intentos esté disponible para exponer el promedio
if ( ! class_exists( 'Polis_Quiz_Attempts_Shortcode' ) ) {
    $path = plugin_dir_path( __FILE__ ) . '../classes/class-polis-quiz-attempts-shortcode.php';
    if ( file_exists( $path ) ) {
        include_once $path;
    }
}

/**
 * Generates a QuickChart URL for a doughnut chart.
 *
 * @param int $value The percentage value to display.
 * @return string   The URL for the chart image.
 */
function politeia_generate_quickchart_url( $value ) {
    $config = [
        'type'    => 'doughnut',
        'data'    => [
            'datasets' => [[
                'data'            => [ $value, 100 - $value ],
                'backgroundColor' => ['#f9c600', '#eeeeee'],
                'borderWidth'     => 0,
            ]],
        ],
        'options' => [
            'cutout'  => '10%',
            'plugins' => [
                'legend'        => [ 'display' => false ],
                'tooltip'       => [ 'enabled' => false ],
                'datalabels'    => [ 'display' => false ],
                'doughnutlabel' => [
                    'labels' => [[
                        'text' => $value . '%',
                        'font' => [
                            'size'   => 24,
                            'weight' => 'bold',
                        ],
                        'color' => '#333',
                    ]],
                ],
            ],
        ],
        'plugins' => ['doughnutlabel'],
    ];

    return 'https://quickchart.io/chart?c=' . urlencode( wp_json_encode( $config ) );
}

/**
 * Builds the subject and HTML body for the first quiz notification email.
 *
 * @param array $quiz_data Data about the quiz (must include ['quiz'] => quiz ID).
 * @param array $user      User data (must include ['ID']).
 * @return array           ['subject' => string, 'body' => string]
 */
function pqc_get_first_quiz_email_content( $quiz_data, $user ) {
    global $wpdb;

    // --- Debug data (quiz title, user name) ---
    $debug_data = pqc_get_quiz_debug_data( $quiz_data, $user );
    $subject    = '✔️ New Quiz Completed: ' . $debug_data['quiz_title'];

    // Logo
    $svg_url = content_url( 'uploads/2025/06/LogoNewBlackPoliteia.svg' );

    // --- 1) Puntaje del usuario ---
    $raw_score  = isset( $debug_data['first_quiz_attempt'] ) ? $debug_data['first_quiz_attempt'] : '0';
    $user_score = (int) preg_replace( '/\D/', '', $raw_score );
    $user_score = min( 100, max( 0, $user_score ) );

    // --- 2) Promedio vía shortcode (almacena en $GLOBALS['polis_quiz_last_average']) ---
    // Ejecutamos el shortcode para que calcule y guarde el promedio
    do_shortcode( '[polis_quiz_attempts id="' . intval( $quiz_data['quiz'] ) . '"]' );
    $avg_score = isset( $GLOBALS['polis_quiz_last_average'] )
        ? intval( $GLOBALS['polis_quiz_last_average'] )
        : 0;

    // --- 3) Generar URLs de los gráficos ---
    $chart_url_user = politeia_generate_quickchart_url( $user_score );
    $chart_url_avg  = politeia_generate_quickchart_url( $avg_score );

    // --- 4) Curso y URLs ---
    $quiz_id        = isset( $quiz_data['quiz'] ) ? intval( $quiz_data['quiz'] ) : 0;
    $course_id      = $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_first_quiz_id' AND meta_value = %d LIMIT 1",
        $quiz_id
    ) );
    $course_title   = get_the_title( $course_id );
    $course_url     = $course_id ? get_permalink( $course_id ) : home_url();
    $login_redirect = wp_login_url( $course_url );

    // Fecha de completado
    $completion_date               = date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) );
    $debug_data['completion_date'] = $completion_date;

    // Producto relacionado
    $product_id = 0;
    if ( $course_id ) {
        if ( ! class_exists( 'PoliteiaCourse' ) && file_exists( plugin_dir_path( __FILE__ ) . '../classes/class-politeia-course.php' ) ) {
            include_once plugin_dir_path( __FILE__ ) . '../classes/class-politeia-course.php';
        }
        if ( class_exists( 'PoliteiaCourse' ) ) {
            $course_obj = new PoliteiaCourse( $course_id );
            $product_id = $course_obj->getRelatedProductId();
        }
    }
    $checkout_url    = $product_id ? wc_get_checkout_url() : '#';
    $add_to_cart_url = $product_id ? add_query_arg( 'add-to-cart', $product_id, $checkout_url ) : '#';

    // ¿Curso gratuito?
    $access_type    = function_exists( 'learndash_get_setting' )
        ? learndash_get_setting( $course_id, 'course_price_type' )
        : '';
    $is_free_course = ( $access_type === 'free' );

    // --- 5) Construcción del HTML ---
    $body  = '<div style="background:#f8f8f8;padding:30px 0;">';
    $body .= '<style>
        @media only screen and (max-width:600px){
            .hide-on-mobile{display:none!important;}
            #your-score-pie,#polis-score-pie{margin-bottom:40px!important;}
        }
    </style>';

    $body .= '<div style="background:white;padding:30px;text-align:center;max-width:700px;margin:auto;border-radius:6px;border:1px solid #d5d5d5;">';
    $body .= '<img src="' . esc_url( $svg_url ) . '" alt="Politeia Logo" style="max-width:200px;margin-bottom:20px;">';
    $body .= '<hr>';
    $body .= '<p style="font-size:12px;">Completion date: <strong>' . esc_html( $completion_date ) . '</strong>.</p>';
    $body .= '<h2 style="font-size:22px;color:#333;margin-bottom:10px;">🎉 Congratulations ' . esc_html( $debug_data['user_display_name'] ) . '!</h2>';
    $body .= '<p>You have completed the quiz <strong>' . esc_html( $debug_data['quiz_title'] ) . '</strong>, part of the course <strong>' . esc_html( $course_title ) . '</strong>.</p>';
    $body .= '<p>Here are the statistics:</p>';
    $body .= '<hr style="margin:20px 0;">';

    $body .= '<div style="display:flex;justify-content:space-evenly;flex-wrap:wrap;align-items:center;font-family:sans-serif;">';

    // Your Score
    $body .= '<div id="your-score-pie" style="max-width:300px;text-align:center;">';
    $body .= '<h3 style="margin-bottom:10px;color:#333;">Your Score</h3>';
    $body .= '<img src="' . esc_url( $chart_url_user ) . '" alt="Your Score" style="width:100%;height:auto;">';
    $body .= '</div>';

    // Divider
    $body .= '<div class="hide-on-mobile" style="width:1px;height:300px;background:#ccc;"></div>';

    // Polis Average
    $body .= '<div id="polis-score-pie" style="max-width:300px;text-align:center;">';
    $body .= '<h3 style="margin-bottom:10px;color:#333;">Polis Average</h3>';
    $body .= '<img src="' . esc_url( $chart_url_avg ) . '" alt="Polis Average" style="width:100%;height:auto;">';
    $body .= '</div>';

    $body .= '</div><hr style="margin:20px 0;">';

    // CTA
    if ( $is_free_course || ( $product_id && pqc_user_has_bought_course( $user['ID'], $product_id ) ) ) {
        $body .= '<p style="text-align:center;"><a href="' . esc_url( $login_redirect ) . '" style="background:#000;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">Go to Course</a></p>';
    } else {
        $body .= '<p style="text-align:center;">To access the course, please <a href="' . esc_url( $add_to_cart_url ) . '" style="background:#000;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">Buy Course</a>.</p>';
    }

    $body .= '<p style="text-align:center;margin-top:30px;">We hope you learn and have fun on this educational journey!</p>';
    $body .= '</div></div>';

    return [
        'subject' => $subject,
        'body'    => $body,
    ];
}
