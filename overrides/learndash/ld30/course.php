<?php
/**
 * LearnDash LD30 Displays a course
 *
 * Available Variables:
 * $course_id                   : (int) ID of the course
 * $course                      : (object) Post object of the course
 * $course_settings             : (array) Settings specific to current course
 *
 * $courses_options             : Options/Settings as configured on Course Options page
 * $lessons_options             : Options/Settings as configured on Lessons Options page
 * $quizzes_options             : Options/Settings as configured on Quiz Options page
 *
 * $user_id                     : Current User ID
 * $logged_in                   : User is logged in
 * $current_user                : (object) Currently logged in user object
 *
 * $course_status               : Course Status
 * $has_access                  : User has access to course or is enrolled.
 * $materials                   : Course Materials
 * $has_course_content          : Course has course content
 * $lessons                     : Lessons Array
 * $quizzes                     : Quizzes Array
 * $lesson_progression_enabled  : (true/false)
 * $has_topics                  : (true/false)
 * $lesson_topics               : (array) lessons topics
 *
 * @since 3.0.0
 *
 * @package LearnDash\Templates\LD30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( LearnDash_Theme_Register::get_active_theme_instance()->supports_views( LDLMS_Post_Types::get_post_type_key( learndash_get_post_type_slug( 'course' ) ) ) ) {
	$course_id = get_the_ID();
	$post      = get_post( $course_id ); // Get the WP_Post object.
	$course    = \LearnDash\Core\Models\Course::create_from_post( $post );
	$content   = $course->get_content();

	// Get basic course data from the course object.
	$course_product             = $course->get_product();
	$course_settings            = $course_product->get_pricing_settings();
	$courses_options            = learndash_get_option( 'sfwd-courses' );
	$lessons_options            = learndash_get_option( 'sfwd-lessons' );
	$quizzes_options            = learndash_get_option( 'sfwd-quiz' );
	$user_id                    = get_current_user_id();
	$logged_in                  = is_user_logged_in();
	$current_user               = wp_get_current_user();
	$course_status              = learndash_course_status( $course_id, $user_id );
	$has_access                 = $course_product->user_has_access();
	$materials                  = $course->get_materials();
	$has_course_content         = $course->has_steps();
	$lessons                    = learndash_get_course_lessons_list( $course_id );
	$quizzes                    = $course->get_quizzes();
	$lesson_progression_enabled = learndash_lesson_progression_enabled( $course_id );
	$has_topics                 = $course->get_topics_number() > 0;

	if ( ! empty( $lessons ) ) {
		foreach ( $lessons as $lesson ) {
			$lesson_topics[ $lesson['post']->ID ] = learndash_topic_dots( $lesson['post']->ID, false, 'array', null, $course_id );
			if ( ! empty( $lesson_topics[ $lesson['post']->ID ] ) ) {
				$has_topics = true;

				$topic_pager_args                     = array(
					'course_id' => $course_id,
					'lesson_id' => $lesson['post']->ID,
				);
				$lesson_topics[ $lesson['post']->ID ] = learndash_process_lesson_topics_pager( $lesson_topics[ $lesson['post']->ID ], $topic_pager_args );
			}
		}
	}

	// Get course meta and certificate.
	$course_meta = get_post_meta( $course_id, '_sfwd-courses', true );
	if ( ! is_array( $course_meta ) ) {
		$course_meta = array();
	}
	if ( ! isset( $course_meta['sfwd-courses_course_disable_content_table'] ) ) {
		$course_meta['sfwd-courses_course_disable_content_table'] = false;
	}
	$course_certficate_link = $course->get_certificate_link( $user_id );
} else {
	$materials              = ( isset( $materials ) ) ? $materials : '';
	$lessons                = ( isset( $lessons ) ) ? $lessons : array();
	$quizzes                = ( isset( $quizzes ) ) ? $quizzes : array();
	$lesson_topics          = ( isset( $lesson_topics ) ) ? $lesson_topics : array();
	$course_certficate_link = ( isset( $course_certficate_link ) ) ? $course_certficate_link : '';
}

$template_args = array(
	'course_id'                  => $course_id,
	'course'                     => $course,
	'course_settings'            => $course_settings,
	'courses_options'            => $courses_options,
	'lessons_options'            => $lessons_options,
	'quizzes_options'            => $quizzes_options,
	'user_id'                    => $user_id,
	'logged_in'                  => $logged_in,
	'current_user'               => $current_user,
	'course_status'              => $course_status,
	'has_access'                 => $has_access,
	'materials'                  => $materials,
	'has_course_content'         => $has_course_content,
	'lessons'                    => $lessons,
	'quizzes'                    => $quizzes,
	'lesson_progression_enabled' => $lesson_progression_enabled,
	'has_topics'                 => $has_topics,
	'lesson_topics'              => $lesson_topics,
	'post'                       => $post,
);

$has_lesson_quizzes = learndash_30_has_lesson_quizzes( $course_id, $lessons ); ?>


<div class="<?php echo esc_attr( learndash_the_wrapper_class() ); ?>">
PRINT THIS CUSTOM MESSAGE ON THE COURSE PAGE
	<?php
	global $course_pager_results;

	/**
	 * Fires before the topic.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id   Post ID.
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 */
	do_action( 'learndash-course-before', get_the_ID(), $course_id, $user_id );

	learndash_get_template_part(
		'template-banner.php',
		array(
			'context'   => 'course',
			'course_id' => $course_id,
			'user_id'   => $user_id,
		),
		true
	);
	?>

	<div class="bb-grid">

		<div class="bb-learndash-content-wrap">

			<?php
			/**
			 * Fires before the course certificate link.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'learndash-course-certificate-link-before', $course_id, $user_id );

			/**
			 * Certificate link
			 */

			if (
				( defined( 'LEARNDASH_TEMPLATE_CONTENT_METHOD' ) ) &&
				( 'shortcode' === LEARNDASH_TEMPLATE_CONTENT_METHOD )
			) {
				$shown_content_key = 'learndash-shortcode-wrap-ld_certificate-' . absint( $course_id ) . '_' . absint( $user_id );
				if ( false === strstr( $content, $shown_content_key ) ) {
					$shortcode_out = do_shortcode( '[ld_certificate course_id="' . $course_id . '" user_id="' . $user_id . '" display_as="banner"]' );
					if ( ! empty( $shortcode_out ) ) {
						echo $shortcode_out;
					}
				}
			} elseif ( ! empty( $course_certficate_link ) ) {
				learndash_get_template_part(
					'modules/alert.php',
					array(
						'type'    => 'success ld-alert-certificate',
						'icon'    => 'certificate',
						'message' => __( 'You\'ve earned a certificate!', 'buddyboss-theme' ),
						'button'  => array(
							'url'    => $course_certficate_link,
							'icon'   => 'download',
							'label'  => __( 'Download Certificate', 'buddyboss-theme' ),
							'target' => '_new',
						),
					),
					true
				);
			}

			/**
			 * Fires after the course certificate link.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'learndash-course-certificate-link-after', $course_id, $user_id );


			if (
				( defined( 'LEARNDASH_TEMPLATE_CONTENT_METHOD' ) ) &&
				( 'shortcode' === LEARNDASH_TEMPLATE_CONTENT_METHOD )
			) {
				$shown_content_key = 'learndash-shortcode-wrap-ld_infobar-' . absint( $course_id ) . '_' . (int) get_the_ID() . '_' . absint( $user_id );
				if ( false === strstr( $content, $shown_content_key ) ) {
					$shortcode_out = do_shortcode( '[ld_infobar course_id="' . $course_id . '" user_id="' . $user_id . '" post_id="' . get_the_ID() . '"]' );
					if ( ! empty( $shortcode_out ) ) {
						echo $shortcode_out;
					}
				}
			} else {
				/**
				 * Course info bar
				 */
				learndash_get_template_part(
					'modules/infobar.php',
					array(
						'context'       => 'course',
						'course_id'     => $course_id,
						'user_id'       => $user_id,
						'has_access'    => $has_access,
						'course_status' => $course_status,
						'post'          => $post,
					),
					true
				);
			}

			/** This filter is documented in themes/legacy/templates/course.php */
			echo apply_filters( 'ld_after_course_status_template_container', '', learndash_course_status_idx( $course_status ), $course_id, $user_id );

			/**
			 * Content tabs
			 */
			echo '<div class="bb-ld-tabs">';
			echo '<div id="learndash-course-content"></div>';
			learndash_get_template_part(
				'modules/tabs.php',
				array(
					'course_id' => $course_id,
					'post_id'   => get_the_ID(),
					'user_id'   => $user_id,
					'content'   => $content,
					'materials' => $materials,
					'context'   => 'course',
				),
				true
			);
			echo '</div>';

			/**
			 * Identify if we should show the course content listing
			 *
			 * @var $show_course_content [bool]
			 */
			$show_course_content = ( ! $has_access && 'on' === $course_meta['sfwd-courses_course_disable_content_table'] ? false : true );

			if ( $has_course_content && $show_course_content ) :

				if (
					( defined( 'LEARNDASH_TEMPLATE_CONTENT_METHOD' ) ) &&
					( 'shortcode' === LEARNDASH_TEMPLATE_CONTENT_METHOD )
				) {
					$shown_content_key = 'learndash-shortcode-wrap-course_content-' . absint( $course_id ) . '_' . (int) get_the_ID() . '_' . absint( $user_id );
					if ( false === strstr( $content, $shown_content_key ) ) {
						$shortcode_out = do_shortcode( '[course_content course_id="' . $course_id . '" user_id="' . $user_id . '" post_id="' . get_the_ID() . '"]' );
						if ( ! empty( $shortcode_out ) ) {
							echo $shortcode_out;
						}
					}
				} else {
					?>

					<div class="ld-item-list ld-lesson-list">
						<div class="ld-section-heading">

							<?php
							/**
							 * Fires before the course heading.
							 *
							 * @since 3.0.0
							 *
							 * @param int $course_id Course ID.
							 * @param int $user_id   User ID.
							 */
							do_action( 'learndash-course-heading-before', $course_id, $user_id );
							?>

							<h2>
								<?php
								printf(
								// translators: placeholder: Course.
									esc_html_x( '%s Content', 'placeholder: Course', 'buddyboss-theme' ),
									LearnDash_Custom_Label::get_label( 'course' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes output
								);
								?>
							</h2>

							<?php
							/**
							 * Fires after the course heading.
							 *
							 * @since 3.0.0
							 *
							 * @param int $course_id Course ID.
							 * @param int $user_id   User ID.
							 */
							do_action( 'learndash-course-heading-after', $course_id, $user_id );
							?>

							<div class="ld-item-list-actions" data-ld-expand-list="true">

								<?php
								/**
								 * Fires before the course expand.
								 *
								 * @since 3.0.0
								 *
								 * @param int $course_id Course ID.
								 * @param int $user_id   User ID.
								 */
								do_action( 'learndash-course-expand-before', $course_id, $user_id );

								$lesson_container_ids = implode(
									' ',
									array_filter(
										array_map(
											function ( $lesson_id ) use ( $user_id, $course_id ) {
												$topics  = learndash_get_topic_list( $lesson_id, $course_id );
												$quizzes = learndash_get_lesson_quiz_list( $lesson_id, $user_id, $course_id );

												// Ensure we only include this ID if there is something to collapse/expand.
												if (
													empty( $topics )
													&& empty( $quizzes )
												) {
													return '';
												}

												return "ld-expand-{$lesson_id}-container";
											},
											array_keys( $lesson_topics )
										)
									)
								);
								?>

								<?php
								// Only display if there is something to expand.
								if ( $has_topics || $has_lesson_quizzes ) :
									?>
									<button
											aria-controls="<?php echo esc_attr( $lesson_container_ids ); ?>"
											class="ld-expand-button ld-primary-background"
											id="<?php echo esc_attr( 'ld-expand-button-' . $course_id ); ?>"
											data-ld-expands="<?php echo esc_attr( $lesson_container_ids ); ?>"
											data-ld-expand-text="<?php echo esc_attr__( 'Expand All', 'buddyboss-theme' ); ?>"
											data-ld-collapse-text="<?php echo esc_attr__( 'Collapse All', 'buddyboss-theme' ); ?>"
									>
										<span class="ld-icon-arrow-down ld-icon"></span>
										<span class="ld-text"><?php echo esc_attr__( 'Expand All', 'buddyboss-theme' ); ?></span>
									</button> <!--/.ld-expand-button-->
								<?php

								/**
								 * Filters whether to expand all course steps by default. Default is false.
								 *
								 * @since 2.5.0
								 *
								 * @param boolean $expand_all Whether to expand all course steps.
								 * @param int     $course_id  Course ID.
								 * @param string  $context    The context where course is expanded.
								 */
								if ( apply_filters( 'learndash_course_steps_expand_all', false, $course_id, 'course_lessons_listing_main' ) ) :
								?>
									<script>
										jQuery( function () {
											setTimeout( function () {
												jQuery( "<?php echo esc_attr( '#ld-expand-button-' . $course_id ); ?>" ).trigger( 'click' );
											}, 1000 );
										} );
									</script>
								<?php
								endif;

								endif;

								/**
								 * Fires after the course content expand button.
								 *
								 * @since 3.0.0
								 *
								 * @param int $course_id Course ID.
								 * @param int $user_id   User ID.
								 */
								do_action( 'learndash-course-expand-after', $course_id, $user_id );
								?>

							</div> <!--/.ld-item-list-actions-->
						</div> <!--/.ld-section-heading-->

						<?php
						/**
						 * Fires before the course content listing
						 *
						 * @since 3.0.0
						 *
						 * @param int $course_id Course ID.
						 * @param int $user_id   User ID.
						 */
						do_action( 'learndash-course-content-list-before', $course_id, $user_id );

						/**
						 * Content listing
						 *
						 * @since 3.0.0
						 *
						 * ('listing.php');
						 */
						learndash_get_template_part(
							'course/listing.php',
							array(
								'course_id'                  => $course_id,
								'user_id'                    => $user_id,
								'lessons'                    => $lessons,
								'lesson_topics'              => @$lesson_topics,
								'quizzes'                    => $quizzes,
								'has_access'                 => $has_access,
								'course_pager_results'       => $course_pager_results,
								'lesson_progression_enabled' => $lesson_progression_enabled,
							),
							true
						);

						/**
						 * Fires before the course content listing.
						 *
						 * @since 3.0.0
						 *
						 * @param int $course_id Course ID.
						 * @param int $user_id   User ID.
						 */
						do_action( 'learndash-course-content-list-after', $course_id, $user_id );
						?>

					</div> <!--/.ld-item-list-->

					<?php
				}
			endif;

			learndash_get_template_part(
				'template-course-author-details.php',
				array(
					'context'   => 'course',
					'course_id' => $course_id,
					'user_id'   => $user_id,
				),
				true
			);

			?>

		</div>

		<?php
		// Single course sidebar.
		learndash_get_template_part( 'template-single-course-sidebar.php', $template_args, true );
		?>
	</div>

	<?php

	/**
	 * Fires before the topic.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id   Post ID.
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 */
	do_action( 'learndash-course-after', get_the_ID(), $course_id, $user_id );
	if ( ! is_user_logged_in() ) {
		global $login_model_load_once;
		$login_model_load_once      = false;
		$learndash_login_model_html = learndash_get_template_part( 'modules/login-modal.php', array(), false );
		echo '<div class="learndash-wrapper learndash-wrapper-login-modal">' . $learndash_login_model_html . '</div>';
	}
	?>

</div>
