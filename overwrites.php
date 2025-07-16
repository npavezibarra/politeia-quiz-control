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

// Intercepta cualquier carga de template para el sidebar de curso
function politeia_qc_override_ld_template( $filepath, $filename, $legacy_file, $ld_version ) {
    if ( 'template-single-course-sidebar.php' === $filename ) {
        $override = __DIR__ . '/overrides/learndash/ld30/' . $filename;
        if ( file_exists( $override ) ) {
            return $override;
        }
    }
    return $filepath;
}
add_filter( 'learndash_template', 'politeia_qc_override_ld_template', 10, 4 );

function politeia_override_infobar_course( $filepath, $filename, $legacy_file, $ld_version ) {
    // Comprueba que el nombre de fichero sea "course.php"
    if ( 'course.php' === basename( $filename ) 
         && false !== strpos( $filepath, 'modules/infobar' ) ) {

        // Ruta corregida (nota “overrides”, no “overwrites”)
        $override = __DIR__ 
                  . '/overrides/learndash/ld30/modules/infobar/'
                  . basename( $filename );

        error_log( "[Politeia] Buscando override en → {$override}" );

        if ( file_exists( $override ) ) {
            error_log( "[Politeia] ¡Override encontrado y usado! → {$override}" );
            return $override;
        } else {
            error_log( "[Politeia] Override NO encontrado en: {$override}" );
        }
    }

    return $filepath;
}
add_filter( 'learndash_template', 'politeia_override_infobar_course', 5, 4 );


add_filter( 'learndash_template', function( $filepath, $filename ) {
    if ( 'progress.php' === basename( $filename ) && false !== strpos( $filepath, 'modules/progress' ) ) {
        $override = __DIR__ . '/overrides/learndash/ld30/modules/progress.php';
        if ( file_exists( $override ) ) {
            return $override;
        }
    }
    return $filepath;
}, 10, 2 );


/* OVERWRITE SINGLE QUIZ TEMPLATE 

add_filter('template_include', function($template) {
    if (is_singular('sfwd-quiz')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/quiz/single-sfwd-quiz.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
});*/


/**
 * Sobrescribe el template principal de la página de un curso (single-sfwd-courses.php).
 *
 * Usamos el filtro 'template_include', que es el filtro final de WordPress
 * para decidir qué archivo de plantilla de página completa cargar.
 *
 * @param string $template La ruta a la plantilla que WordPress planea usar.
 * @return string La ruta a nuestra plantilla personalizada o la original.
 */
function politeia_override_single_course_template_file( $template ) {
    // Verifica si estamos en la página de un curso single ('sfwd-courses').
    if ( is_singular( 'sfwd-courses' ) ) {
        // Define la ruta a nuestra copia de single-sfwd-courses.php dentro del plugin.
        $new_template = __DIR__ . '/overrides/buddyboss/single-sfwd-courses.php';

        // Si nuestro archivo existe, le decimos a WordPress que lo use.
        if ( file_exists( $new_template ) ) {
            return $new_template;
        }
    }

    // Si no es una página de curso, no hacemos nada y devolvemos la plantilla original.
    return $template;
}
add_filter( 'template_include', 'politeia_override_single_course_template_file', 99 );


/* OVERWRITE QUIZ RESULT */

add_filter( 'learndash_template', 'politeia_override_show_quiz_result_box', 10, 5 );

function politeia_override_show_quiz_result_box( $filepath, $filename, $args, $template_path, $default_path ) {
	if ( $filename === 'quiz/partials/show_quiz_result_box.php' ) {
		$custom_template = plugin_dir_path( __FILE__ ) . 'overrides/quiz/partials/show_quiz_result_box.php';
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}
	return $filepath;
}
