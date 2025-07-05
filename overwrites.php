<?php
/**
 * overwrites.php
 * 
 * Contiene los filtros para sobreescribir plantillas de LearnDash y BuddyBoss.
 */

// Asegura que no se acceda directamente
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Override LearnDash v3.0+ templates
 *
 * @param string $filepath    Ruta original de la plantilla.
 * @param string $filename    Nombre del archivo de plantilla.
 * @param string $legacy_file Ruta antigua (legacy).
 * @param string $ld_version  Versión de LearnDash.
 * @return string             Ruta de plantilla a cargar.
 */
function politeia_qc_override_ld_template( $filepath, $filename, $legacy_file, $ld_version ) {
    if ( 'template-single-course-sidebar.php' === $filename ) {
        $override = plugin_dir_path( __FILE__ )
                  . 'overrides/learndash/ld30/'
                  . $filename;
        if ( file_exists( $override ) ) {
            return $override;
        }
    }
    return $filepath;
}
add_filter( 'learndash_template', 'politeia_qc_override_ld_template', 10, 4 );

// Puedes añadir aquí más filtros para otros archivos a sobreescribir
