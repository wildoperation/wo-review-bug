<?php
/**
 * Version 1.0.0
 *
 * Update Namespace to avoid plugin conflicts.
 *
 * @package WPPluginReviewBug
 */

namespace WOWPRB;

/**
 * Handle review banner for a WordPress plugin.
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
	 * Show the review notice to users with this capability.
	 *
	 * @var string $capability
	 */
	private $capability;

	/**
	 * The number of days to snooze before showing again.
	 *
	 * @var int $snooze_days
	 */
	private $snooze_days;

	/**
	 * The URL to review the plugin.
	 *
	 * @var string $review_url
	 */
	private $review_url;

	/**
	 * The messages displayed to the user.
	 *
	 * @var array $messages
	 */
	private $messages;

	/**
	 * Typical usage:
	 * new WOReviewBug(__FILE__)
	 * from main plugin file.
	 *
	 * Sets variables, creates hooks.
	 *
	 * @param string $plugin_file Pass the plugin file (__FILE__) to the class.
	 * @param string $plugin_slug The plugin slug on the WordPress repository. If empty, one will be generated from the filename.
	 * @param array  $args An array of settings that overrides default arguments.
	 */
	public function __construct( $plugin_file, $plugin_slug = '', $messages = array(), $args = array() ) {

		if ( is_admin() ) {

			$default_args = array(
				'plugin_name'       => '',
				'plugin_textdomain' => '',
				'capability'        => 'manage_options',
				'prefix'            => 'worb',
				'snooze_days'       => 7,
				'review_url'        => '',
			);

			$args = wp_parse_args( $args, $default_args );

			/**
			 * Get plugin information if not provided as params.
			 */
			if ( ! $plugin_slug ) {
				$plugin_basename_parts = explode( '/', plugin_basename( $plugin_file ) );
				$plugin_slug           = str_replace( '.php', '', strtolower( array_pop( $plugin_basename_parts ) ) );
			}

			if ( ! $args['plugin_name'] || ! $args['plugin_textdomain'] ) {
				$plugin_data = $this->wo_get_plugin_data( $plugin_file );

				if ( ! $args['plugin_name'] ) {
					$args['plugin_name'] = $plugin_data['Name'];
				}

				if ( ! $args['plugin_textdomain'] ) {
					$args['plugin_textdomain'] = ( isset( $plugin_data['TextDomain'] ) && $plugin_data['TextDomain'] !== '' ) ? $plugin_data['TextDomain'] : $plugin_slug;
				}
			}

			/**
			 * Setup variables
			 */
			$this->slug        = $plugin_slug;
			$this->plugin_name = $args['plugin_name'];
			$this->text_domain = $args['plugin_textdomain'];
			$this->prefix      = ( $args['prefix'] !== '' ) ? sanitize_title( $args['prefix'] ) : $default_args['prefix'];
			$this->capability  = ( $args['capability'] !== '' ) ? $args['capability'] : $default_args['capability'];
			$this->snooze_days = ( intval( $args['snooze_days'] ) > 0 ) ? intval( $args['snooze_days'] ) : $default_args['snooze_days'];
			$this->review_url  = ( $args['review_url'] != '' ) ? $args['review_url'] : $this->default_review_url();

			/**
			 * Setup messages
			 */
			$default_messages = array(
				'intro'            => 'You&rsquo;ve been using ' . $this->plugin_name . ' for a while now. We&rsquo;d love your feedback!',
				'rate_link_text'   => 'Rate the plugin',
				'remind_link_text' => 'Remind me later',
				'nobug_link_text'  => 'Don&rsquo;t ask again',
				'notice_class'     => 'notice-info',
			);

			$this->messages = wp_parse_args( $messages, $default_messages );

			/**
			 * Plugin activation hook
			 */
			register_activation_hook( $plugin_file, array( &$this, 'set_check_timestamp' ) );

			/**
			 * Hooks
			 */
			add_action( 'init', array( &$this, 'check' ), 20 );
		}
	}

	/**
	 * Get data from plugin. Require WP core file if needed.
	 *
	 * @param string $plugin_file The current plugin file.
	 * @return array Plugin data.
	 */
	private function wo_get_plugin_data( $plugin_file ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( $plugin_file );
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
	 * Sets the check timestamp option if one does not exist. Or overrides if forced.
	 *
	 * @return void
	 */
	public function set_check_timestamp( $force = false ) {
		if ( $force ) {
			update_option( $this->get_option_name( 'check' ), time() );
		} elseif ( ! get_option( $this->get_option_name( 'check' ) ) ) {
			add_option( $this->get_option_name( 'check' ), time() );
		}
	}

	/**
	 * Compares current timestamp to the check timestamp. Adds admin_notices hook if necessary.
	 *
	 * @return void
	 */
	public function check() {

		if ( current_user_can( $this->capability ) ) {
			if ( ! get_option( $this->get_option_name( 'nobug' ) ) ) {
				$check_timestamp = get_option( $this->get_option_name( 'check' ) );

				if ( ! $check_timestamp ) {
					$this->set_check_timestamp();
				} else {

					$notice_date = strtotime( '+' . $this->snooze_days . ' days', $check_timestamp );

					if ( time() >= $notice_date ) {
						add_action( 'admin_notices', array( &$this, 'display_admin_notice' ) );
						add_action( 'wp_ajax_' . $this->action_string(), array( &$this, 'actions' ) );
						add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue' ) );
						add_action( 'admin_print_footer_scripts', array( &$this, 'notice_script' ) );
					}
				}
			}
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

	/**
	 * Creates a URL to plugin reviews.
	 *
	 * @return string
	 */
	private function default_review_url() {
		return 'https://wordpress.org/support/plugin/' . $this->slug . '/reviews/';
	}

	/**
	 * Displays the admin review banner.
	 */
	public function display_admin_notice() {
		?>
		<style type="text/css">
			#<?php esc_attr_e( $this->prefix ); ?>-<?php esc_attr_e( $this->slug ); ?> .actions a { margin-left: 10px; }
			#<?php esc_attr_e( $this->prefix ); ?>-<?php esc_attr_e( $this->slug ); ?> .actions a:first-child { margin-left: 0; }
		</style>
		<div class="notice <?php esc_attr_e( $this->messages['notice_class'] ); ?> is-dismissible" id="<?php esc_attr_e( $this->prefix ); ?>-<?php esc_attr_e( $this->slug ); ?>">
			<p><?php esc_html_e( $this->messages['intro'], $this->text_domain ); ?></p>
			<p class="actions">
				<a id="<?php esc_attr_e( $this->prefix ); ?>-rate-<?php esc_attr_e( $this->slug ); ?>" href="<?php echo esc_url( $this->review_url ); ?>" target="_blank" class="<?php esc_attr_e( $this->prefix ); ?>-rate <?php esc_attr_e( $this->prefix ); ?>-action button button-primary">
					<?php esc_html_e( $this->messages['rate_link_text'], $this->text_domain ); ?>
				</a>
				<a id="<?php esc_attr_e( $this->prefix ); ?>-later-<?php esc_attr_e( $this->slug ); ?>" href="#" class="<?php esc_attr_e( $this->prefix ); ?>-action <?php esc_attr_e( $this->prefix ); ?>-later"><?php esc_html_e( $this->messages['remind_link_text'], $this->text_domain ); ?></a>
				<a id="<?php esc_attr_e( $this->prefix ); ?>-nobug-<?php esc_attr_e( $this->slug ); ?>" href="#" class="<?php esc_attr_e( $this->prefix ); ?>-action <?php esc_attr_e( $this->prefix ); ?>-nobug"><?php esc_html_e( $this->messages['nobug_link_text'], $this->text_domain ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * This all needs to be done
	 */
	public function actions() {

		check_ajax_referer( $this->nonce_string(), 'security' );

		if ( ! isset( $_POST['action_performed'] ) ) {
			wp_die();
		}

		if ( 'worb-later' == $_POST['action_performed'] ) {
			$this->set_check_timestamp( true );
		} elseif ( 'worb-nobug' == $_POST['action_performed'] ) {
			$this->set_nobug_option();
		}

		wp_die();
	}

	public function enqueue() {
		wp_enqueue_script( 'jquery' );
	}

	private function nonce_string() {
		return $this->prefix . '-n-' . $this->slug;
	}

	private function action_string() {
		return str_replace(
			'-',
			'_',
			$this->prefix . '_' . $this->slug
		);
	}

	public function notice_script() {

		$ajax_nonce = wp_create_nonce( $this->nonce_string() );

		?>

		<script type="text/javascript" id="<?php esc_attr_e( $this->prefix ); ?>-<?php esc_attr_e( $this->slug ); ?>-script">
			jQuery( document ).ready( function( $ ){

				var $actions = $('#<?php esc_html_e( $this->prefix ); ?>-<?php esc_html_e( $this->slug ); ?> .<?php esc_html_e( $this->prefix ); ?>-action');

				function close_wowprcv_notice($notice) {
					$notice.slideUp('fast', function() {
						$notice.remove();
					})
				}

				function wowprcv_post_data($elem, this_action) {
					var data = {
						action: '<?php esc_html_e( $this->action_string() ); ?>',
						security: '<?php echo esc_html_e( $ajax_nonce ); ?>',
						action_performed: this_action,
					};

					$.post( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', data, function( response ) {
						close_wowprcv_notice($actions.closest('.notice'));
					});
				}

				$actions.click( function( e ){
					var $this = $(this);
					var action_performed = $this.hasClass('<?php esc_html_e( $this->prefix ); ?>-nobug') ? 'worb-nobug' : 'worb-later';

					if (!$this.hasClass('<?php esc_html_e( $this->prefix ); ?>-rate')) {
						e.preventDefault();
						wowprcv_post_data($this, action_performed);
					}
				} );

				$actions.closest('.notice').on('click', '.notice-dismiss', function(){
					wowprcv_post_data($(this), 'worb-later');
				});

			});
		</script>

		<?php
	}
}
