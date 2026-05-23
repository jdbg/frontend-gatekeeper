<?php
/**
 * Plugin Name:       Frontend Gatekeeper
 * Plugin URI:        https://hwp.bg/frontend-gatekeeper/
 * Description:       Hides the public frontend unless a configured URL parameter is present, then keeps that parameter on same-site links.
 * Version:           1.0.3
 * Requires at least: 5.7
 * Requires PHP:      8.0
 * Author:            Jordan Hlebarov
 * Author URI:        https://hwp.bg
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       frontend-gatekeeper
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fronga_Plugin {
	private const OPTION_NAME = 'fronga_settings';

	private const DEFAULT_PARAM_NAME = 'fronga_access';

	private const DEFAULT_BLOCKED_MESSAGE = 'This website is not currently available.';

	private static $instance = null;

	private $request_is_authorized = false;

	private $home_parts_cache = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_action_link' ] );
		add_action( 'template_redirect', [ $this, 'handle_frontend_gate' ], 0 );
		add_filter( 'home_url', [ $this, 'append_access_parameter_to_generated_urls' ], 10, 4 );
		add_filter( 'site_url', [ $this, 'append_access_parameter_to_generated_urls' ], 10, 4 );
		add_filter( 'page_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'post_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'post_type_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'term_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'category_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'tag_link', [ $this, 'append_access_parameter_to_url' ], 10 );
		add_filter( 'nav_menu_link_attributes', [ $this, 'append_access_parameter_to_link_attributes' ], 10 );
		add_filter( 'page_menu_link_attributes', [ $this, 'append_access_parameter_to_link_attributes' ], 10 );
		add_filter( 'render_block', [ $this, 'append_access_parameter_to_block_content' ], 10, 3 );
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_fronga-settings' !== $hook_suffix ) {
			return;
		}

		$handle = 'fronga-admin';

		wp_register_style( $handle, false, [], '1.0.3' );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $this->admin_inline_css() );

		wp_register_script( $handle, false, [], '1.0.3', true );
		wp_enqueue_script( $handle );

		$data = sprintf(
			'window.frongaData = { onLabel: %s, offLabel: %s };',
			wp_json_encode( __( 'On', 'frontend-gatekeeper' ) ),
			wp_json_encode( __( 'Off', 'frontend-gatekeeper' ) )
		);
		wp_add_inline_script( $handle, $data, 'before' );
		wp_add_inline_script( $handle, $this->admin_inline_js() );
	}

	private function admin_inline_css() {
		return '
			.fg-switch{position:relative;display:inline-block;width:46px;height:24px;vertical-align:middle;margin-right:8px;}
			.fg-switch input{opacity:0;width:0;height:0;}
			.fg-switch .fg-slider{position:absolute;cursor:pointer;inset:0;background-color:#c3c4c7;border-radius:24px;transition:background-color .2s ease;}
			.fg-switch .fg-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background-color:#fff;border-radius:50%;transition:transform .2s ease;box-shadow:0 1px 2px rgba(0,0,0,.2);}
			.fg-switch input:checked + .fg-slider{background-color:#2271b1;}
			.fg-switch input:focus-visible + .fg-slider{box-shadow:0 0 0 2px #2271b1;outline:2px solid transparent;}
			.fg-switch input:checked + .fg-slider:before{transform:translateX(22px);}
			.fg-switch-state{font-weight:600;margin-left:4px;}
			.fg-access-url-wrap{display:flex;gap:8px;align-items:center;max-width:780px;}
			.fg-access-url-wrap input[type=url]{flex:1 1 auto;width:100%;font-family:Consolas,Monaco,monospace;}
			.fg-access-url-full{display:block;margin-top:8px;padding:8px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;font-family:Consolas,Monaco,monospace;word-break:break-all;white-space:pre-wrap;}
			.fg-copy-feedback{margin-left:8px;color:#008a20;font-weight:600;display:none;}
			.fg-copy-feedback.is-visible{display:inline;}
		';
	}

	private function admin_inline_js() {
		return '
			(function () {
				var toggle = document.getElementById("fg-enabled-toggle");
				var state = document.getElementById("fg-enabled-state");
				if (toggle && state) {
					var labels = window.frongaData || { onLabel: "On", offLabel: "Off" };
					toggle.addEventListener("change", function () {
						state.textContent = toggle.checked ? labels.onLabel : labels.offLabel;
					});
				}

				var btn = document.getElementById("fg-copy-button");
				if (!btn) { return; }
				btn.addEventListener("click", function () {
					var input = document.getElementById(btn.getAttribute("data-target"));
					if (!input) { return; }
					var value = input.value;
					var done = function () {
						var feedback = document.getElementById("fg-copy-feedback");
						if (!feedback) { return; }
						feedback.classList.add("is-visible");
						window.setTimeout(function () {
							feedback.classList.remove("is-visible");
						}, 2000);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(value).then(done, function () {
							input.select();
							try { document.execCommand("copy"); done(); } catch (e) {}
						});
					} else {
						input.select();
						try { document.execCommand("copy"); done(); } catch (e) {}
					}
				});
			})();
		';
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Frontend Gatekeeper', 'frontend-gatekeeper' ),
			__( 'Frontend Gatekeeper', 'frontend-gatekeeper' ),
			'manage_options',
			'fronga-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function add_settings_action_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=fronga-settings' ) ),
			esc_html__( 'Settings', 'frontend-gatekeeper' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function register_settings() {
		register_setting(
			'fronga_settings',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->default_settings(),
			]
		);

		add_settings_section(
			'fronga_section_access',
			__( 'Access URL Parameter', 'frontend-gatekeeper' ),
			function () {
				echo '<p>' . esc_html__( 'Visitors can view the frontend only when the configured parameter and value are present in the URL.', 'frontend-gatekeeper' ) . '</p>';
			},
			'fronga-settings'
		);

		add_settings_field(
			'enabled',
			__( 'Enable frontend gate', 'frontend-gatekeeper' ),
			[ $this, 'render_enabled_field' ],
			'fronga-settings',
			'fronga_section_access'
		);

		add_settings_field(
			'param_name',
			__( 'Parameter name', 'frontend-gatekeeper' ),
			[ $this, 'render_param_name_field' ],
			'fronga-settings',
			'fronga_section_access'
		);

		add_settings_field(
			'param_value',
			__( 'Parameter value', 'frontend-gatekeeper' ),
			[ $this, 'render_param_value_field' ],
			'fronga-settings',
			'fronga_section_access'
		);

		add_settings_field(
			'blocked_message',
			__( 'Blocked message', 'frontend-gatekeeper' ),
			[ $this, 'render_blocked_message_field' ],
			'fronga-settings',
			'fronga_section_access'
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->default_settings();
		$input    = is_array( $input ) ? $input : [];

		$param_name = isset( $input['param_name'] ) ? sanitize_key( $input['param_name'] ) : $defaults['param_name'];
		if ( '' === $param_name ) {
			$param_name = $defaults['param_name'];
		}

		$param_value = isset( $input['param_value'] ) ? sanitize_text_field( wp_unslash( $input['param_value'] ) ) : '';

		return [
			'enabled'         => ! empty( $input['enabled'] ) ? 1 : 0,
			'param_name'      => $param_name,
			'param_value'     => $param_value,
			'blocked_message' => isset( $input['blocked_message'] )
				? sanitize_textarea_field( wp_unslash( $input['blocked_message'] ) )
				: $defaults['blocked_message'],
		];
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings();
		$access_url = '';

		if ( '' !== $settings['param_value'] ) {
			$access_url = add_query_arg(
				[ $settings['param_name'] => $settings['param_value'] ],
				home_url( '/' )
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Frontend Gatekeeper', 'frontend-gatekeeper' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'fronga_settings' );
				do_settings_sections( 'fronga-settings' );
				submit_button();
				?>
			</form>
			<?php if ( '' !== $access_url ) : ?>
				<h2><?php esc_html_e( 'Access URL', 'frontend-gatekeeper' ); ?></h2>
				<div class="fg-access-url-wrap">
					<input
						type="url"
						id="fg-access-url"
						class="code"
						readonly
						value="<?php echo esc_attr( $access_url ); ?>"
						onfocus="this.select();"
					/>
					<button type="button" class="button button-secondary" id="fg-copy-button" data-target="fg-access-url">
						<?php esc_html_e( 'Copy to clipboard', 'frontend-gatekeeper' ); ?>
					</button>
					<span class="fg-copy-feedback" id="fg-copy-feedback" aria-live="polite">
						<?php esc_html_e( 'Copied!', 'frontend-gatekeeper' ); ?>
					</span>
				</div>
				<code class="fg-access-url-full"><?php echo esc_html( $access_url ); ?></code>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_enabled_field() {
		$settings = $this->settings();
		$is_on = ! empty( $settings['enabled'] );
		?>
		<label class="fg-switch" for="fg-enabled-toggle">
			<input type="checkbox" id="fg-enabled-toggle" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $is_on ); ?> />
			<span class="fg-slider" aria-hidden="true"></span>
		</label>
		<label for="fg-enabled-toggle">
			<span class="fg-switch-state" id="fg-enabled-state"><?php echo $is_on ? esc_html__( 'On', 'frontend-gatekeeper' ) : esc_html__( 'Off', 'frontend-gatekeeper' ); ?></span>
			<span class="description"><?php esc_html_e( 'Hide the public frontend unless the access URL parameter is present.', 'frontend-gatekeeper' ); ?></span>
		</label>
		<?php
	}

	public function render_param_name_field() {
		$settings = $this->settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[param_name]" value="<?php echo esc_attr( $settings['param_name'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Use letters, numbers, and underscores. Example: fronga_access', 'frontend-gatekeeper' ); ?></p>
		<?php
	}

	public function render_param_value_field() {
		$settings = $this->settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[param_value]" value="<?php echo esc_attr( $settings['param_value'] ); ?>" autocomplete="off" />
		<p class="description"><?php esc_html_e( 'Example: preview-2026. Visitors would open the site with ?parameter_name=parameter_value.', 'frontend-gatekeeper' ); ?></p>
		<?php
	}

	public function render_blocked_message_field() {
		$settings = $this->settings();
		?>
		<textarea class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[blocked_message]"><?php echo esc_textarea( $settings['blocked_message'] ); ?></textarea>
		<?php
	}

	public function handle_frontend_gate() {
		if ( $this->should_skip_gate() ) {
			return;
		}

		$settings = $this->settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$this->request_is_authorized = $this->request_has_valid_access_parameter( $settings );

		if ( ! $this->request_is_authorized ) {
			status_header( 404 );
			nocache_headers();
			wp_die(
				esc_html( $settings['blocked_message'] ),
				esc_html__( 'Not Found', 'frontend-gatekeeper' ),
				[
					'response'  => 404,
					'back_link' => false,
				]
			);
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_script' ] );
	}

	public function append_access_parameter_to_generated_urls( $url, $path = '', $scheme = null, $blog_id = null ) {
		unset( $path, $scheme, $blog_id );

		return $this->append_access_parameter_to_url( $url );
	}

	public function append_access_parameter_to_url( $url ) {
		if ( ! $this->request_is_authorized || ! $this->is_same_site_url( $url ) ) {
			return $url;
		}

		$settings = $this->settings();
		if ( '' === $settings['param_value'] ) {
			return $url;
		}

		return add_query_arg( [ $settings['param_name'] => $settings['param_value'] ], $url );
	}

	public function append_access_parameter_to_link_attributes( $attributes ) {
		if ( is_array( $attributes ) && ! empty( $attributes['href'] ) ) {
			$attributes['href'] = $this->append_access_parameter_to_url( $attributes['href'] );
		}

		return $attributes;
	}

	public function append_access_parameter_to_block_content( $block_content, $block = [], $instance = null ) {
		unset( $block, $instance );

		if ( ! $this->request_is_authorized || ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		return $this->append_access_parameter_with_regex( $block_content );
	}

	public function enqueue_frontend_script() {
		$settings = $this->settings();
		if ( ! $this->request_is_authorized || '' === $settings['param_value'] ) {
			return;
		}

		$handle = 'fronga-frontend';
		wp_register_script( $handle, false, [], '1.0.3', true );
		wp_enqueue_script( $handle );

		$data = sprintf(
			'window.frongaConfig = { name: %1$s, value: %2$s, home: %3$s };',
			wp_json_encode( $settings['param_name'] ),
			wp_json_encode( $settings['param_value'] ),
			wp_json_encode( home_url( '/' ) )
		);
		wp_add_inline_script( $handle, $data, 'before' );

		$script = '(function(){var cfg=window.frongaConfig||{};var name=cfg.name;var value=cfg.value;var home=cfg.home;function sameSite(url){try{var target=new URL(url,window.location.href);var base=new URL(home);if(target.origin!==base.origin){return false;}var basePath=base.pathname.replace(/\/?$/,"/");if(basePath==="/"){return true;}return target.pathname===basePath.slice(0,-1)||target.pathname.indexOf(basePath)===0;}catch(e){return false;}}function addParam(url){try{var target=new URL(url,window.location.href);target.searchParams.set(name,value);return target.toString();}catch(e){return url;}}document.querySelectorAll("a[href],area[href]").forEach(function(link){var href=link.getAttribute("href");if(href&&sameSite(href)){link.setAttribute("href",addParam(href));}});document.querySelectorAll("form").forEach(function(form){var action=form.getAttribute("action")||window.location.href;if(sameSite(action)){form.setAttribute("action",addParam(action));}});})();';
		wp_add_inline_script( $handle, $script );
	}

	public function append_access_parameter_to_same_site_links( $html ) {
		if ( ! $this->request_is_authorized || '' === $html ) {
			return $html;
		}

		try {
			if ( $this->can_use_html_tag_processor() ) {
				return $this->append_access_parameter_with_tag_processor( $html );
			}

			return $this->append_access_parameter_with_regex( $html );
		} catch ( Throwable $exception ) {
			unset( $exception );

			return $html;
		}
	}

	private function can_use_html_tag_processor() {
		return class_exists( 'WP_HTML_Tag_Processor' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'next_tag' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'get_tag' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'get_attribute' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'set_attribute' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'get_updated_html' );
	}

	private function append_access_parameter_with_tag_processor( $html ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$attrs     = [
			'a'    => 'href',
			'area' => 'href',
			'form' => 'action',
		];

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();
			if ( ! is_string( $tag ) ) {
				continue;
			}

			$tag_name = strtolower( $tag );
			if ( ! isset( $attrs[ $tag_name ] ) ) {
				continue;
			}

			$attribute = $attrs[ $tag_name ];
			$url       = $processor->get_attribute( $attribute );
			if ( ! is_string( $url ) || '' === $url || ! $this->is_same_site_url( $url ) ) {
				continue;
			}

			$processor->set_attribute( $attribute, $this->append_access_parameter_to_url( $url ) );
		}

		return $processor->get_updated_html();
	}

	private function append_access_parameter_with_regex( $html ) {
		return preg_replace_callback(
			'/\s(href|action)=(["\'])(.*?)\2/i',
			function ( array $matches ) {
				$url = html_entity_decode( $matches[3], ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( ! $this->is_same_site_url( $url ) ) {
					return $matches[0];
				}

				return sprintf( ' %s=%s%s%s', $matches[1], $matches[2], esc_url( $this->append_access_parameter_to_url( $url ) ), $matches[2] );
			},
			$html
		) ?? $html;
	}

	private function should_skip_gate() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}

		if ( is_user_logged_in() ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== strpos( $request_uri, 'wp-login.php' ) ) {
			return true;
		}

		return false;
	}

	private function request_has_valid_access_parameter( array $settings ) {
		if ( '' === $settings['param_value'] ) {
			return false;
		}

		$param_name = $settings['param_name'];

		// This is a read-only access-token check on a public URL parameter, not
		// form submission handling, so a nonce is not applicable here. The value
		// is sanitized below and compared with hash_equals().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $param_name ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provided = sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) );

		return hash_equals( $settings['param_value'], $provided );
	}

	private function is_same_site_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || 0 === strpos( $url, '#' ) || preg_match( '/^(mailto|tel|sms|javascript):/i', $url ) ) {
			return false;
		}

		$url_parts = wp_parse_url( $url );
		if ( false === $url_parts ) {
			return false;
		}

		$url_host = $url_parts['host'] ?? null;
		if ( null === $url_host ) {
			if ( ! empty( $url_parts['scheme'] ) ) {
				return false;
			}

			if ( 0 === strpos( $url, '/' ) ) {
				return $this->is_path_within_current_site( $url_parts['path'] ?? '/' );
			}

			return true;
		}

		$home_parts = $this->home_parts();
		if ( empty( $home_parts ) || empty( $home_parts['host'] ) ) {
			return false;
		}

		if ( strtolower( (string) $home_parts['host'] ) !== strtolower( (string) $url_host ) ) {
			return false;
		}

		$home_port = isset( $home_parts['port'] ) ? (int) $home_parts['port'] : null;
		$url_port  = isset( $url_parts['port'] ) ? (int) $url_parts['port'] : null;
		if ( $home_port !== $url_port ) {
			return false;
		}

		return $this->is_path_within_current_site( $url_parts['path'] ?? '/' );
	}

	private function is_path_within_current_site( $path ) {
		$home_parts = $this->home_parts();
		$site_path  = isset( $home_parts['path'] ) ? $home_parts['path'] : '';
		$site_path  = is_string( $site_path ) && '' !== $site_path ? '/' . ltrim( $site_path, '/' ) : '/';
		$site_path  = trailingslashit( $site_path );

		if ( '/' === $site_path ) {
			return true;
		}

		$path = is_string( $path ) && '' !== $path ? '/' . ltrim( $path, '/' ) : '/';

		return untrailingslashit( $site_path ) === untrailingslashit( $path ) || 0 === strpos( $path, $site_path );
	}

	private function home_parts() {
		if ( null !== $this->home_parts_cache ) {
			return $this->home_parts_cache;
		}

		remove_filter( 'home_url', [ $this, 'append_access_parameter_to_generated_urls' ], 10 );
		$home_url = home_url( '/' );
		add_filter( 'home_url', [ $this, 'append_access_parameter_to_generated_urls' ], 10, 4 );

		$parts = wp_parse_url( $home_url );
		$this->home_parts_cache = is_array( $parts ) ? $parts : [];

		return $this->home_parts_cache;
	}

	private function settings() {
		$settings = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return array_merge( $this->default_settings(), $settings );
	}

	private function default_settings() {
		return [
			'enabled'         => 0,
			'param_name'      => self::DEFAULT_PARAM_NAME,
			'param_value'     => '',
			'blocked_message' => self::DEFAULT_BLOCKED_MESSAGE,
		];
	}
}

Fronga_Plugin::instance();
