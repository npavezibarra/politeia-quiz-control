<?php
/**
 * Metabox "First Quiz" – Asocia un único quiz publicado como First Quiz de un curso.
 *
 * @package PoliteiaQuizControl
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQCTL_Metabox_First_Quiz
{

    const META_KEY = '_first_quiz_id';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_sfwd-courses', [$this, 'save_meta']);
    }

    /**
     * Añade el metabox al sidebar de la pantalla de edición de cursos.
     */
    public function add_metabox()
    {
        add_meta_box(
            'pqc_first_quiz',                       // ID
            __('First Quiz', 'politeia-quiz-control'), // Título
            [$this, 'render'],                    // Callback
            'sfwd-courses',                        // Post type
            'side',                                // Context
            'default'                              // Prioridad
        );
    }

    /**
     * Renderiza el campo <select> con Select2.
     *
     * @param WP_Post $post El post del curso que se está editando.
     */
    public function render($post)
    {
        // Valor guardado.
        $value = (int) get_post_meta($post->ID, self::META_KEY, true);

        // Nonce.
        wp_nonce_field('pqc_first_quiz_nonce', 'pqc_first_quiz_nonce_field');

        // <select> vacío, Select2 lo llenará vía AJAX. Si hay valor guardado,
        // lo mostramos como opción seleccionada para que aparezca visible.
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

    /**
     * Guarda o elimina la meta key al guardar el curso.
     *
     * @param int $post_id ID del curso que se está guardando.
     */
    public function save_meta($post_id)
    {
        // Autosave o sin nonce válido.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['pqc_first_quiz_nonce_field']) || !wp_verify_nonce($_POST['pqc_first_quiz_nonce_field'], 'pqc_first_quiz_nonce')) {
            return;
        }

        // Comprobación de capacidad.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Nuevo valor enviado.
        $new = isset($_POST[self::META_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::META_KEY])) : '';

        // Si está vacío, eliminamos la meta.
        if ('' === $new) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        // Validamos que sea un quiz publicado.
        $new = absint($new);
        $post = get_post($new);

        if ($post && 'sfwd-quiz' === $post->post_type && 'publish' === $post->post_status) {
            update_post_meta($post_id, self::META_KEY, $new);
        }
    }
}
