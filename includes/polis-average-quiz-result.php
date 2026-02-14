<?php
/**
 * Renderiza la tabla de intentos + promedio,
 * y expone el promedio en $GLOBALS['polis_quiz_last_average']
 * y en la propiedad estática self::$last_average.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Polis_Quiz_Attempts_Shortcode
{

    /** Último promedio calculado (int) */
    public static $last_average = 0;

    /**
     * Renderiza la tabla de intentos + promedio.
     *
     * @param array $atts
     * @return string
     */
    public static function render($atts)
    {
        global $wpdb;

        // Inicializar variable global
        $GLOBALS['polis_quiz_last_average'] = 0;

        $atts = shortcode_atts(['id' => 0], $atts, 'polis_quiz_attempts');
        $quiz_id = intval($atts['id']);
        if (!$quiz_id) {
            return '<p style="color:red;">Quiz ID inválido.</p>';
        }

        // 1) Traer todos los intentos completados, ordenados por FECHA ASCENDENTE
        // Para tomar el PRIMER intento de cada usuario.
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT ua.activity_id, ua.user_id, ua.activity_completed
            FROM {$wpdb->prefix}learndash_user_activity AS ua
            INNER JOIN {$wpdb->prefix}learndash_user_activity_meta AS uam
                ON ua.activity_id = uam.activity_id
            WHERE ua.activity_type = 'quiz'
              AND ua.activity_completed IS NOT NULL
              AND uam.activity_meta_key = 'quiz'
              AND uam.activity_meta_value+0 = %d
            ORDER BY ua.activity_completed ASC
        ", $quiz_id));

        if (empty($rows)) {
            return '<p>No hay intentos registrados para este quiz.</p>';
        }

        // 2) Construir la tabla
        $html = '<table class="polis-quiz-attempts" style="width:100%; border-collapse: collapse;">';
        $html .= '<thead><tr>';
        // --- NUEVA COLUMNA: Activity ID ---
        $html .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Activity ID</th>';
        // --- Columnas Existentes ---
        $html .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Usuario</th>';
        $html .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Fecha y hora</th>';
        $html .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">Puntaje %</th>';
        $html .= '</tr></thead><tbody>';

        $sum = 0;
        $count = 0;
        $seen_users = [];

        foreach ($rows as $row) {
            // Si ya vimos a este usuario, saltar (porque estamos ordenando ASC, el primero es el 'First Attempt')
            if (isset($seen_users[$row->user_id])) {
                continue;
            }
            $seen_users[$row->user_id] = true;

            // Nombre de usuario
            $user = get_userdata($row->user_id);
            $user_name = $user ? esc_html($user->display_name) : esc_html($row->user_id);

            // Porcentaje
            $pct = $wpdb->get_var($wpdb->prepare("
                SELECT activity_meta_value
                FROM {$wpdb->prefix}learndash_user_activity_meta
                WHERE activity_id = %d
                  AND activity_meta_key = 'percentage'
                LIMIT 1
            ", $row->activity_id));
            $pct_val = $pct !== null ? floatval($pct) : 0;

            $sum += $pct_val;
            $count++;

            $pct_fmt = round($pct_val) . '%';

            // Fecha
            $date = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($row->activity_completed)
            );

            $html .= '<tr>';
            // --- NUEVA CELDA: Activity ID ---
            $html .= '<td style="border:1px solid #ddd; padding:8px;">' . esc_html($row->activity_id) . '</td>';
            // --- Celdas Existentes ---
            $html .= '<td style="border:1px solid #ddd; padding:8px;">' . $user_name . '</td>';
            $html .= '<td style="border:1px solid #ddd; padding:8px;">' . esc_html($date) . '</td>';
            $html .= '<td style="border:1px solid #ddd; padding:8px;">' . esc_html($pct_fmt) . '</td>';
            $html .= '</tr>';
        }

        // 3) Fila de promedio
        $avg = $count > 0 ? round($sum / $count) : 0;

        // Guardar en variable global y propiedad estática
        $GLOBALS['polis_quiz_last_average'] = $avg;
        self::$last_average = $avg;

        $html .= '<tr style="background:#f9f9f9;">';
        // Ajustar colspan para la nueva columna
        $html .= '<th colspan="3" style="border:1px solid #ddd; padding:8px; text-align:right;">Promedios Polis</th>';
        $html .= '<th style="border:1px solid #ddd; padding:8px; text-align:left;">' . esc_html($avg) . '%</th>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        return $html;
    }
}

// Registrar shortcode (asegúrate de que esto ya esté en tu plugin y se cargue)
add_action('init', function () {
    add_shortcode('polis_quiz_attempts', ['Polis_Quiz_Attempts_Shortcode', 'render']);
});