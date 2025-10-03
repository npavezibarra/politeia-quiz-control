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

    /**
     * Retrieves the latest completed attempt summary for a user on a quiz.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @return array|null
     */
    public static function get_latest_attempt_summary( int $user_id, int $quiz_id ): ?array {
        if ( ! $user_id || ! $quiz_id ) {
            return null;
        }

        global $wpdb;

        $ua  = $wpdb->prefix . 'learndash_user_activity';
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        $attempt = $wpdb->get_row( $wpdb->prepare(
            "SELECT ua.activity_id, ua.activity_started, ua.activity_completed
             FROM {$ua} ua
             INNER JOIN {$uam} m_quiz
               ON m_quiz.activity_id = ua.activity_id
              AND m_quiz.activity_meta_key = 'quiz'
              AND m_quiz.activity_meta_value = %d
             WHERE ua.user_id = %d
               AND ua.activity_type = 'quiz'
             ORDER BY ua.activity_completed DESC, ua.activity_id DESC
             LIMIT 1",
            $quiz_id,
            $user_id
        ) );

        if ( ! $attempt ) {
            return null;
        }

        $meta_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT activity_meta_key, activity_meta_value
             FROM {$uam}
             WHERE activity_id = %d",
            $attempt->activity_id
        ) );

        if ( empty( $meta_rows ) ) {
            return null;
        }

        $meta = [];
        foreach ( $meta_rows as $row ) {
            $meta[ $row->activity_meta_key ] = $row->activity_meta_value;
        }

        $percentage   = isset( $meta['percentage'] ) ? round( floatval( $meta['percentage'] ) ) : 0;
        $score        = isset( $meta['score'] ) ? intval( $meta['score'] ) : 0;
        $total_points = isset( $meta['total_points'] ) ? intval( $meta['total_points'] ) : 0;

        return [
            'activity_id'         => (int) $attempt->activity_id,
            'percentage'          => $percentage,
            'score'               => $score,
            'total_points'        => $total_points,
            'passed'              => isset( $meta['pass'] ) ? (bool) intval( $meta['pass'] ) : false,
            'started_timestamp'   => (int) $attempt->activity_started,
            'completed_timestamp' => (int) $attempt->activity_completed,
            'started'             => $attempt->activity_started
                ? wp_date( 'Y-m-d H:i:s', (int) $attempt->activity_started )
                : '',
            'completed'           => $attempt->activity_completed
                ? wp_date( 'Y-m-d H:i:s', (int) $attempt->activity_completed )
                : '',
            'duration'            => ( $attempt->activity_completed && $attempt->activity_started )
                ? max( 0, (int) $attempt->activity_completed - (int) $attempt->activity_started )
                : 0,
        ];
    }
}


/**
 * Representa un curso de LearnDash y proporciona métodos para acceder a sus datos relacionados.
 */
class PoliteiaCourse {

    public $id = 0;
    public $post = null;

    public function __construct( $course_id_or_post ) {
        if ( $course_id_or_post instanceof WP_Post ) {
            $this->post = $course_id_or_post;
            $this->id   = $this->post->ID;
        } else {
            $this->id   = (int) $course_id_or_post;
            $this->post = get_post( $this->id );
        }
    }

    public function getTitle() {
        return $this->post ? $this->post->post_title : 'Curso no encontrado';
    }

    public function __call( $name, $arguments ) {
        if ( 'getFirstQuizId' === $name ) {
            return self::getFirstQuizId( $this->id );
        }

        if ( 'getFinalQuizId' === $name ) {
            return self::getFinalQuizId( $this->id );
        }

        return null;
    }

    public static function getFirstQuizId( int $course_id ): int {
        return self::getQuizMetaId( $course_id, '_first_quiz_id' );
    }

    public static function getFinalQuizId( int $course_id ): int {
        return self::getQuizMetaId( $course_id, '_final_quiz_id' );
    }

    protected static function getQuizMetaId( int $course_id, string $meta_key ): int {
        if ( ! $course_id ) {
            return 0;
        }

        $value = get_post_meta( $course_id, $meta_key, true );

        return $value ? (int) $value : 0;
    }

