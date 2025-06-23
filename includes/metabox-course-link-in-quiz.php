<?php
add_action('add_meta_boxes', 'politeia_add_course_link_metabox');
function politeia_add_course_link_metabox() {
    add_meta_box(
        'politeia_course_link',
        'Linked Course (First Quiz)',
        'politeia_render_course_link_metabox',
        'sfwd-quiz',
        'side',
        'default'
    );
}

function politeia_render_course_link_metabox($post) {
    // Find the course where this quiz is set as First Quiz
    $linked_course = get_posts([
        'post_type'  => 'sfwd-courses',
        'meta_key'   => '_first_quiz_id',
        'meta_value' => $post->ID,
        'numberposts' => 1,
    ]);
    $course_id = $linked_course ? $linked_course[0]->ID : '';

    // Output the Select2 field
    echo '<label for="linked_course_id">Select course:</label>';
    echo '<select id="linked_course_id" name="linked_course_id" style="width:100%;">';
    if ($course_id) {
        echo '<option value="' . esc_attr($course_id) . '" selected>' . esc_html(get_the_title($course_id)) . '</option>';
    }
    echo '</select>';

    // Nonce
    wp_nonce_field('politeia_save_quiz_course_link', 'politeia_quiz_course_nonce');
}

add_action('save_post_sfwd-quiz', 'politeia_save_course_link_metabox');
function politeia_save_course_link_metabox($post_id) {
    if (!isset($_POST['politeia_quiz_course_nonce']) || !wp_verify_nonce($_POST['politeia_quiz_course_nonce'], 'politeia_save_quiz_course_link')) {
        return;
    }

    $new_course_id = isset($_POST['linked_course_id']) ? absint($_POST['linked_course_id']) : 0;

    // Clear any old link pointing to this quiz
    $old_courses = get_posts([
        'post_type'  => 'sfwd-courses',
        'meta_key'   => '_first_quiz_id',
        'meta_value' => $post_id,
        'numberposts' => -1,
        'fields'     => 'ids',
    ]);
    foreach ($old_courses as $course_id) {
        delete_post_meta($course_id, '_first_quiz_id');
    }

    if ($new_course_id) {
        update_post_meta($new_course_id, '_first_quiz_id', $post_id);
    }
}
