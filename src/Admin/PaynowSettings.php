<?php

namespace YangSheep\YSCartPaynow\Admin;

use YangSheep\Ecommerce\Admin\YSAdminApp;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\YSEcommerce;

defined( 'ABSPATH' ) || exit;

final class PaynowSettings {
	private const NONCE_ACTION = 'ys_cart_paynow_save_settings';

	public static function register(): void {
		add_action( 'admin_post_ys_cart_paynow_save_settings', [ __CLASS__, 'handle_save' ] );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-paynow' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$ec = YSEcommerce::get_instance();

		$ec->update_setting( 'paynow_enabled', isset( $_POST['paynow_enabled'] ) ? '1' : '0' );
		$ec->update_setting( 'shipping_paynow_testmode', isset( $_POST['shipping_paynow_testmode'] ) ? '1' : '0' );
		$ec->update_setting( 'shipping_paynow_merchant_id', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_merchant_id'] ?? '' ) ) );

		$hash_key = trim( (string) wp_unslash( $_POST['shipping_paynow_hash_key'] ?? '' ) );
		if ( '' !== $hash_key ) {
			$ec->update_setting( 'shipping_paynow_hash_key', YSCrypto::encrypt_for_storage( $hash_key ) );
		}

		$hash_iv = trim( (string) wp_unslash( $_POST['shipping_paynow_hash_iv'] ?? '' ) );
		if ( '' !== $hash_iv ) {
			$ec->update_setting( 'shipping_paynow_hash_iv', YSCrypto::encrypt_for_storage( $hash_iv ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ys-ec-paynow&updated=1' ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-paynow' ), 403 );
		}

		$ec          = YSEcommerce::get_instance();
		$enabled    = '1' === (string) $ec->get_setting( 'paynow_enabled', '0' );
		$test_mode  = '1' === (string) $ec->get_setting( 'shipping_paynow_testmode', '0' );
		$merchant   = (string) $ec->get_setting( 'shipping_paynow_merchant_id', '' );
		$has_key    = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_key', '' );
		$has_iv     = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_iv', '' );

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::open( 'PayNow 設定', '金物流 / PayNow' );
		}
		?>
		<div class="ysca-card">
			<div class="ysca-card__body">
				<?php if ( isset( $_GET['updated'] ) ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'PayNow settings saved.', 'ys-cart-paynow' ); ?></p></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-form">
					<input type="hidden" name="action" value="ys_cart_paynow_save_settings">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>

					<div class="ysca-form-grid">
						<label class="ysca-field">
							<span class="ysca-field__label"><?php esc_html_e( 'Enable PayNow logistics', 'ys-cart-paynow' ); ?></span>
							<input type="checkbox" name="paynow_enabled" value="1" <?php checked( $enabled ); ?>>
						</label>

						<label class="ysca-field">
							<span class="ysca-field__label"><?php esc_html_e( 'Sandbox mode', 'ys-cart-paynow' ); ?></span>
							<input type="checkbox" name="shipping_paynow_testmode" value="1" <?php checked( $test_mode ); ?>>
						</label>

						<label class="ysca-field">
							<span class="ysca-field__label"><?php esc_html_e( 'Merchant ID', 'ys-cart-paynow' ); ?></span>
							<input class="regular-text" type="text" name="shipping_paynow_merchant_id" value="<?php echo esc_attr( $merchant ); ?>" autocomplete="off">
						</label>

						<label class="ysca-field">
							<span class="ysca-field__label"><?php esc_html_e( 'Hash Key', 'ys-cart-paynow' ); ?></span>
							<input class="regular-text" type="password" name="shipping_paynow_hash_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_key ? __( 'Saved. Leave blank to keep current value.', 'ys-cart-paynow' ) : '' ); ?>">
						</label>

						<label class="ysca-field">
							<span class="ysca-field__label"><?php esc_html_e( 'Hash IV', 'ys-cart-paynow' ); ?></span>
							<input class="regular-text" type="password" name="shipping_paynow_hash_iv" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_iv ? __( 'Saved. Leave blank to keep current value.', 'ys-cart-paynow' ) : '' ); ?>">
						</label>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save PayNow settings', 'ys-cart-paynow' ); ?></button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>"><?php esc_html_e( 'Configure shipping methods', 'ys-cart-paynow' ); ?></a>
					</p>
				</form>
			</div>
		</div>
		<?php
		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::close();
		}
	}
}
