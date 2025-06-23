<?php
/**
 * Plugin Name: Politeia Quiz Control
 * Description: Links First Quiz and Final Quiz to LearnDash courses for the Politeia platform.
 * Version: 1.0.0
 * Author: Politeia
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*--------------------------------------------------------------
>>> DEFINITIONS
--------------------------------------------------------------*/
define( 'POLITEIA_QUIZ_CONTROL_VERSION', '1.0.0' );
define( 'POLITEIA_QUIZ_CONTROL_PATH', plugin_dir_path( __FILE__ ) );
define( 'POLITEIA_QUIZ_CONTROL_URL', plugin_dir_url( __FILE__ ) );

/*--------------------------------------------------------------
>>> ACTIVATION HOOK
--------------------------------------------------------------*/
register_activation_hook( __FILE__, 'politeia_quiz_control_activate' );
function politeia_quiz_control_activate() {
    // Setup or migrate future data here
}

/*--------------------------------------------------------------
>>> REGISTER META
--------------------------------------------------------------*/
add_action( 'init', 'politeia_quiz_control_register_meta' );
function politeia_quiz_control_register_meta() {
    register_post_meta( 'sfwd-courses', '_first_quiz_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => function() {
            return current_user_can( 'edit_sfwd-courses' );
        }
    ] );

    register_post_meta( 'sfwd-courses', '_final_quiz_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => function() {
            return current_user_can( 'edit_sfwd-courses' );
        }
    ] );
}

/*--------------------------------------------------------------
>>> ENQUEUE ADMIN SCRIPTS (Select2 for Quiz Page)
--------------------------------------------------------------*/
add_action('admin_enqueue_scripts', function($hook) {
    $screen = get_current_screen();
    if ( in_array($hook, ['post.php', 'post-new.php']) && $screen ) {
        if ($screen->post_type === 'sfwd-courses') {
            wp_enqueue_script('select2');
            wp_enqueue_style('select2');

            wp_enqueue_script(
                'politeia-quiz-select',
                POLITEIA_QUIZ_CONTROL_URL . 'assets/quiz-select.js',
                ['jquery', 'select2'],
                null,
                true
            );
            wp_localize_script('politeia-quiz-select', 'PoliteiaQuizSelect', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('politeia_ajax_nonce'),
            ]);

            wp_enqueue_script(
                'politeia-final-quiz-select',
                POLITEIA_QUIZ_CONTROL_URL . 'assets/final-quiz-select.js',
                ['jquery', 'select2'],
                null,
                true
            );
            wp_localize_script('politeia-final-quiz-select', 'PoliteiaFinalQuizSelect', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('politeia_ajax_nonce'),
            ]);
        }
    }
});

/*--------------------------------------------------------------
>>> INCLUDE FILES
--------------------------------------------------------------*/
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/metabox-first-quiz.php';
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/metabox-final-quiz.php';
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/metabox-course-link-in-quiz.php';
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/ajax-quiz-search.php';
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/ajax-final-quiz-search.php';
require_once POLITEIA_QUIZ_CONTROL_PATH . 'includes/ajax-course-search.php';
