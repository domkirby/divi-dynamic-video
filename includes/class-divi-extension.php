<?php
/**
 * Loads the Divi Extension and registers the VideoEmbed module.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'DiviExtension' ) ) {
	return;
}

class DVP_Divi_Extension extends DiviExtension {

	/**
	 * The gettext domain for the extension's translations.
	 *
	 * @var string
	 */
	public $gettext_domain = 'divi-video-post';

	/**
	 * The extension's WP Plugin name.
	 *
	 * @var string
	 */
	public $name = 'divi-video-post';

	/**
	 * The extension's version.
	 *
	 * @var string
	 */
	public $version = DVP_VERSION;

	/**
	 * DVP_Divi_Extension constructor.
	 *
	 * @param string $name
	 * @param array  $args
	 */
	public function __construct( $name = 'divi-video-post', $args = [] ) {
		$this->plugin_dir     = DVP_PLUGIN_DIR;
		$this->plugin_dir_url = DVP_PLUGIN_URL;

		parent::__construct( $name, $args );
	}
}

new DVP_Divi_Extension();
