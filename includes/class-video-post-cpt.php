<?php
/**
 * Registers the Video Post custom post type, taxonomies, meta fields, and admin meta box.
 */

defined( 'ABSPATH' ) || exit;

class DVP_Video_Post_CPT {

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_video_post', [ $this, 'save_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function enqueue_admin_assets( string $hook ): void {
		// Only load on Video Post add/edit screens.
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'video_post' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'dvp-admin-meta-box',
			DVP_PLUGIN_URL . 'assets/admin/meta-box.css',
			[],
			DVP_VERSION
		);

		// Required for wp.media() in the meta box JS.
		wp_enqueue_media();
	}

	public function register_post_type(): void {
		register_post_type( 'video_post', [
			'label'           => __( 'Video Posts', 'divi-video-post' ),
			'labels'          => [
				'name'               => __( 'Video Posts', 'divi-video-post' ),
				'singular_name'      => __( 'Video Post', 'divi-video-post' ),
				'add_new'            => __( 'Add New', 'divi-video-post' ),
				'add_new_item'       => __( 'Add New Video Post', 'divi-video-post' ),
				'edit_item'          => __( 'Edit Video Post', 'divi-video-post' ),
				'new_item'           => __( 'New Video Post', 'divi-video-post' ),
				'view_item'          => __( 'View Video Post', 'divi-video-post' ),
				'search_items'       => __( 'Search Video Posts', 'divi-video-post' ),
				'not_found'          => __( 'No video posts found.', 'divi-video-post' ),
				'not_found_in_trash' => __( 'No video posts found in trash.', 'divi-video-post' ),
			],
			'public'          => true,
			'has_archive'     => true,
			'show_in_rest'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'rewrite'         => [ 'slug' => 'video-posts' ],
			'menu_icon'       => 'dashicons-video-alt3',
			'taxonomies'      => [ 'video_category', 'post_tag' ],
		] );
	}

	public function register_taxonomy(): void {
		register_taxonomy( 'video_category', 'video_post', [
			'label'             => __( 'Video Categories', 'divi-video-post' ),
			'labels'            => [
				'name'              => __( 'Video Categories', 'divi-video-post' ),
				'singular_name'     => __( 'Video Category', 'divi-video-post' ),
				'search_items'      => __( 'Search Video Categories', 'divi-video-post' ),
				'all_items'         => __( 'All Video Categories', 'divi-video-post' ),
				'parent_item'       => __( 'Parent Video Category', 'divi-video-post' ),
				'parent_item_colon' => __( 'Parent Video Category:', 'divi-video-post' ),
				'edit_item'         => __( 'Edit Video Category', 'divi-video-post' ),
				'update_item'       => __( 'Update Video Category', 'divi-video-post' ),
				'add_new_item'      => __( 'Add New Video Category', 'divi-video-post' ),
				'new_item_name'     => __( 'New Video Category Name', 'divi-video-post' ),
				'menu_name'         => __( 'Video Categories', 'divi-video-post' ),
			],
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'video-category' ],
		] );
	}

	public function register_meta(): void {
		register_post_meta( 'video_post', '_video_url', [
			'type'              => 'string',
			'description'       => 'YouTube or Vimeo URL',
			'single'            => true,
			'sanitize_callback' => 'sanitize_url',
			'show_in_rest'      => true,
		] );

		register_post_meta( 'video_post', '_video_thumbnail', [
			'type'              => 'integer',
			'description'       => 'Attachment ID for poster/thumbnail image',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_post_meta( 'video_post', '_video_description', [
			'type'              => 'string',
			'description'       => 'Optional rich description',
			'single'            => true,
			'sanitize_callback' => 'sanitize_textarea_field',
			'show_in_rest'      => true,
		] );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'dvp_video_details',
			__( 'Video Details', 'divi-video-post' ),
			[ $this, 'render_meta_box' ],
			'video_post',
			'normal',
			'high'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		$video_url         = get_post_meta( $post->ID, '_video_url', true );
		$video_thumbnail   = get_post_meta( $post->ID, '_video_thumbnail', true );
		$video_description = get_post_meta( $post->ID, '_video_description', true );

		wp_nonce_field( 'dvp_save_meta', 'dvp_meta_nonce' );
		?>
		<div class="dvp-meta-box">
			<p>
				<label for="dvp_video_url"><strong><?php esc_html_e( 'Video URL', 'divi-video-post' ); ?></strong></label><br>
				<input
					type="url"
					id="dvp_video_url"
					name="dvp_video_url"
					value="<?php echo esc_attr( $video_url ); ?>"
					placeholder="https://www.youtube.com/watch?v=..."
					class="widefat"
				>
				<span class="description"><?php esc_html_e( 'Enter a YouTube or Vimeo URL.', 'divi-video-post' ); ?></span>
			</p>

			<p>
				<label for="dvp_video_thumbnail"><strong><?php esc_html_e( 'Video Thumbnail', 'divi-video-post' ); ?></strong></label><br>
				<input
					type="hidden"
					id="dvp_video_thumbnail"
					name="dvp_video_thumbnail"
					value="<?php echo esc_attr( $video_thumbnail ); ?>"
				>
				<?php if ( $video_thumbnail ) : ?>
					<div id="dvp-thumbnail-preview">
						<?php echo wp_get_attachment_image( (int) $video_thumbnail, 'medium' ); ?>
					</div>
				<?php else : ?>
					<div id="dvp-thumbnail-preview"></div>
				<?php endif; ?>
				<button type="button" id="dvp-select-thumbnail" class="button">
					<?php esc_html_e( 'Select Thumbnail', 'divi-video-post' ); ?>
				</button>
				<?php if ( $video_thumbnail ) : ?>
					<button type="button" id="dvp-remove-thumbnail" class="button">
						<?php esc_html_e( 'Remove Thumbnail', 'divi-video-post' ); ?>
					</button>
				<?php endif; ?>
			</p>

			<p>
				<label for="dvp_video_description"><strong><?php esc_html_e( 'Video Description', 'divi-video-post' ); ?></strong></label><br>
				<textarea
					id="dvp_video_description"
					name="dvp_video_description"
					rows="4"
					class="widefat"
				><?php echo esc_textarea( $video_description ); ?></textarea>
			</p>
		</div>

		<script>
		(function ($) {
			var mediaUploader;

			$('#dvp-select-thumbnail').on('click', function (e) {
				e.preventDefault();
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				mediaUploader = wp.media({
					title: '<?php echo esc_js( __( 'Select Video Thumbnail', 'divi-video-post' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use as Thumbnail', 'divi-video-post' ) ); ?>' },
					multiple: false,
					library: { type: 'image' },
				});
				mediaUploader.on('select', function () {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#dvp_video_thumbnail').val(attachment.id);
					$('#dvp-thumbnail-preview').html('<img src="' + attachment.sizes.medium.url + '" style="max-width:200px;">');
					$('#dvp-remove-thumbnail').show();
				});
				mediaUploader.open();
			});

			$(document).on('click', '#dvp-remove-thumbnail', function (e) {
				e.preventDefault();
				$('#dvp_video_thumbnail').val('');
				$('#dvp-thumbnail-preview').html('');
				$(this).hide();
			});
		}(jQuery));
		</script>
		<?php
	}

	public function save_meta_box( int $post_id ): void {
		if (
			! isset( $_POST['dvp_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dvp_meta_nonce'] ) ), 'dvp_save_meta' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['dvp_video_url'] ) ) {
			update_post_meta( $post_id, '_video_url', sanitize_url( wp_unslash( $_POST['dvp_video_url'] ) ) );
		}

		if ( isset( $_POST['dvp_video_thumbnail'] ) ) {
			$thumbnail_id = absint( $_POST['dvp_video_thumbnail'] );
			if ( $thumbnail_id > 0 ) {
				update_post_meta( $post_id, '_video_thumbnail', $thumbnail_id );
			} else {
				delete_post_meta( $post_id, '_video_thumbnail' );
			}
		}

		if ( isset( $_POST['dvp_video_description'] ) ) {
			update_post_meta( $post_id, '_video_description', sanitize_textarea_field( wp_unslash( $_POST['dvp_video_description'] ) ) );
		}
	}
}
