<?php
/**
 * Plugin Loader.
 *
 * @package {{package}}
 * @since 1.0.0
 */

namespace AiBuilder;

use AiBuilder\Inc\Ajax\AjaxInit;
use AiBuilder\Inc\Api\ApiInit;
use AiBuilder\Inc\Classes\Zipwp\Ai_Builder_ZipWP_Api;
use AiBuilder\Inc\Classes\Zipwp\Ai_Builder_ZipWP_Integration;
use AiBuilder\Inc\Traits\Helper;
use STImporter\Importer\ST_Importer_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ai_Builder_Plugin_Loader
 *
 * @since 1.0.0
 */
class Ai_Builder_Plugin_Loader {
	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * List of hosting providers.
	 *
	 * @access private
	 * @var array<int, string>
	 * @since 1.0.0
	 */
	private $hosting_providers = array(
		'unaux',
		'epizy',
		'ezyro',
	);

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		spl_autoload_register( [ $this, 'autoload' ] );
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ), 99 );

		/*
			// add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		*/
		add_action( 'admin_menu', [ $this, 'add_theme_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
		$this->define_constants();
		$this->setup_classes();
	}

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class class name.
	 *
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$class_to_load = $class;

		$filename = strtolower(
			(string) preg_replace(
				[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
				[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
				$class_to_load
			)
		);

		$file = AI_BUILDER_DIR . $filename . '.php';

		// if the file redable, include it.
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Load plugin files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_plugin() {
		require_once AI_BUILDER_DIR . 'inc/classes/ai-builder-loader.php';
		require_once AI_BUILDER_DIR . 'inc/compatibility/ai-builder-compatibility.php';
	}

	/**
	 * Include required constants.
	 *
	 * @return void
	 */
	public function define_constants() {

		if ( ! defined( 'ZIPWP_APP' ) ) {
			define( 'ZIPWP_APP', apply_filters( 'ai_builder_zip_app_url', 'https://app.zipwp.com/auth' ) );
		}

		if ( ! defined( 'ZIPWP_API' ) ) {
			define( 'ZIPWP_API', apply_filters( 'ai_builder_templates_zip_api_url', 'https://api.zipwp.com/api' ) );
		}

		if ( ! defined( 'ZIPWP_API_V1' ) ) {
			define( 'ZIPWP_API_V1', apply_filters( 'ai_builder_templates_zip_api_url', 'https://api.zipwp.com/api/v1' ) );
		}
	}

	/**
	 * Include required classes.
	 *
	 * @return void
	 */
	public function setup_classes() {

		/* Init API */
		ApiInit::Instance();

		if ( is_admin() ) {
			/* Ajax init */
			AjaxInit::Instance();
		}
	}

	/**
	 * Load Plugin Text Domain.
	 * This will load the translation textdomain depending on the file priorities.
	 *      1. Global Languages /wp-content/languages/ai-builder/ folder
	 *      2. Local dorectory /wp-content/plugins/ai-builder/languages/ folder
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		// Default languages directory.
		$lang_dir = AI_BUILDER_DIR . 'languages/';

		/**
		 * Filters the languages directory path to use for plugin.
		 *
		 * @param string $lang_dir The languages directory path.
		 */
		$lang_dir = apply_filters( 'ai_builder_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter.
		global $wp_version;

		$get_locale = get_locale();

		if ( $wp_version >= 4.7 ) {
			$get_locale = get_user_locale();
		}

		$locale = apply_filters( 'plugin_locale', $get_locale, 'ai-builder' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'ai-builder', $locale );

		// Setup paths to current locale file.
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;
		$mofile_local  = $lang_dir . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/ai-builder/ folder.
			load_textdomain( 'ai-builder', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/ai-builder/languages/ folder.
			load_textdomain( 'ai-builder', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'ai-builder', false, $lang_dir );
		}
	}

	/**
	 * Add a theme page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_theme_page() {
		add_theme_page(
			__( 'AI Builder', 'astra-sites' ),
			__( 'AI Builder', 'astra-sites' ),
			'manage_options',
			'ai-builder',
			[ $this, 'theme_page' ]
		);
	}

	/**
	 * Theme page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function theme_page() {
		?>
			<div id="ai-builder-root" class="font-sans"></div>
		<?php
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook hook.
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook = '' ) {
		if ( 'appearance_page_ai-builder' !== $hook ) {
			return;
		}

		remove_all_actions( 'admin_notices' );

		$assets_file_path = AI_BUILDER_DIR . '/inc/assets/build/main.asset.php';

		if ( ! file_exists( $assets_file_path ) ) {
			return;
		}

		$assets = require_once AI_BUILDER_DIR . '/inc/assets/build/main.asset.php';

		if ( ! isset( $assets ) ) {
			return;
		}

		$partner_id = get_option( 'zipwp_partner_url_param', '' );
		$partner_id = is_string( $partner_id ) ? sanitize_text_field( $partner_id ) : '';
		$zipwp_auth = array(
			'screen_url'   => ZIPWP_APP,
			'redirect_url' => admin_url( 'themes.php?page=ai-builder' ),
			'source'       => 'starter-templates',
		);

		if ( ! empty( $partner_id ) ) {
			$zipwp_auth['partner_id'] = $partner_id;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'ai-builder',
			AI_BUILDER_URL . 'inc/assets/build/main.js',
			array_merge(
				$assets['dependencies'],
				array(
					'react',
					'react-dom',
					'updates',
				)
			),
			$assets['version'],
			true
		);
		wp_enqueue_style( 'ai-builder', AI_BUILDER_URL . 'inc/assets/build/style-main.css', [], $assets['version'] );

		// Required variables for JS.
		wp_localize_script( 'ai-builder', 'aiBuilderVars', $this->get_localize_variable() );
		wp_localize_script(
			'ai-builder',
			'wpApiSettings',
			array(
				'root'       => esc_url_raw( get_rest_url() ),
				'nonce'      => wp_installing() && ! is_multisite() ? '' : wp_create_nonce( 'wp_rest' ),
				'zipwp_auth' => $zipwp_auth,
			)
		);

		$text_domain = apply_filters( 'ai_builder_textdomain', 'ai-builder' );
		$locale_path = apply_filters( 'ai_builder_languages_directory', '' );

		wp_set_script_translations( 'ai-builder', $text_domain, $locale_path );

		// Required for install theme.
		wp_enqueue_script( 'ai-builder-install-theme', AI_BUILDER_URL . 'inc/assets/js/install-theme.js', array( 'jquery', 'updates' ), AI_BUILDER_VER, true );

		// Google fonts.
		wp_enqueue_style( 'ai-builder-google-fonts', $this->google_fonts_url(), array( 'ai-builder' ), 'all' );
	}

	/**
	 * Admin body class.
	 *
	 * @param string $classes classes.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function admin_body_class( $classes ) {
		$ai_builder_class_name = isset( $_GET['page'] ) && 'ai-builder' === $_GET['page'] ? 'ai-builder' : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$classes .= ' ' . $ai_builder_class_name;
		return $classes;
	}

	/**
	 * Generate and return the Google fonts url.
	 *
	 * @since 1.0.1
	 * @return string
	 */
	public function google_fonts_url() {

		$fonts_url     = '';
		$font_families = array(
			'Inter:400,500,600',
			'Figtree:400,500,600,700',
		);

		$query_args = array(
			'family'  => rawurlencode( implode( '|', $font_families ) ),
			'subset'  => rawurlencode( 'latin,latin-ext' ),
			'display' => 'swap',
		);

		return add_query_arg( $query_args, '//fonts.googleapis.com/css' );
	}

	/**
	 * Check if we should report error or not.
	 * Skipping error reporting for a few hosting providers.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function should_report_error() {

		/**
		 * Byassing error reporting for a few hosting providers.
		 */
		foreach ( $this->hosting_providers as $provider ) {
			if ( strpos( ABSPATH, $provider ) !== false ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if legacy Beaver Builder support is enabled.
	 *
	 * @since 1.2.28
	 * @return bool Returns `true` if legacy Beaver Builder support is enabled, `false` otherwise.
	 */
	public static function is_legacy_beaver_builder_enabled() {
		/**
		 * Filter to enable legacy Beaver Builder support.
		 *
		 * @param bool $enabled Default value indicating if Beaver Builder support is enabled. Default to `false`.
		 *
		 * @since 1.2.28
		 * @return bool Returns `true` if Beaver Builder support is enabled, `false` otherwise.
		 */
		return boolval( apply_filters( 'astra_sites_enable_legacy_beaver_builder_support', false ) );
	}

	/**
	 * Get localize variable.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_localize_variable() {
		$theme_status = class_exists( 'STImporter\Importer\ST_Importer_Helper' ) ? ST_Importer_Helper::get_instance()->get_theme_status() : '';

		$plans = Ai_Builder_ZipWP_Api::Instance()->get_zip_plans();

		$team_name    = is_array( $plans['data'] ) && isset( $plans['data']['team']['name'] ) ? $plans['data']['team']['name'] : '';
		$plan_name    = is_array( $plans['data'] ) && isset( $plans['data']['active_plan']['slug'] ) ? $plans['data']['active_plan']['slug'] : '';
		$support_link = 'https://wpastra.com/starter-templates-support/?ip=' . $this->get_client_ip();

		return array(
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'_ajax_nonce'              => wp_create_nonce( 'astra-sites' ),
			'zipwp_auth_nonce'         => wp_create_nonce( 'zipwp-auth-nonce' ),
			'adminUrl'                 => admin_url(),
			'imageDir'                 => AI_BUILDER_URL . 'inc/assets/images/',
			'supportLink'              => $support_link,
			'logoUrl'                  => apply_filters( 'ai_builder_logo', AI_BUILDER_URL . 'inc/assets/images/logo.svg' ),
			'placeholder_images'       => Helper::get_image_placeholders(),
			'reportError'              => $this->should_report_error(),
			'zip_token_exists'         => Helper::get_token() !== '' ? true : false,
			'themeStatus'              => $theme_status,
			'firstImportStatus'        => get_option( 'astra_sites_import_complete', false ),
			'analytics'                => get_site_option( 'bsf_analytics_optin', false ),
			'siteUrl'                  => site_url(),
			'installed'                => __( 'Installed! Activating..', 'astra-sites' ),
			'activating'               => __( 'Activating...', 'astra-sites' ),
			'activated'                => __( 'Activated!', 'astra-sites' ),
			'installing'               => __( 'Installing...', 'astra-sites' ),
			'logoUrlDark'              => apply_filters( 'st_ai_onboarding_logo_dark', AI_BUILDER_URL . 'inc/assets/images/build-with-ai/st-logo-dark.svg' ),
			'logoUrlLight'             => apply_filters( 'st_ai_onboarding_logo_light', AI_BUILDER_URL . 'inc/assets/images/logo.svg' ),
			'zip_plans'                => $plans && isset( $plans['data'] ) ? $plans['data'] : array(),
			'dashboard_url'            => admin_url(),
			'migrateSvg'               => apply_filters( 'ai_builder_migrate_svg', AI_BUILDER_URL . 'inc/assets/images/build-with-ai/migrate.svg' ),
			'business_details'         => Ai_Builder_ZipWP_Integration::get_business_details(),
			'skipFeatures'             => 'yes' === apply_filters( 'ai_builder_skip_features', 'no' ),
			'show_premium_badge'       => 'yes' === apply_filters( 'ai_builder_show_premium_badge', 'yes' ),
			'show_premium_templates'   => 'yes' === apply_filters( 'ai_builder_show_premium_templates', 'yes' ),
			'parent_plugin'            => apply_filters( 'ai_builder_parent_plugin', 'wp-astra-sites' ),
			'failed_sites'             => $this->get_failed_sites(),
			'filtered_data'            => apply_filters(
				'ai_builder_limit_exceeded_popup_strings',
				array(
					'main_content'      => sprintf(
						/* translators: %1$s: team name, %2$s: plan name */
						__(
							'Your current active organization is %1$s, which is on the %2$s plan. You have reached the maximum number of sites allowed to be created on %2$s plan.',
							'astra-sites'
						),
						$team_name,
						$plan_name
					),
					'secondary_content' => sprintf(
						/* translators: %1$s: team name */
						__(
							'Please upgrade the plan for %s in order to create more sites.',
							'astra-sites'
						),
						$team_name,
					),
					'upgrade_text'      => __( 'Unlock Full Power', 'astra-sites' ),
					'upgrade_url'       => 'https://app.zipwp.com/founders-deal?source=starter-templates',
					'contact_url'       => $support_link,
					'contact_text'      => __( 'Contact Support', 'astra-sites' ),
				)
			),
			'default_website_language' => apply_filters( 'ai_builder_default_website_language', 'en' ),
			'default_business_type'    => apply_filters( 'ai_builder_default_business_type', '' ),
			'show_zip_plan'            => apply_filters( 'ai_builder_show_zip_plan_details', true ),
			'hide_site_features'       => apply_filters( 'ai_builder_hidden_site_features', array() ),
			'hideDashboardButton'      => 'yes' === apply_filters( 'ai_builder_hide_visit_dashboard_button', 'no' ),
			'isElementorDisabled'      => get_option( 'st-elementor-builder-flag' ),
			'isBeaverBuilderDisabled'  => get_option( 'st-beaver-builder-flag' ) || ! self::is_legacy_beaver_builder_enabled(),
			'supportedPageBuilders'    => apply_filters( 'ai_builder_supported_page_builders', array( 'block-editor', 'elementor' ) ),
			'hideCreditsWarningModal'  => apply_filters( 'ai_builder_hide_credits_warning_modal', false ), // Added for white label AI Builder.
		);
	}

	/**
	 * Get failed sites.
	 *
	 * @since 1.2.3
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_failed_sites() {

		$failed_sites        = get_option( 'astra_sites_import_failed_sites', array() );
		$active_failed_sites = array();
		if ( is_array( $failed_sites ) ) {
			foreach ( $failed_sites as $site ) {
				if ( ! $site['is_expired'] ) {
					$active_failed_sites[] = $site;
				}
			}
		}

		return $active_failed_sites;
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 1.0.9
	 *
	 * @return string
	 */
	public function get_client_ip() {
		$ipaddress = '';
		if ( getenv( 'HTTP_CLIENT_IP' ) ) {
			$ipaddress = getenv( 'HTTP_CLIENT_IP' );
		} elseif ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
			$ipaddress = getenv( 'HTTP_X_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_X_FORWARDED' ) ) {
			$ipaddress = getenv( 'HTTP_X_FORWARDED' );
		} elseif ( getenv( 'HTTP_FORWARDED_FOR' ) ) {
			$ipaddress = getenv( 'HTTP_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_FORWARDED' ) ) {
			$ipaddress = getenv( 'HTTP_FORWARDED' );
		} elseif ( getenv( 'REMOTE_ADDR' ) ) {
			$ipaddress = getenv( 'REMOTE_ADDR' );
		} else {
			$ipaddress = 'UNKNOWN';
		}
		return $ipaddress;
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Ai_Builder_Plugin_Loader::get_instance();
