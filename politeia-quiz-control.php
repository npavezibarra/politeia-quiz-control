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

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Constantes
// -----------------------------------------------------------------------------

define('PQCTL_PLUGIN_FILE', __FILE__);
define('PQCTL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PQCTL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PQCTL_VERSION', '1.0.0');

// -----------------------------------------------------------------------------
// Carga de clases siempre disponibles
// -----------------------------------------------------------------------------

require_once PQCTL_PLUGIN_DIR . 'admin/class-rest-search.php';
new PQCTL_REST_Search(); // <-- la ruta REST debe estar disponible dentro y fuera del admin

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------

class PQCTL_Bootstrap
{

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('init', [$this, 'load_admin_classes'], 20);
    }

    /**
     * Carga traducciones.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain('politeia-quiz-control', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Encola CSS/JS sólo en la edición de cursos.
     */

    public function enqueue_frontend_assets(): void
    {
        wp_enqueue_style(
            'pqc-style',
            PQCTL_PLUGIN_URL . 'assets/style.css',
            [],
            PQCTL_VERSION
        );
        wp_enqueue_style(
            'politeia-email-css',
            PQCTL_PLUGIN_URL . 'assets/emails.css',
            [],
            PQCTL_VERSION
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || 'sfwd-courses' !== $screen->post_type) {
            return;
        }

        // Select2 desde CDN (rápido de implementar)
        wp_enqueue_style('pqc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('pqc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('pqc-select2-es', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js', ['pqc-select2'], '4.1.0', true);

        // Assets propios
        wp_enqueue_style('pqc-admin', PQCTL_PLUGIN_URL . 'assets/admin.css', ['pqc-select2'], PQCTL_VERSION);
        wp_enqueue_script('pqc-admin', PQCTL_PLUGIN_URL . 'assets/admin.js', ['jquery', 'pqc-select2'], PQCTL_VERSION, true);
        wp_localize_script('pqc-admin', 'pqcData', [
            'restUrl' => esc_url_raw(rest_url('politeia-quiz-control/v1/quiz-search')),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'searching' => __('Buscando…', 'politeia-quiz-control'),
                'noResults' => __('No se encontraron resultados', 'politeia-quiz-control'),
            ],
        ]);
    }

    /**
     * Carga clases y funcionalidades exclusivas del administrador.
     */
    public function load_admin_classes(): void
    {
        if (!is_admin()) {
            return;
        }

        require_once PQCTL_PLUGIN_DIR . 'admin/class-metabox-evaluation.php';

        new PQCTL_Metabox_Evaluation();
    }
}

new PQCTL_Bootstrap();

/* REQUIRES */

require_once plugin_dir_path(__FILE__) . 'overwrites.php';
require_once plugin_dir_path(__FILE__) . 'functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-politeia-quiz-stats.php';
// Cargar el controlador de correos electrónicos del plugin.
require_once plugin_dir_path(__FILE__) . 'emails.php';
require_once plugin_dir_path(__FILE__) . 'includes/polis-average-quiz-result.php';


/* Registration Redirect */

add_filter('registration_redirect', function ($redirect, $requested_redirect_to, $user) {
    if (!empty($_GET['redirect_to'])) {
        return esc_url_raw(wp_unslash($_GET['redirect_to']));
    }
    return $redirect;
}, 10, 3);



// =============================================================================
//  VERSIÓN DEFINITIVA: WOOCOMMERCE ESTADO "COURSE ON HOLD" PARA LEARNDASH
// =============================================================================

/**
 * 1. Registra el estado de pedido personalizado 'wc-course-on-hold'.
 */
add_action('init', function () {
    register_post_status('wc-course-on-hold', array(
        'label' => _x('Course On Hold', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Course On Hold <span class="count">(%s)</span>', 'Course On Hold <span class="count">(%s)</span>', 'woocommerce'),
    ));
});

/**
 * 2. Añade el nuevo estado al listado de estados de WooCommerce para que sea visible.
 */
add_filter('wc_order_statuses', function ($order_statuses) {
    $new_order_statuses = array();
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-on-hold' === $key) {
            $new_order_statuses['wc-course-on-hold'] = _x('Course On Hold', 'Order status', 'woocommerce');
        }
    }
    return $new_order_statuses;
});

/**
 * 3. Al cambiar un pedido a 'on-hold', revisa si contiene un curso y actualiza el estado.
 * Esta versión utiliza get_post_meta() directamente para evitar problemas de carga de funciones.
 */
add_action('woocommerce_order_status_pending_to_on-hold', function ($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        // Usamos la función nativa de WordPress, que siempre está disponible.
        $related_course = get_post_meta($product_id, '_related_course', true);

        // Si el metadato '_related_course' existe y no está vacío, es un producto de curso.
        if (!empty($related_course)) {
            $order->update_status('wc-course-on-hold', 'Estado cambiado automáticamente: Pedido de curso LearnDash.');
            break; // Salimos del bucle, el trabajo está hecho.
        }
    }
}, 10, 1);

