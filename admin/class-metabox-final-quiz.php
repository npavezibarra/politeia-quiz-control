<?php
/**
 * Metabox "Final Quiz" – Asocia un único quiz publicado como Final Quiz de un curso.
 *
 * @package PoliteiaQuizControl
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQCTL_Metabox_Final_Quiz
{

    const META_KEY = '_final_quiz_id';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_sfwd-courses', [$this, 'save_meta']);
    }

    public function add_metabox()
    {
        add_meta_box(
            'pqc_final_quiz',
            __('Final Quiz', 'politeia-quiz-control'),
            [$this, 'render'],
            'sfwd-courses',
            'side',
            'default'
        );
    }

    public function render($post)
    {
        $value = (int) get_post_meta($post->ID, self::META_KEY, true);

        wp_nonce_field('pqc_final_quiz_nonce', 'pqc_final_quiz_nonce_field');

        echo '<select name="' . esc_attr(self::META_KEY) . '" id="' . esc_attr(self::META_KEY) . '" class="pqc-select2" style="width:100%;" data-placeholder="—Ninguno—">';
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

        if (!isset($_POST['pqc_final_quiz_nonce_field']) || !wp_verify_nonce($_POST['pqc_final_quiz_nonce_field'], 'pqc_final_quiz_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $new = isset($_POST[self::META_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::META_KEY])) : '';

        if ('' === $new) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        $new = absint($new);
        $post = get_post($new);

        if ($post && 'sfwd-quiz' === $post->post_type && 'publish' === $post->post_status) {
            update_post_meta($post_id, self::META_KEY, $new);
        }
    }
}
