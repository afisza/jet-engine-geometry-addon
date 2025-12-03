<?php
/**
 * GitHub Updater for JetEngine Geometry Addon
 * Allows automatic updates from GitHub Releases
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_GitHub_Updater
 */
class Jet_Geometry_GitHub_Updater {

	/**
	 * GitHub repository owner
	 *
	 * @var string
	 */
	private $owner = 'afisza';

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	private $repo = 'jet-engine-geometry-addon';

	/**
	 * Plugin file path
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_file = JET_GEOMETRY_ADDON_FILE;
		$this->plugin_slug = plugin_basename( $this->plugin_file );

		// Hook into WordPress update system
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
	}

	/**
	 * Check for updates from GitHub
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_updates( $transient ) {
		// If we've already checked, return early
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get latest release from GitHub
		$latest_release = $this->get_latest_release();

		if ( ! $latest_release ) {
			return $transient;
		}

		$current_version = JET_GEOMETRY_ADDON_VERSION;
		$latest_version  = $this->normalize_version( $latest_release->tag_name );

		// Compare versions
		if ( version_compare( $current_version, $latest_version, '<' ) ) {
			$plugin_data = get_plugin_data( $this->plugin_file );

			$update = new stdClass();
			$update->id          = $this->plugin_slug;
			$update->slug        = dirname( $this->plugin_slug );
			$update->plugin      = $this->plugin_slug;
			$update->new_version = $latest_version;
			$update->url         = $plugin_data['PluginURI'];
			$update->package     = $this->get_download_url( $latest_release );
			$update->tested      = '6.4';
			$update->requires    = '6.0';
			$update->requires_php = '7.4';

			$transient->response[ $this->plugin_slug ] = $update;
		}

		return $transient;
	}

	/**
	 * Get latest release from GitHub API
	 *
	 * @return object|false Release object or false on failure.
	 */
	private function get_latest_release() {
		$cache_key = 'jet_geometry_latest_release';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$api_url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->owner,
			$this->repo
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( ! $data || ! isset( $data->tag_name ) ) {
			return false;
		}

		// Cache for 12 hours
		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Get download URL for release
	 *
	 * @param object $release Release object.
	 * @return string Download URL.
	 */
	private function get_download_url( $release ) {
		// Look for zip asset
		foreach ( $release->assets as $asset ) {
			if ( 'application/zip' === $asset->content_type || 'application/x-zip-compressed' === $asset->content_type ) {
				return $asset->browser_download_url;
			}
		}

		// Fallback: create zip from source
		return sprintf(
			'https://github.com/%s/%s/archive/refs/tags/%s.zip',
			$this->owner,
			$this->repo,
			$release->tag_name
		);
	}

	/**
	 * Normalize version string (remove 'v' prefix)
	 *
	 * @param string $version Version string.
	 * @return string Normalized version.
	 */
	private function normalize_version( $version ) {
		return ltrim( $version, 'v' );
	}

	/**
	 * Plugin API call for update information
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Arguments.
	 * @return object|false Plugin information or false.
	 */
	public function plugin_api_call( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( $this->plugin_slug ) !== $args->slug ) {
			return $result;
		}

		$latest_release = $this->get_latest_release();

		if ( ! $latest_release ) {
			return $result;
		}

		$plugin_data = get_plugin_data( $this->plugin_file );

		$result                = new stdClass();
		$result->name          = $plugin_data['Name'];
		$result->slug          = dirname( $this->plugin_slug );
		$result->version       = $this->normalize_version( $latest_release->tag_name );
		$result->author        = $plugin_data['Author'];
		$result->homepage      = $plugin_data['PluginURI'];
		$result->requires      = '6.0';
		$result->tested        = '6.4';
		$result->requires_php  = '7.4';
		$result->download_link  = $this->get_download_url( $latest_release );
		$result->sections      = array(
			'description' => $plugin_data['Description'],
			'changelog'   => $this->format_changelog( $latest_release->body ),
		);

		return $result;
	}

	/**
	 * Format changelog from release body
	 *
	 * @param string $body Release body.
	 * @return string Formatted changelog.
	 */
	private function format_changelog( $body ) {
		if ( empty( $body ) ) {
			return '<p>' . esc_html__( 'No changelog available.', 'jet-geometry-addon' ) . '</p>';
		}

		// Convert markdown to HTML (basic)
		$body = wp_kses_post( $body );
		$body = nl2br( $body );

		return $body;
	}

	/**
	 * Post install hook
	 *
	 * @param bool  $response   Response.
	 * @param array $hook_extra Extra hook data.
	 * @param array $result      Result.
	 * @return bool Response.
	 */
	public function post_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $response;
		}

		// Clear update cache
		delete_transient( 'jet_geometry_latest_release' );

		return $response;
	}
}