    public function getRelatedProductId() {
        $product_id = 0;
        $pq = new WP_Query( [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_related_course',
                    'value'   => $this->id,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        if ( $pq->have_posts() ) {
            $product_id = $pq->posts[0];
        }
        return (int) $product_id;
    }

    /**
     * Verifica si el usuario está inscrito formalmente en el curso.
     */
    public function isUserEnrolled( $user_id ) {
        if ( ! function_exists( 'ld_course_check_user_access' ) ) {
            return false;
        }

        $access = ld_course_check_user_access( $this->id, $user_id );
        return isset( $access['access'] ) && $access['access'] === true;
    }

    /**
     * Verifica si el usuario ha completado todas las lecciones del curso.
     */
    public function hasCompletedAllLessons( $user_id ) {
        $progress = learndash_course_progress( [
            'course_id' => $this->id,
            'user_id'   => $user_id,
            'array'     => true,
        ] );

        return isset( $progress['completed'], $progress['total'] )
            && (int) $progress['total'] > 0
            && (int) $progress['completed'] >= (int) $progress['total'];
    }

    public static function getCourseFromQuiz( int $quiz_id ): int {
        if ( ! $quiz_id ) {
            return 0;
        }

        global $wpdb;

        $course_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id
             FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_first_quiz_id', '_final_quiz_id')
               AND meta_value = %d
             LIMIT 1",
            $quiz_id
        ) );

        if ( $course_id ) {
            return (int) $course_id;
        }

        return 0;
    }

    public static function find_course_id_by_quiz( $quiz_id ) {
        return self::getCourseFromQuiz( (int) $quiz_id );
    }
}

class Politeia_Quiz_Analytics {

    protected $quiz_id = 0;
    protected $course_id = 0;
    protected $first_quiz_id = 0;
    protected $final_quiz_id = 0;

    public function __construct( int $quiz_id ) {
        $this->quiz_id   = $quiz_id;
        $this->course_id = PoliteiaCourse::getCourseFromQuiz( $quiz_id );

        if ( $this->course_id ) {
            $this->first_quiz_id = PoliteiaCourse::getFirstQuizId( $this->course_id );
            $this->final_quiz_id = PoliteiaCourse::getFinalQuizId( $this->course_id );
        }
    }

    public function getQuizId(): int {
        return $this->quiz_id;
    }

    public function getCourseId(): int {
        return $this->course_id;
    }

    public function isFirstQuiz(): bool {
        return $this->quiz_id && $this->first_quiz_id && (int) $this->quiz_id === (int) $this->first_quiz_id;
    }

    public function isFinalQuiz(): bool {
        return $this->quiz_id && $this->final_quiz_id && (int) $this->quiz_id === (int) $this->final_quiz_id;
    }

    public function getFirstQuizId(): int {
        return $this->first_quiz_id;
    }

    public function getFinalQuizId(): int {
        return $this->final_quiz_id;
    }

    public function getFirstQuizData( int $user_id ): array {
        return $this->getQuizData( $user_id, $this->first_quiz_id );
    }

    public function getFinalQuizData( int $user_id ): array {
        return $this->getQuizData( $user_id, $this->final_quiz_id );
    }

    protected function getQuizData( int $user_id, int $quiz_id ): array {
        $attempts = [];
        $latest   = null;

        if ( $user_id && $quiz_id && class_exists( 'Politeia_Quiz_Stats' ) ) {
            $attempts = Politeia_Quiz_Stats::get_all_attempts_data( $user_id, $quiz_id );
            $latest   = $attempts[0] ?? null;
        }

        return [
            'quiz_id'        => $quiz_id,
            'attempts'       => $attempts,
            'latest_attempt' => $latest,
        ];
    }
}



/**
 * Servicio para encontrar órdenes de compra relevantes de un usuario.
 * Contiene la lógica para buscar órdenes normales y de tipo 'placeholder'.
 */
class PoliteiaOrderFinder {

    public function findOrderForUser( $user_id, $product_id ) {
        global $wpdb;
        $order_id_found = 0;

        // Parte A) Búsqueda de órdenes normales (Depende de un producto)
        if ( $product_id && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( [
                'customer' => $user_id,
                'status'   => [ 'pending', 'processing', 'completed', 'on-hold', 'course-on-hold' ],
                'limit'    => -1,
            ] );
            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $pid = $item->get_meta( '_product_id', true ) ?: $item->get_product_id();
                    if ( (int) $pid === (int) $product_id ) {
                        $order_id_found = (int) $order->get_id();
                        break 2;
                    }
                }
            }
        }

        // Parte B) Fallback: Búsqueda de órdenes placeholder si no se encontró una orden normal
        if ( ! $order_id_found ) {
            $sql   = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order_placehold' AND post_author = %d ORDER BY ID DESC LIMIT 1";
            $found = $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
            if ( $found ) {
                $order_id_found = (int) $found;
            }
        }
        
        return $order_id_found;
    }
}