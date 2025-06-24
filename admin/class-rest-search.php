<?php
/**
 * Clase PQC_REST_Search
 * @package PoliteiaQuizControl
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQC_REST_Search {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
    }

    public function register_route() {
        register_rest_route(
            'politeia-quiz-control/v1',
            '/quiz-search',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'handle_search' ],
                'permission_callback' => fn () => current_user_can( 'edit_posts' ),
                'args' => [
                    'term' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'page' => [
                        'sanitize_callback' => 'absint',
                        'default'           => 1,
                    ],
                ],
            ]
        );
    }

    public function handle_search( WP_REST_Request $request ) {
        $term = $request['term'] ?: '';
        $page = max( 1, (int) $request['page'] );

        $q = new WP_Query( [
            'post_type'      => 'sfwd-quiz',
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => 20,
            'paged'          => $page,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $results = array_map(
            fn( $id ) => [ 'id' => $id, 'text' => get_the_title( $id ) ],
            $q->posts
        );

        return rest_ensure_response( [
            'results' => $results,                 // 👈  ahora coincide con admin.js
            'more'    => $page < $q->max_num_pages
        ] );
    }
}
