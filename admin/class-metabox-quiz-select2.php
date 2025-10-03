<?php
/**
 * Shared Select2-powered metaboxes for assigning First/Final quizzes to a course.
 *
 * @package PoliteiaQuizControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class that encapsulates the common behaviour of the Select2 quiz metaboxes.
 */
abstract class PQC_Abstract_Quiz_Metabox {

    /**
     * Meta key used to store the quiz ID.
     *
     * @var string
     */
    protected $meta_key = '';

    /**
     * Identifier used when registering the metabox.
     *
     * @var string
     */
    protected $metabox_id = '';

    /**
     * Human readable label of the metabox.
     *
     * @var string
     */
    protected $label = '';

    /**
     * Nonce action to validate submissions.
     *
     * @var string
     */
    protected $nonce_action = '';

    /**
     * Nonce field name.
     *
     * @var string
     */
    protected $nonce_field = '';

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
        add_action( 'save_post_sfwd-courses', [ $this, 'save_meta' ] );
    }

    /**
     * Registers the metabox on the course editor sidebar.
     */
    public function register_metabox() : void {
        add_meta_box(
            $this->metabox_id,
            $this->label,
            [ $this, 'render' ],
            'sfwd-courses',
            'side',
            'default'
        );
    }

    /**
     * Prints the Select2 field used to search quizzes.
     *
     * @param WP_Post $post Course being edited.
     */
    public function render( $post ) : void {
        $value       = (int) get_post_meta( $post->ID, $this->meta_key, true );
        $placeholder = __( '— None —', 'politeia-quiz-control' );

        wp_nonce_field( $this->nonce_action, $this->nonce_field );

        printf(
            '<select name="%1$s" id="%1$s" class="pqc-select2" style="width:100%%;" data-placeholder="%2$s" data-allow-clear="true">',
            esc_attr( $this->meta_key ),
            esc_attr( $placeholder )
        );
        printf( '<option value="">%s</option>', esc_html( $placeholder ) );

        if ( $value ) {
            $title = get_the_title( $value );
            if ( $title ) {
                printf(
                    '<option value="%1$s" selected>%2$s</option>',
                    esc_attr( $value ),
                    esc_html( $title )
                );
            }
        }

        echo '</select>';
    }

    /**
     * Persists the selected quiz ID when the course is saved.
     *
     * @param int $post_id Course identifier.
     */
    public function save_meta( $post_id ) : void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST[ $this->nonce_field ] ) || ! wp_verify_nonce( $_POST[ $this->nonce_field ], $this->nonce_action ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $new_value = isset( $_POST[ $this->meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->meta_key ] ) ) : '';

        if ( '' === $new_value ) {
            delete_post_meta( $post_id, $this->meta_key );
            return;
        }

        $quiz_id = absint( $new_value );

        if ( ! $quiz_id ) {
            delete_post_meta( $post_id, $this->meta_key );
            return;
        }

        $quiz_post = get_post( $quiz_id );

        if ( $quiz_post && 'sfwd-quiz' === $quiz_post->post_type && 'publish' === $quiz_post->post_status ) {
            update_post_meta( $post_id, $this->meta_key, $quiz_id );
        } else {
            delete_post_meta( $post_id, $this->meta_key );
        }
    }
}

/**
 * Metabox used to assign the First Quiz to a course.
 */
class PQC_Metabox_First_Quiz extends PQC_Abstract_Quiz_Metabox {

    public function __construct() {
        $this->meta_key     = '_first_quiz_id';
        $this->metabox_id   = 'pqc_first_quiz';
        $this->label        = __( 'First Quiz', 'politeia-quiz-control' );
        $this->nonce_action = 'pqc_first_quiz_nonce';
        $this->nonce_field  = 'pqc_first_quiz_nonce_field';

        parent::__construct();
    }
}

/**
 * Metabox used to assign the Final Quiz to a course.
 */
class PQC_Metabox_Final_Quiz extends PQC_Abstract_Quiz_Metabox {

    public function __construct() {
        $this->meta_key     = '_final_quiz_id';
        $this->metabox_id   = 'pqc_final_quiz';
        $this->label        = __( 'Final Quiz', 'politeia-quiz-control' );
        $this->nonce_action = 'pqc_final_quiz_nonce';
        $this->nonce_field  = 'pqc_final_quiz_nonce_field';

        parent::__construct();
    }
}

