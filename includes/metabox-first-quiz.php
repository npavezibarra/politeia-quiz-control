<?php
// Add metabox
add_action( 'add_meta_boxes', 'politeia_add_first_quiz_metabox' );
function politeia_add_first_quiz_metabox() {
    add_meta_box(
        'politeia_first_quiz_metabox',
        'First Quiz',
        'politeia_render_first_quiz_metabox',
        'sfwd-courses',
        'side',
        'default'
    );
}

// Render metabox content
function politeia_render_first_quiz_metabox( $post ) {
    $selected_quiz = get_post_meta( $post->ID, '_first_quiz_id', true );

    // Get all quizzes
    $quizzes = get_posts([
        'post_type' => 'sfwd-quiz',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    echo '<label for="politeia_first_quiz_select">Select First Quiz:</label><br>';
    echo '<select name="politeia_first_quiz_select" id="politeia_first_quiz_select" style="width:100%;">';
    echo '<option value="">-- None --</option>';

    foreach ( $quizzes as $quiz ) {
        $selected = selected( $selected_quiz, $quiz->ID, false );
        echo "<option value='{$quiz->ID}' {$selected}>{$quiz->post_title}</option>";
    }

    echo '</select>';

    // Nonce for security
    wp_nonce_field( 'politeia_save_first_quiz', 'politeia_first_quiz_nonce' );
}

// Save selected quiz
add_action( 'save_post_sfwd-courses', 'politeia_save_first_quiz_metabox' );
function politeia_save_first_quiz_metabox( $post_id ) {
    if ( ! isset( $_POST['politeia_first_quiz_nonce'] ) ||
         ! wp_verify_nonce( $_POST['politeia_first_quiz_nonce'], 'politeia_save_first_quiz' ) ) {
        return;
    }

    if ( isset( $_POST['politeia_first_quiz_select'] ) ) {
        $quiz_id = absint( $_POST['politeia_first_quiz_select'] );
        if ( $quiz_id > 0 ) {
            update_post_meta( $post_id, '_first_quiz_id', $quiz_id );
        } else {
            delete_post_meta( $post_id, '_first_quiz_id' );
        }
    }
}
