<?php
/**
 * Metabox "Course Evaluation" - Associates a single quiz as both First and Final quiz.
 *
 * @package PoliteiaQuizControl
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQCTL_Metabox_Evaluation
{

    const META_KEY_FIRST = '_first_quiz_id';
    const META_KEY_FINAL = '_final_quiz_id';
    const NONCE_ACTION = 'pqctl_evaluation_nonce';
    const NONCE_FIELD = 'pqctl_evaluation_nonce_field';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_sfwd-courses', [$this, 'save_meta']);
    }

    public function add_metabox()
    {
        add_meta_box(
            'pqctl_course_evaluation',
            __('Course Evaluation', 'politeia-quiz-control'),
            [$this, 'render'],
            'sfwd-courses',
            'side',
            'default'
        );
    }

    public function render($post)
    {
        // We use First Quiz ID as the source of truth for the "Evaluation" since they should be identical.
        $value = (int) get_post_meta($post->ID, self::META_KEY_FIRST, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        echo '<p class="description" style="margin-bottom:10px;">' .
            esc_html__('Select the quiz that will serve as both the Initial Assessment and Final Exam.', 'politeia-quiz-control') .
            '</p>';

        echo '<select name="pqctl_evaluation_quiz_id" class="pqc-select2" style="width:100%;" data-placeholder="—Ninguno—">';
        echo '<option value="">—Ninguno—</option>';

        if ($value) {
            $title = get_the_title($value);
            if ($title) {
                echo '<option value="' . esc_attr($value) . '" selected>' . esc_html($title) . '</option>';
            }
        }
        echo '</select>';
    }

    public function save_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $new_val = isset($_POST['pqctl_evaluation_quiz_id']) ? sanitize_text_field(wp_unslash($_POST['pqctl_evaluation_quiz_id'])) : '';

        if ('' === $new_val) {
            delete_post_meta($post_id, self::META_KEY_FIRST);
            delete_post_meta($post_id, self::META_KEY_FINAL);
            return;
        }

        $new_id = absint($new_val);
        $post = get_post($new_id);

        if ($post && 'sfwd-quiz' === $post->post_type && 'publish' === $post->post_status) {
            // Save the SAME ID to both meta keys to maintain backward compatibility
            update_post_meta($post_id, self::META_KEY_FIRST, $new_id);
            update_post_meta($post_id, self::META_KEY_FINAL, $new_id);
        }
    }
}
