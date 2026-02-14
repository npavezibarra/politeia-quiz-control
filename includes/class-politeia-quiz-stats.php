<?php
if (!defined('ABSPATH')) {
    exit;
}

class Politeia_Quiz_Stats
{

    /**
     * Devuelve el activity_id del intento más reciente de un usuario en un quiz.
     *
     * @param int $user_id
     * @param int $quiz_id  ID real del CPT sfwd-quiz.
     * @return int|null     activity_id o null si no hay intentos.
     */
    public static function get_latest_attempt_id(int $user_id, int $quiz_id): ?int
    {
        global $wpdb;
        $ua = $wpdb->prefix . 'learndash_user_activity';

        return $wpdb->get_var($wpdb->prepare("
            SELECT activity_id
            FROM {$ua}
            WHERE user_id       = %d
              AND activity_type = 'quiz'
              AND post_id       = %d
              AND activity_completed > 0
            ORDER BY activity_completed DESC
            LIMIT 1
        ", $user_id, $quiz_id)) ?: null;
    }

    /**
     * Trae los metadatos percentage y points de un intento dado.
     *
     * @param int $activity_id
     * @return object|null  { percentage, points } o null si no existe.
     */
    public static function get_score_and_pct_by_activity(int $activity_id): ?object
    {
        global $wpdb;
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        return $wpdb->get_row($wpdb->prepare("
            SELECT
              MAX( CASE WHEN activity_meta_key = 'percentage' THEN activity_meta_value END ) AS percentage,
              MAX( CASE WHEN activity_meta_key = 'points'     THEN activity_meta_value END ) AS points
            FROM {$uam}
            WHERE activity_id = %d
        ", $activity_id));
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
    public static function get_all_attempts_data(int $user_id, int $quiz_id): array
    {
        global $wpdb;
        $ua = $wpdb->prefix . 'learndash_user_activity';
        $uam = $wpdb->prefix . 'learndash_user_activity_meta';

        // LearnDash stores quiz post ID in ua.post_id, NOT as a meta value
        $ids = $wpdb->get_col($wpdb->prepare("
            SELECT activity_id
            FROM {$ua}
            WHERE user_id       = %d
              AND activity_type = 'quiz'
              AND post_id       = %d
              AND activity_completed > 0
            ORDER BY activity_completed DESC
        ", $user_id, $quiz_id));

        if (empty($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $aid) {
            $row = $wpdb->get_row($wpdb->prepare("
                SELECT
                  MAX( CASE WHEN activity_meta_key = 'percentage' THEN activity_meta_value END ) AS pct,
                  MAX( CASE WHEN activity_meta_key = 'points'     THEN activity_meta_value END ) AS pts
                FROM {$uam}
                WHERE activity_id = %d
            ", $aid));

            $out[] = [
                'activity_id' => intval($aid),
                'percentage' => $row && $row->pct !== null
                    ? round(floatval($row->pct), 2) . '%'
                    : '–',
                'points' => $row && $row->pts !== null
                    ? intval($row->pts)
                    : 0,
            ];
        }

        return $out;
    }
}


/**
 * Representa un curso de LearnDash y proporciona métodos para acceder a sus datos relacionados.
 */
class PoliteiaCourse
{

    public $id = 0;
    public $post = null;

    public function __construct($course_id_or_post)
    {
        if ($course_id_or_post instanceof WP_Post) {
            $this->post = $course_id_or_post;
            $this->id = $this->post->ID;
        } else {
            $this->id = (int) $course_id_or_post;
            $this->post = get_post($this->id);
        }
    }

    public function getTitle()
    {
        return $this->post ? $this->post->post_title : 'Curso no encontrado';
    }

    public function getFirstQuizId()
    {
        return $this->post ? (int) get_post_meta($this->id, '_first_quiz_id', true) : 0;
    }

    public function getFinalQuizId()
    {
        return $this->post ? (int) get_post_meta($this->id, '_final_quiz_id', true) : 0;
    }

    public function getRelatedProductId()
    {
        $product_id = 0;
        $pq = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_related_course',
                    'value' => $this->id,
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if ($pq->have_posts()) {
            $product_id = $pq->posts[0];
        }
        return (int) $product_id;
    }

    /**
     * Verifica si el usuario está inscrito formalmente en el curso.
     */
    public function isUserEnrolled($user_id)
    {
        if (!function_exists('ld_course_check_user_access')) {
            return false;
        }

        $access = ld_course_check_user_access($this->id, $user_id);
        return isset($access['access']) && $access['access'] === true;
    }

    /**
     * Verifica si el usuario ha completado todas las lecciones del curso.
     */
    public function hasCompletedAllLessons($user_id)
    {
        $progress = learndash_course_progress([
            'course_id' => $this->id,
            'user_id' => $user_id,
            'array' => true,
        ]);

        return isset($progress['completed'], $progress['total'])
            && (int) $progress['total'] > 0
            && (int) $progress['completed'] >= (int) $progress['total'];
    }

    public static function find_course_id_by_quiz($quiz_id)
    {
        global $wpdb;

        // 1. Verifica si este quiz es un First Quiz
        $course_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_first_quiz_id'
               AND meta_value = %d
             LIMIT 1",
            $quiz_id
        ));

        if ($course_id) {
            return (int) $course_id;
        }

        // 2. Verifica si está en el builder del curso (como Final Quiz u otro)
        $course_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id
             FROM {$wpdb->prefix}learndash_course_steps
             WHERE step_id = %d
               AND step_type = 'quiz'
             LIMIT 1",
            $quiz_id
        ));

        return $course_id ? (int) $course_id : 0;
    }
}



/**
 * Servicio para encontrar órdenes de compra relevantes de un usuario.
 * Contiene la lógica para buscar órdenes normales y de tipo 'placeholder'.
 */
class PoliteiaOrderFinder
{

    public function findOrderForUser($user_id, $product_id)
    {
        global $wpdb;
        $order_id_found = 0;

        // Parte A) Búsqueda de órdenes normales (Depende de un producto)
        if ($product_id && function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer' => $user_id,
                'status' => ['pending', 'processing', 'completed', 'on-hold', 'course-on-hold'],
                'limit' => -1,
            ]);
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $pid = $item->get_meta('_product_id', true) ?: $item->get_product_id();
                    if ((int) $pid === (int) $product_id) {
                        $order_id_found = (int) $order->get_id();
                        break 2;
                    }
                }
            }
        }

        // Parte B) Fallback: Búsqueda de órdenes placeholder si no se encontró una orden normal
        if (!$order_id_found) {
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order_placehold' AND post_author = %d ORDER BY ID DESC LIMIT 1";
            $found = $wpdb->get_var($wpdb->prepare($sql, $user_id));
            if ($found) {
                $order_id_found = (int) $found;
            }
        }

        return $order_id_found;
    }
}