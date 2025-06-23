<?php
add_action('wp_ajax_politeia_search_final_quizzes', function() {
    check_ajax_referer('politeia_ajax_nonce', 'nonce');

    $search = sanitize_text_field($_GET['q'] ?? '');

    $quizzes = get_posts([
        'post_type' => 'sfwd-quiz',
        'post_status' => 'publish',
        's' => $search,
        'numberposts' => 20,
    ]);

    $results = array_map(function($quiz) {
        return [
            'id' => $quiz->ID,
            'text' => $quiz->post_title
        ];
    }, $quizzes);

    wp_send_json($results);
});
