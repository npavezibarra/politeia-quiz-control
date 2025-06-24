<?php
/**
 * Plugin Name:       Politeia Quiz Control
 * Description:       Metaboxes "First Quiz" y "Final Quiz" con Select2 (AJAX) para asociar quizzes publicados a los cursos de LearnDash.
 * Version:           1.0.0
 * Author:            Nico Pavez
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       politeia-quiz-control
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Constantes
// -----------------------------------------------------------------------------

define( 'PQC_PLUGIN_FILE', __FILE__ );
define( 'PQC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PQC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PQC_VERSION', '1.0.0' );

// -----------------------------------------------------------------------------
// Carga de clases siempre disponibles
// -----------------------------------------------------------------------------

require_once PQC_PLUGIN_DIR . 'admin/class-rest-search.php';
new PQC_REST_Search(); // <-- la ruta REST debe estar disponible dentro y fuera del admin

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------

class PQC_Bootstrap {

    public function __construct() {
        add_action( 'plugins_loaded',       [ $this, 'load_textdomain' ] );
        add_action( 'admin_enqueue_scripts',[ $this, 'enqueue_admin_assets' ] );
        add_action( 'init',                 [ $this, 'load_admin_classes' ], 20 );
    }

    /**
     * Carga traducciones.
     */
    public function load_textdomain() : void {
        load_plugin_textdomain( 'politeia-quiz-control', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Encola CSS/JS sólo en la edición de cursos.
     */
    public function enqueue_admin_assets( string $hook ) : void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || 'sfwd-courses' !== $screen->post_type ) {
            return;
        }

        // Select2 desde CDN (rápido de implementar)
        wp_enqueue_style( 'pqc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0' );
        wp_enqueue_script( 'pqc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], '4.1.0', true );
        wp_enqueue_script( 'pqc-select2-es', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js', [ 'pqc-select2' ], '4.1.0', true );

        // Assets propios
        wp_enqueue_style( 'pqc-admin', PQC_PLUGIN_URL . 'assets/admin.css', [ 'pqc-select2' ], PQC_VERSION );
        wp_enqueue_script( 'pqc-admin', PQC_PLUGIN_URL . 'assets/admin.js', [ 'jquery', 'pqc-select2' ], PQC_VERSION, true );
        wp_localize_script( 'pqc-admin', 'pqcData', [
            'restUrl'  => esc_url_raw( rest_url( 'politeia-quiz-control/v1/quiz-search' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'i18n'     => [
                'searching'  => __( 'Buscando…', 'politeia-quiz-control' ),
                'noResults'  => __( 'No se encontraron resultados', 'politeia-quiz-control' ),
            ],
        ] );
    }

    /**
     * Carga clases y funcionalidades exclusivas del administrador.
     */
    public function load_admin_classes() : void {
        if ( ! is_admin() ) {
            return;
        }

        require_once PQC_PLUGIN_DIR . 'admin/class-metabox-first-quiz.php';
        require_once PQC_PLUGIN_DIR . 'admin/class-metabox-final-quiz.php';

        new PQC_Metabox_First_Quiz();
        new PQC_Metabox_Final_Quiz();
    }
}

new PQC_Bootstrap();
