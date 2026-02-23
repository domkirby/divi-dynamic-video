<?php
/**
 * GitHub Releases updater for the Divi Video Post plugin.
 *
 * Hooks into the WordPress transient-based update system to surface new
 * releases published on GitHub without requiring the plugin to be listed
 * on the WordPress.org plugin directory.
 *
 * Release convention
 * ------------------
 * Tag format  : v1.2.3  (the leading "v" is stripped when comparing versions)
 * Release asset: A ZIP file named exactly `divi-video-post.zip` must be
 *               attached to each GitHub release. This ZIP should contain a
 *               single top-level folder named `divi-video-post/` so that
 *               WordPress places the plugin files in the correct location
 *               after installation.
 *
 * Usage
 * -----
 * new DVP_GitHub_Updater(
 *     DVP_PLUGIN_FILE,   // __FILE__ from the main plugin file
 *     'domkirby',        // GitHub username / org
 *     'divi-dynamic-video' // GitHub repository slug
 * );
 */

defined( 'ABSPATH' ) || exit;

class DVP_GitHub_Updater {

	/** How long to cache the GitHub API response (in seconds). */
	private const CACHE_TTL = 43200; // 12 hours

	private const TRANSIENT_KEY = 'dvp_github_release_data';

	private string $plugin_file;
	private string $plugin_basename;
	private string $current_version;
	private string $github_user;
	private string $github_repo;
	private string $api_url;

	public function __construct( string $plugin_file, string $github_user, string $github_repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->github_user     = $github_user;
		$this->github_repo     = $github_repo;
		$this->api_url         = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";

		$plugin_data           = get_file_data( $plugin_file, [ 'Version' => 'Version' ] );
		$this->current_version = $plugin_data['Version'] ?? '0.0.0';

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// WordPress update hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject update data into the WordPress plugin update transient when a
	 * newer release is found on GitHub.
	 *
	 * @param  object $transient WordPress update_plugins transient.
	 * @return object
	 */
	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$download_url = $this->get_download_url( $release );
			if ( $download_url ) {
				$transient->response[ $this->plugin_basename ] = (object) [
					'slug'        => dirname( $this->plugin_basename ),
					'plugin'      => $this->plugin_basename,
					'new_version' => $remote_version,
					'url'         => $release['html_url'],
					'package'     => $download_url,
					'icons'       => [],
					'banners'     => [],
					'tested'      => '',
					'requires_php' => '8.0',
				];
			}
		}

		return $transient;
	}

	/**
	 * Populate the "View Details" plugin info popup with data from the
	 * GitHub release.
	 *
	 * @param  false|object|array $result The result object/array, or false.
	 * @param  string             $action The type of information being requested.
	 * @param  object             $args   Plugin API arguments.
	 * @return false|object
	 */
	public function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( dirname( $this->plugin_basename ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = $this->parse_version( $release['tag_name'] );
		$download_url   = $this->get_download_url( $release );

		return (object) [
			'name'              => 'Divi Dynamic Video',
			'slug'              => dirname( $this->plugin_basename ),
			'version'           => $remote_version,
			'author'            => '<a href="https://github.com/' . esc_attr( $this->github_user ) . '">' . esc_html( $this->github_user ) . '</a>',
			'homepage'          => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
			'requires'          => '6.0',
			'tested'            => '',
			'requires_php'      => '8.0',
			'download_link'     => $download_url,
			'trunk'             => $download_url,
			'last_updated'      => $release['published_at'] ?? '',
			'sections'          => [
				'description' => 'A WordPress plugin that registers a Video Post custom post type and a Divi Builder Video Embed module.',
				'changelog'   => $this->format_changelog( $release['body'] ?? '' ),
			],
		];
	}

	/**
	 * After the plugin ZIP is extracted, rename the folder if GitHub gave it
	 * an auto-generated name (e.g. domkirby-divi-dynamic-video-abc1234/).
	 *
	 * @param  bool  $response   Installation response.
	 * @param  array $hook_extra Extra arguments passed to hooked filters.
	 * @param  array $result     Installation result data.
	 * @return bool|WP_Error
	 */
	public function after_install( bool $response, array $hook_extra, array $result ): bool|WP_Error {
		if (
			! isset( $hook_extra['plugin'] ) ||
			$hook_extra['plugin'] !== $this->plugin_basename
		) {
			return $response;
		}

		global $wp_filesystem;

		$plugin_dir  = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_basename );
		$install_dir = $result['destination'] ?? '';

		if ( $install_dir && $install_dir !== $plugin_dir ) {
			$wp_filesystem->move( $install_dir, $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		// Re-activate the plugin if it was active before the update.
		activate_plugin( $this->plugin_basename );

		return $response;
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch the latest release data from the GitHub API, with a 12-hour cache.
	 *
	 * @return array|null Decoded release data, or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( $this->api_url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		// Do not surface pre-release or draft releases.
		if ( ! empty( $data['prerelease'] ) || ! empty( $data['draft'] ) ) {
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Resolve the best download URL from a release.
	 *
	 * Prefers a release asset named `divi-video-post.zip`; falls back to
	 * GitHub's auto-generated source ZIP.
	 *
	 * @param  array $release GitHub release data.
	 * @return string|null
	 */
	private function get_download_url( array $release ): ?string {
		// Prefer an explicitly uploaded release asset.
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( 'divi-video-post.zip' === ( $asset['name'] ?? '' ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fall back to GitHub's auto-generated source ZIP.
		return $release['zipball_url'] ?? null;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Strip a leading "v" from a tag name so version_compare() works correctly.
	 * e.g. "v1.2.3" → "1.2.3"
	 */
	private function parse_version( string $tag ): string {
		return ltrim( $tag, 'vV' );
	}

	/**
	 * Convert a GitHub release body (Markdown) to basic HTML for the
	 * WordPress plugin info popup.
	 */
	private function format_changelog( string $markdown ): string {
		if ( empty( $markdown ) ) {
			return '<p>See the <a href="https://github.com/' . esc_attr( $this->github_user ) . '/' . esc_attr( $this->github_repo ) . '/releases">GitHub releases page</a> for changelog details.</p>';
		}

		// Very lightweight Markdown → HTML for the most common patterns.
		$html = esc_html( $markdown );
		$html = preg_replace( '/^#{1,3} (.+)$/m', '<strong>$1</strong>', $html );
		$html = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $html );
		$html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
		$html = nl2br( $html );

		return $html;
	}

	// -------------------------------------------------------------------------
	// Cache management
	// -------------------------------------------------------------------------

	/**
	 * Clear the cached release data — useful to call when saving plugin settings
	 * or when an admin triggers a manual update check.
	 */
	public static function flush_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}
}
