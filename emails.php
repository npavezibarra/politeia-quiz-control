<?php
/**
 * Controlador principal para el envío de correos electrónicos.
 *
 * @package Politeia-Quiz-Control
 */

// Evita el acceso directo al archivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// FUNCIONES DE DATOS Y DEPURACIÓN
// =========================================================================

function pqc_get_last_attempt_data( $user_id, $quiz_id ) {
    global $wpdb;
    if ( empty( $quiz_id ) ) return [ 'percentage' => 'None', 'date' => '—' ];
    $activity = $wpdb->get_row( $wpdb->prepare( "SELECT ua.activity_id, ua.activity_completed FROM {$wpdb->prefix}learndash_user_activity AS ua INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam ON ua.activity_id = uam.activity_id WHERE ua.user_id = %d AND uam.activity_meta_key = 'quiz' AND uam.activity_meta_value+0 = %d AND ua.activity_type = 'quiz' AND ua.activity_completed IS NOT NULL ORDER BY ua.activity_id DESC LIMIT 1", $user_id, $quiz_id ) );
    if ( ! $activity ) return [ 'percentage' => 'None', 'date' => '—' ];
    $percentage = $wpdb->get_var( $wpdb->prepare( "SELECT activity_meta_value+0 FROM {$wpdb->prefix}learndash_user_activity_meta WHERE activity_id = %d AND activity_meta_key = 'percentage' LIMIT 1", $activity->activity_id ) );
    return [ 'percentage' => intval( $percentage ) . '%', 'date' => date_i18n( get_option( 'date_format' ) . ' H:i', $activity->activity_completed ) ];
}

/**
 * Recolecta todos los datos de depuración.
 * Ahora acepta el objeto $user directamente.
 */
function pqc_get_quiz_debug_data( $quiz_data, $user ) { // <-- CAMBIO: Acepta el objeto $user
    global $wpdb;
    $user_id = $user->ID; // <-- CAMBIO: Obtenemos el ID del objeto $user
    $quiz_id = is_object( $quiz_data['quiz'] ) ? $quiz_data['quiz']->ID : $quiz_data['quiz'];
    
    $course_id_first = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_first_quiz_id' AND meta_value = %d LIMIT 1", $quiz_id ) );
    $course_id_final = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_final_quiz_id' AND meta_value = %d LIMIT 1", $quiz_id ) );
    $course_id = $course_id_first ?: $course_id_final;

    $first_quiz_id = $course_id ? get_post_meta( $course_id, '_first_quiz_id', true ) : null;
    $final_quiz_id = $course_id ? get_post_meta( $course_id, '_final_quiz_id', true ) : null;
    $first_data = $first_quiz_id ? pqc_get_last_attempt_data( $user_id, $first_quiz_id ) : [ 'percentage' => 'None', 'date' => '—' ];
    $final_data = $final_quiz_id ? pqc_get_last_attempt_data( $user_id, $final_quiz_id ) : [ 'percentage' => 'None', 'date' => '—' ];
    $progress_data = $course_id ? learndash_course_progress(['user_id' => $user_id, 'course_id' => $course_id, 'array' => true]) : ['percentage' => 0];

    return [
        'user_id' => $user_id,
        'user_display_name' => $user->display_name ?: 'N/A', // <-- CAMBIO: Usamos el objeto $user
        'user_email' => $user->user_email ?: 'N/A', // <-- CAMBIO: Usamos el objeto $user
        'quiz_id' => $quiz_id,
        'quiz_title' => get_the_title( $quiz_id ),
        'is_first_quiz' => (int) $quiz_id === (int) $first_quiz_id,
        'is_final_quiz' => (int) $quiz_id === (int) $final_quiz_id,
        'course_id_detected' => $course_id ?: 'N/A',
        'course_title' => $course_id ? get_the_title( $course_id ) : 'N/A',
        'first_quiz_id' => $first_quiz_id ?: 'N/A',
        'first_quiz_attempt' => $first_data['percentage'],
        'first_quiz_date' => $first_data['date'],
        'final_quiz_id' => $final_quiz_id ?: 'N/A',
        'final_quiz_attempt' => $final_data['percentage'],
        'final_quiz_date' => $final_data['date'],
        'lessons_completed' => isset($progress_data['percentage']) ? $progress_data['percentage'] . '%' : '0%',
        'ld_course_id_hook' => $quiz_data['course'],
        'ld_percentage_hook' => $quiz_data['percentage'],
    ];
}

function pqc_build_html_debug_table( $data ) {
    $html = '<h2>Debug Data</h2>';
    $html .= '<table style="width: 100%; border-collapse: collapse; font-family: monospace; font-size: 12px;">';
    foreach ( $data as $key => $value ) {
        $html .= '<tr style="background-color: #f2f2f2;">';
        $html .= '<td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;">' . esc_html( strtoupper( str_replace( '_', ' ', $key ) ) ) . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( is_bool($value) ? ($value ? 'true' : 'false') : $value ) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

// =========================================================================
// FUNCIÓN MANEJADORA (HANDLER)
// =========================================================================

/**
 * Función manejadora que se ejecuta cuando se completa un quiz.
 * Ahora acepta el objeto $user.
 */
function pqc_quiz_completed_handler( $quiz_data, $user ) {
    $debug = pqc_get_quiz_debug_data( $quiz_data, $user );

    $to      = get_option( 'admin_email' );
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( $debug['is_first_quiz'] ) {
        require_once plugin_dir_path( __FILE__ ) . 'emails/first-quiz-email.php';
        $email_content = pqc_get_first_quiz_email_content( $quiz_data, $user );
    } elseif ( $debug['is_final_quiz'] ) {
        require_once plugin_dir_path( __FILE__ ) . 'emails/final-quiz-email.php';
        $email_content = pqc_get_final_quiz_email_content( $quiz_data, $user );
    } else {
        return; // No enviar nada si no es ni First ni Final Quiz
    }

    wp_mail( $to, $email_content['subject'], $email_content['body'], $headers );
}

// Le decimos a WordPress que nuestra función ahora acepta 2 argumentos.
add_action( 'learndash_quiz_completed', 'pqc_quiz_completed_handler', 10, 2 ); // <-- CAMBIO: El '1' ahora es un '2'.