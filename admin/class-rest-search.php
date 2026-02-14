<?php
/**
 * Clase PQC_REST_Search
 * @package PoliteiaQuizControl
 * PQC_REST_Search crea y gestiona el endpoint REST que alimenta los campos Select2 del plugin. 
 * Al instanciarse, registra la ruta /wp-json/politeia-quiz-control/v1/quiz-search, accesible mediante peticiones GET. 
 * El endpoint acepta dos parámetros (term y page), verifica que el usuario tenga la capacidad edit_posts, 
 * ejecuta una consulta WP_Query paginada sobre los quizzes publicados (sfwd-quiz) y devuelve un JSON compacto 
 * con la forma { results: [ {id, text}, … ], more: bool }. Esa respuesta encaja con la firma que Select2 espera 
 * para autocompletar, permitiendo buscar y paginar miles de quizzes sin recargar la pantalla y 
 * sin exponer datos a usuarios sin permisos.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PQCTL_REST_Search
{

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route()
    {
        register_rest_route(
            'politeia-quiz-control/v1',
            '/quiz-search',
            [
                'methods' => 'GET',
                'callback' => [$this, 'handle_search'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args' => [
                    'term' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'page' => [
                        'sanitize_callback' => 'absint',
                        'default' => 1,
                    ],
                ],
            ]
        );
    }

    public function handle_search(WP_REST_Request $request)
    {
        $term = $request['term'] ?: '';
        $page = max(1, (int) $request['page']);

        $q = new WP_Query([
            'post_type' => 'sfwd-quiz',
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => 20,
            'paged' => $page,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $results = array_map(
            fn($id) => ['id' => $id, 'text' => get_the_title($id)],
            $q->posts
        );

        return rest_ensure_response([
            'results' => $results,                 // 👈  ahora coincide con admin.js
            'more' => $page < $q->max_num_pages
        ]);
    }
}
