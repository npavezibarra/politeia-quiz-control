<?php
add_action('wp_ajax_politeia_search_courses', function() {
    check_ajax_referer('politeia_ajax_nonce', 'nonce');

    $search = sanitize_text_field($_GET['q'] ?? '');

    $courses = get_posts([
        'post_type'   => 'sfwd-courses',
        'post_status' => 'publish',
        's'           => $search,
        'numberposts' => 20,
    ]);

    $results = array_map(function($course) {
        return [
            'id'   => $course->ID,
            'text' => $course->post_title
        ];
    }, $courses);

    wp_send_json($results);
});
