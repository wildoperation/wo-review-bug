<?php
/**
 * Update Namespace to avoid plugin conflicts.
 */
namespace WOWPRB;

/**
 * Handle review banner for a WordPress plugin.
 *
 * Version 0.1.1
 */
class WPPluginReviewBug {


	/**
	 * Plugin 'slug' (filename w/o path or extenstion)
	 *
	 * @var string $slug
	 */
	private $slug;

	/**
	 * Prefix used on options, vars, etc.
	 *
	 * @var string $prefix
	 */
	private $prefix;

	/**
	 * Store the plugin name from get_plugin_data
	 *
	 * @var string $plugin_name
	 */
	private $plugin_name;

	/**
	 * Store the text domain from get_plugin_data
	 *
	 * @var string $text_domain
	 */
	private $text_domain;

	/**
	 * Typical usage:
	 * new WOReviewBug(__FILE__)
	 * from main plugin file.
	 *
	 * Sets variables, creates hooks.
	 *
	 * @param string $plugin_file Pass the plugin file (__FILE__) to the class.
	 */
	public function __construct( $plugin_file ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data           = get_plugin_data( $plugin_file );
		$plugin_basename_parts = explode( '/', plugin_basename( $plugin_file ) );

		$this->slug        = str_replace( '.php', '', strtolower( array_pop( $plugin_basename_parts ) ) );
		$this->plugin_name = $plugin_data['Name'];
		$this->text_domain = $plugin_data['TextDomain'];
		$this->prefix      = 'worb';

		register_activation_hook( $plugin_file, array( &$this, 'set_activation_date' ) );

		add_action( 'admin_init', array( &$this, 'check_nobug_flag' ), 10 );
		add_action( 'admin_init', array( &$this, 'check_activation_date' ), 20 );
	}

	/**
	 * Create a relevant option name
	 *
	 * @param string $suffix Appended to prefix and plugin slug.
	 *
	 * @return string
	 */
	private function get_option_name( $suffix ) {
		$option_name = implode( '_', array( $this->prefix, $this->slug, $suffix ) );

		if ( strlen( $option_name ) > 150 ) {
			error_log( 'long option name. consider revising. ' . $option_name );
		}

		return $option_name;
	}

	/**
	 * Sets the activation date option if one does not exist.
	 *
	 * @return void
	 */
	public function set_activation_date() {
		if ( ! get_option( $this->get_option_name( 'activation' ) ) ) {
			add_option( $this->get_option_name( 'activation' ), time() );
		}
	}

	/**
	 * Compares current timestamp to activation timestamp. Adds admin_notices hook if necessary.
	 *
	 * @return void
	 */
	public function check_activation_date() {

		if ( $this->should_set_nobug() ) {
			return false;
		}

		if ( ! get_option( $this->get_option_name( 'nobug' ) ) ) {
			$activation_timestamp = get_option( $this->get_option_name( 'activation' ) );

			if ( ! $activation_timestamp ) {
				$this->set_activation_date();
				return false;
			}

			$notice_date = strtotime( '+7 days', $activation_timestamp );

			if ( time() >= $notice_date ) {
				add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
			}
		}
	}

	/**
	 * Creates a URL to plugin reviews.
	 *
	 * @return string
	 */
	private function get_review_url() {
		$review_path = apply_filters( 'worb_review_path_' . $this->slug, $this->slug, $this->plugin_name );
		return apply_filters( 'worb_review_url_' . $this->slug, 'https://wordpress.org/support/plugin/' . $review_path . '/reviews/', $this->slug, $this->plugin_name );
	}

	/**
	 * Creates a URL to disable a review request banner
	 *
	 * @return string
	 */
	private function get_nobug_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

		if ( ! $uri ) {
			return '';
		}

		$uri = remove_query_arg( array( '_wpnonce' ), admin_url( $uri ) );

		return add_query_arg( $this->get_option_name( 'nobug' ), 1, $uri );
	}

	/**
	 * Displays the admin review banner.
	 */
	public function display_admin_notice() {
		?>
		<div class="notice notice-info">
			<p>You&rsquo;ve been using <strong><?php esc_attr_e( $this->plugin_name, $this->text_domain ); ?></strong> for a while now. We&rsquo;d love your feedback! <a href="<?php echo esc_url( $this->get_review_url() ); ?>" target="_blank">Leave a Review</a> | <a href="<?php echo esc_url( $this->get_nobug_url() ); ?>">Don&rsquo;t Ask Again.</a></p>
		</div>
		<?php
	}

	/**
	 * Checks for the 'nobug' $_GET parameter for this plugin.
	 *
	 * @return boolean
	 */
	private function should_set_nobug() {
		return isset( $_GET[ $this->get_option_name( 'nobug' ) ] ) && intval( $_GET[ $this->get_option_name( 'nobug' ) ] ) === 1;
	}

	/**
	 * Check if the nobug flag should be set.
	 *
	 * @return void
	 */
	public function check_nobug_flag() {
		if ( $this->should_set_nobug() ) {
			$this->set_nobug_option();
		}
	}

	/**
	 * Sets the nobug option in the database
	 *
	 * @return void
	 */
	private function set_nobug_option() {
		update_option( $this->get_option_name( 'nobug' ), true );
	}
}
