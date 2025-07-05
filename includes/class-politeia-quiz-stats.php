<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Politeia_Quiz_Stats {

    /**
     * Devuelve el activity_id del intento más reciente de un usuario en un quiz.
     *
     * @param int $user_id
     * @param int $quiz_id  ID real del CPT sfwd-quiz.
     * @return int|null     activity_id o null si no hay intentos.
     */
    public static function get_latest_attempt_id( int $user_id, int $quiz_id ): ?int {
        global $wpdb;
        $ua  = $wpdb->prefix . 'learndash_user_activity';
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        return $wpdb->get_var( $wpdb->prepare( "
            SELECT ua.activity_id
            FROM {$ua} ua
            INNER JOIN {$uam} m_quiz
              ON m_quiz.activity_id = ua.activity_id
             AND m_quiz.activity_meta_key   = 'quiz'
             AND m_quiz.activity_meta_value = %d
            WHERE ua.user_id       = %d
              AND ua.activity_type = 'quiz'
            ORDER BY ua.activity_completed DESC
            LIMIT 1
        ", $quiz_id, $user_id ) ) ?: null;
    }

    /**
     * Trae los metadatos percentage y points de un intento dado.
     *
     * @param int $activity_id
     * @return object|null  { percentage, points } o null si no existe.
     */
    public static function get_score_and_pct_by_activity( int $activity_id ): ?object {
        global $wpdb;
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        return $wpdb->get_row( $wpdb->prepare( "
            SELECT
              MAX( CASE WHEN activity_meta_key = 'percentage' THEN activity_meta_value END ) AS percentage,
              MAX( CASE WHEN activity_meta_key = 'points'     THEN activity_meta_value END ) AS points
            FROM {$uam}
            WHERE activity_id = %d
        ", $activity_id ) );
    }

    /**
     * Recupera todos los intentos de un usuario en un quiz,
     * con percentage y points ya formateados.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @return array  Cada elemento: [
     *     'activity_id' => int,
     *     'percentage'  => string, // e.g. "100%"
     *     'points'      => int
     * ]
     */
    public static function get_all_attempts_data( int $user_id, int $quiz_id ): array {
        global $wpdb;
        $ua  = $wpdb->prefix . 'learndash_user_activity';
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        // 1) Sacamos todos los activity_id ordenados
        $ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT ua.activity_id
            FROM {$ua} ua
            INNER JOIN {$uam} m_quiz
              ON m_quiz.activity_id       = ua.activity_id
             AND m_quiz.activity_meta_key   = 'quiz'
             AND m_quiz.activity_meta_value = %d
            WHERE ua.user_id       = %d
              AND ua.activity_type = 'quiz'
            ORDER BY ua.activity_completed DESC
        ", $quiz_id, $user_id ) );

        if ( empty( $ids ) ) {
            return [];
        }

        $out = [];
        foreach ( $ids as $aid ) {
            $row = $wpdb->get_row( $wpdb->prepare( "
                SELECT
                  MAX( CASE WHEN activity_meta_key = 'percentage' THEN activity_meta_value END ) AS pct,
                  MAX( CASE WHEN activity_meta_key = 'points'     THEN activity_meta_value END ) AS pts
                FROM {$uam}
                WHERE activity_id = %d
            ", $aid ) );

            $out[] = [
                'activity_id' => intval( $aid ),
                'percentage'  => $row && $row->pct !== null
                                  ? round( floatval( $row->pct ), 2 ) . '%'
                                  : '–',
                'points'      => $row && $row->pts !== null
                                  ? intval( $row->pts )
                                  : 0,
            ];
        }

        return $out;
    }
}
