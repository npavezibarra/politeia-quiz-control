<?php
add_action( 'add_meta_boxes', 'politeia_add_final_quiz_metabox' );
function politeia_add_final_quiz_metabox() {
    add_meta_box(
        'politeia_final_quiz_metabox',
        'Final Quiz',
        'politeia_render_final_quiz_metabox',
        'sfwd-courses',
        'side',
        'default'
    );
}

function politeia_render_final_quiz_metabox( $post ) {
    $selected_quiz = get_post_meta( $post->ID, '_final_quiz_id', true );
    $selected_title = $selected_quiz ? get_the_title($selected_quiz) : '';

    echo '<label for="politeia_final_quiz_select">Select Final Quiz:</label><br>';
    echo '<select id="politeia_final_quiz_select" name="politeia_final_quiz_select" style="width:100%;">';

    if ($selected_quiz) {
        echo '<option value="' . esc_attr($selected_quiz) . '" selected>' . esc_html($selected_title) . '</option>';
    }

    echo '</select>';
    wp_nonce_field( 'politeia_save_final_quiz', 'politeia_final_quiz_nonce' );
}

add_action( 'save_post_sfwd-courses', 'politeia_save_final_quiz_metabox' );
function politeia_save_final_quiz_metabox( $post_id ) {
    if ( ! isset( $_POST['politeia_final_quiz_nonce'] ) ||
         ! wp_verify_nonce( $_POST['politeia_final_quiz_nonce'], 'politeia_save_final_quiz' ) ) {
        return;
    }

    $quiz_id = isset($_POST['politeia_final_quiz_select']) ? absint($_POST['politeia_final_quiz_select']) : 0;

    if ($quiz_id) {
        update_post_meta($post_id, '_final_quiz_id', $quiz_id);
    } else {
        delete_post_meta($post_id, '_final_quiz_id');
    }
}
