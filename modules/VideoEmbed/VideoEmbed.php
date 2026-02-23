<?php
/**
 * VideoEmbed Divi Builder Module.
 *
 * Slug: et_pb_video_embed
 */

defined( 'ABSPATH' ) || exit;

class ET_Builder_VideoEmbed extends ET_Builder_Module {

	public $slug       = 'et_pb_video_embed';
	public $vb_support = 'on';

	protected $module_credits = [
		'module_uri' => '',
		'author'     => 'Your Name',
		'author_uri' => '',
	];

	public function init(): void {
		$this->name             = esc_html__( 'Video Embed', 'divi-video-post' );
		$this->plural           = esc_html__( 'Video Embeds', 'divi-video-post' );
		$this->main_css_element = '%%order_class%%';
	}

	public function get_fields(): array {
		return [
			'video_mode'             => [
				'label'           => esc_html__( 'Video Mode', 'divi-video-post' ),
				'type'            => 'select',
				'options'         => [
					'dynamic' => esc_html__( 'Dynamic (from post meta)', 'divi-video-post' ),
					'manual'  => esc_html__( 'Manual Override', 'divi-video-post' ),
				],
				'default'         => 'dynamic',
				'description'     => esc_html__( 'Choose whether to pull the video URL from the Video Post\'s meta field or enter it manually.', 'divi-video-post' ),
				'toggle_slug'     => 'main_content',
			],
			'manual_video_url'       => [
				'label'           => esc_html__( 'Video URL', 'divi-video-post' ),
				'type'            => 'text',
				'default'         => '',
				'description'     => esc_html__( 'Enter a YouTube or Vimeo URL.', 'divi-video-post' ),
				'toggle_slug'     => 'main_content',
				'show_if'         => [ 'video_mode' => 'manual' ],
			],
			'video_ratio'            => [
				'label'           => esc_html__( 'Aspect Ratio', 'divi-video-post' ),
				'type'            => 'select',
				'options'         => [
					'16:9' => '16:9',
					'4:3'  => '4:3',
					'1:1'  => '1:1',
				],
				'default'         => '16:9',
				'description'     => esc_html__( 'Choose the video aspect ratio.', 'divi-video-post' ),
				'toggle_slug'     => 'main_content',
			],
			'show_thumbnail_fallback' => [
				'label'           => esc_html__( 'Show Thumbnail as Poster', 'divi-video-post' ),
				'type'            => 'yes_no_button',
				'options'         => [
					'on'  => esc_html__( 'Yes', 'divi-video-post' ),
					'off' => esc_html__( 'No', 'divi-video-post' ),
				],
				'default'         => 'on',
				'description'     => esc_html__( 'Show the Video Post\'s thumbnail as a background poster before the video plays.', 'divi-video-post' ),
				'toggle_slug'     => 'main_content',
			],
		];
	}

	public function render( $attrs, $content, $render_slug ): string {
		$video_mode              = $this->props['video_mode'] ?? 'dynamic';
		$manual_video_url        = $this->props['manual_video_url'] ?? '';
		$video_ratio             = $this->props['video_ratio'] ?? '16:9';
		$show_thumbnail_fallback = $this->props['show_thumbnail_fallback'] ?? 'on';

		// Resolve the video URL.
		if ( 'dynamic' === $video_mode ) {
			$video_url = get_post_meta( get_the_ID(), '_video_url', true );
		} else {
			$video_url = $manual_video_url;
		}

		// Handle empty URL.
		if ( empty( $video_url ) ) {
			return '<div class="dvp-no-video"></div>';
		}

		// Parse URL into platform + ID.
		$parsed = $this->parse_video_url( $video_url );

		if ( 'youtube' === $parsed['platform'] ) {
			$embed_url = 'https://www.youtube.com/embed/' . rawurlencode( $parsed['id'] ) . '?rel=0';
		} elseif ( 'vimeo' === $parsed['platform'] ) {
			$embed_url = 'https://player.vimeo.com/video/' . rawurlencode( $parsed['id'] );
		} else {
			return '<div class="dvp-no-video"></div>';
		}

		// Determine ratio CSS class.
		$ratio_class_map = [
			'16:9' => 'dvp-ratio-16x9',
			'4:3'  => 'dvp-ratio-4x3',
			'1:1'  => 'dvp-ratio-1x1',
		];
		$ratio_class = $ratio_class_map[ $video_ratio ] ?? 'dvp-ratio-16x9';

		// Thumbnail poster (dynamic mode only).
		$poster_style = '';
		if ( 'on' === $show_thumbnail_fallback && 'dynamic' === $video_mode ) {
			$thumbnail_id = get_post_meta( get_the_ID(), '_video_thumbnail', true );
			if ( $thumbnail_id ) {
				$thumbnail_url = wp_get_attachment_image_url( (int) $thumbnail_id, 'large' );
			} else {
				$thumbnail_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
			}
			if ( $thumbnail_url ) {
				$poster_style = ' style="background-image: url(' . esc_url( $thumbnail_url ) . ');"';
			}
		}

		$iframe = sprintf(
			'<iframe src="%s" allowfullscreen loading="lazy" title="%s"></iframe>',
			esc_url( $embed_url ),
			esc_attr__( 'Video embed', 'divi-video-post' )
		);

		return sprintf(
			'<div class="dvp-video-wrapper %s"%s>%s</div>',
			esc_attr( $ratio_class ),
			$poster_style,
			$iframe
		);
	}

	/**
	 * Parse a YouTube or Vimeo URL into platform and video ID.
	 *
	 * @param string $url
	 * @return array{platform: string, id: string}
	 */
	private function parse_video_url( string $url ): array {
		$result = [ 'platform' => 'unknown', 'id' => '' ];

		$url = trim( $url );

		// YouTube patterns.
		// youtube.com/watch?v=ID
		if ( preg_match( '/(?:youtube\.com\/watch\?(?:[^&]*&)*v=)([a-zA-Z0-9_\-]{11})/', $url, $matches ) ) {
			return [ 'platform' => 'youtube', 'id' => $matches[1] ];
		}

		// youtu.be/ID
		if ( preg_match( '/youtu\.be\/([a-zA-Z0-9_\-]{11})/', $url, $matches ) ) {
			return [ 'platform' => 'youtube', 'id' => $matches[1] ];
		}

		// youtube.com/embed/ID
		if ( preg_match( '/youtube\.com\/embed\/([a-zA-Z0-9_\-]{11})/', $url, $matches ) ) {
			return [ 'platform' => 'youtube', 'id' => $matches[1] ];
		}

		// Vimeo patterns.
		// player.vimeo.com/video/ID
		if ( preg_match( '/player\.vimeo\.com\/video\/(\d+)/', $url, $matches ) ) {
			return [ 'platform' => 'vimeo', 'id' => $matches[1] ];
		}

		// vimeo.com/ID
		if ( preg_match( '/vimeo\.com\/(\d+)(?:[?\/]|$)/', $url, $matches ) ) {
			return [ 'platform' => 'vimeo', 'id' => $matches[1] ];
		}

		return $result;
	}
}

// DiviExtension base class automatically discovers and loads this module
// when it lives at modules/VideoEmbed/VideoEmbed.php.
