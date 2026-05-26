<?php

namespace YangSheep\YSCartPaynow\Admin;

use YangSheep\Ecommerce\Admin\YSAdminApp;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\YSEcommerce;
use YangSheep\YSCartPaynow\Plugin;

defined( 'ABSPATH' ) || exit;

final class PaynowSettings {
	private const NONCE_ACTION = 'ys_cart_paynow_save_settings';

	private const METHODS = [
		'ys_ec_paynow_ship_711'    => [
			'label'       => '7-ELEVEN 超商取貨',
			'description' => 'PayNow 服務代碼 01，支援 7-ELEVEN 超商取貨。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_family' => [
			'label'       => '全家超商取貨',
			'description' => 'PayNow 服務代碼 03，支援全家便利商店取貨。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_hilife' => [
			'label'       => '萊爾富超商取貨',
			'description' => 'PayNow 服務代碼 05，支援萊爾富超商取貨。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_tcat'   => [
			'label'       => '黑貓宅配',
			'description' => 'PayNow 服務代碼 36，支援黑貓宅配與溫層設定。',
			'type'        => '宅配',
		],
	];

	public static function register(): void {
		add_action( 'admin_post_ys_cart_paynow_save_settings', [ __CLASS__, 'handle_save' ] );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '權限不足。', 'ys-cart-paynow' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$ec = YSEcommerce::get_instance();
		$ec->update_setting( 'shipping_paynow_testmode', isset( $_POST['shipping_paynow_testmode'] ) ? '1' : '0' );
		$ec->update_setting( 'shipping_paynow_merchant_id', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_merchant_id'] ?? '' ) ) );
		$ec->update_setting( 'shipping_paynow_sender_name', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_sender_name'] ?? '' ) ) );
		$ec->update_setting( 'shipping_paynow_sender_phone', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_sender_phone'] ?? '' ) ) );
		$ec->update_setting( 'shipping_paynow_sender_zipcode', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_sender_zipcode'] ?? '' ) ) );
		$ec->update_setting( 'shipping_paynow_sender_address', sanitize_text_field( wp_unslash( $_POST['shipping_paynow_sender_address'] ?? '' ) ) );

		$hash_key = trim( (string) wp_unslash( $_POST['shipping_paynow_hash_key'] ?? '' ) );
		if ( '' !== $hash_key ) {
			$ec->update_setting( 'shipping_paynow_hash_key', YSCrypto::encrypt_for_storage( $hash_key ) );
		}

		$hash_iv = trim( (string) wp_unslash( $_POST['shipping_paynow_hash_iv'] ?? '' ) );
		if ( '' !== $hash_iv ) {
			$ec->update_setting( 'shipping_paynow_hash_iv', YSCrypto::encrypt_for_storage( $hash_iv ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ys-provider-paynow&updated=1' ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '權限不足。', 'ys-cart-paynow' ), 403 );
		}

		$ec          = YSEcommerce::get_instance();
		$enabled     = self::is_provider_enabled();
		$test_mode   = '1' === (string) $ec->get_setting( 'shipping_paynow_testmode', '0' );
		$merchant    = (string) $ec->get_setting( 'shipping_paynow_merchant_id', '' );
		$has_key     = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_key', '' );
		$has_iv      = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_iv', '' );
		$credentials = '' !== $merchant && $has_key && $has_iv;

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::open( 'PayNow 物流設定', '金物流 / PayNow' );
		}
		?>
		<div class="ysca-page-root ysca-page-root--wide ysca-stack-md">
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="ys-ec-notice ys-ec-notice-success">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'PayNow 設定已儲存。', 'ys-cart-paynow' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $enabled ) : ?>
				<div class="ys-ec-notice ysca-notice--warning">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<?php esc_html_e( 'PayNow 供應商尚未啟用。此頁只保存 API 憑證與寄件資訊；請先在供應商管理啟用 PayNow，再到物流設定啟用需要的物流方法。', 'ys-cart-paynow' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-providers' ) ); ?>"><?php esc_html_e( '前往供應商管理', 'ys-cart-paynow' ); ?></a>
				</div>
			<?php endif; ?>

			<div class="ys-ec-stats-grid ysca-stats-grid--four">
				<?php self::render_status_card( '供應商狀態', $enabled ? '已啟用' : '未啟用', '由 YS CART 供應商管理控制' ); ?>
				<?php self::render_status_card( 'API 模式', $test_mode ? 'Sandbox' : '正式環境', $test_mode ? '目前送到 PayNow 測試環境' : '目前送到 PayNow 正式 API' ); ?>
				<?php self::render_status_card( 'API 憑證', $credentials ? '已設定' : '未完成', $credentials ? '商店代號、加密金鑰、加密向量已保存' : '請填入 PayNow API 憑證' ); ?>
				<?php self::render_status_card( '物流方法', count( self::METHODS ) . ' 個', '方法啟用請到物流設定管理' ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-stack-md">
				<input type="hidden" name="action" value="ys_cart_paynow_save_settings">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> <?php esc_html_e( 'API 基本設定', 'ys-cart-paynow' ); ?></h3>
					<div class="inside ysca-stack-sm">
						<label class="ysca-switch-label ysca-switch-label--trailing">
							<span>
								<strong><?php esc_html_e( '測試模式（Sandbox）', 'ys-cart-paynow' ); ?></strong>
								<p class="description"><?php esc_html_e( '啟用後會串接 PayNow 測試環境。', 'ys-cart-paynow' ); ?></p>
							</span>
							<span class="ysca-switch">
								<input type="checkbox" name="shipping_paynow_testmode" value="1" <?php checked( $test_mode ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
						</label>

						<div class="ysca-field-row">
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">商店代號</span>
								<input class="ysca-input ysca-field--md" type="text" name="shipping_paynow_merchant_id" value="<?php echo esc_attr( $merchant ); ?>" autocomplete="off">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">加密金鑰</span>
								<input class="ysca-input ysca-field--md" type="password" name="shipping_paynow_hash_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_key ? '已設定，留空不變' : '請輸入加密金鑰' ); ?>">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">加密向量</span>
								<input class="ysca-input ysca-field--md" type="password" name="shipping_paynow_hash_iv" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_iv ? '已設定，留空不變' : '請輸入加密向量' ); ?>">
							</label>
						</div>
					</div>
				</div>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-businessperson" aria-hidden="true"></span> <?php esc_html_e( '寄件人資料', 'ys-cart-paynow' ); ?></h3>
					<div class="inside ysca-stack-sm">
						<div class="ysca-field-row">
							<label class="ys-ec-form-group">
								<span class="ysca-field__label"><?php esc_html_e( '寄件人姓名', 'ys-cart-paynow' ); ?></span>
								<input class="ysca-input ysca-field--md" type="text" name="shipping_paynow_sender_name" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_name', '' ) ); ?>">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label"><?php esc_html_e( '寄件人電話', 'ys-cart-paynow' ); ?></span>
								<input class="ysca-input ysca-field--md" type="tel" name="shipping_paynow_sender_phone" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_phone', '' ) ); ?>" placeholder="09xxxxxxxx">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label"><?php esc_html_e( '郵遞區號', 'ys-cart-paynow' ); ?></span>
								<input class="ysca-input ysca-field--compact" type="text" name="shipping_paynow_sender_zipcode" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_zipcode', '' ) ); ?>">
							</label>
						</div>
						<label class="ys-ec-form-group">
							<span class="ysca-field__label"><?php esc_html_e( '寄件地址', 'ys-cart-paynow' ); ?></span>
							<input class="ysca-input ysca-field--lg" type="text" name="shipping_paynow_sender_address" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_address', '' ) ); ?>">
						</label>
					</div>
				</div>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-store" aria-hidden="true"></span> <?php esc_html_e( 'PayNow 物流方法', 'ys-cart-paynow' ); ?></h3>
					<div class="inside ysca-stack-sm">
						<p class="description">
							<?php esc_html_e( '這裡只顯示 PayNow 可用的物流方法。啟用、排序、運費、免運門檻等營運設定統一到 YS CART 物流設定管理。', 'ys-cart-paynow' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>"><?php esc_html_e( '前往物流設定', 'ys-cart-paynow' ); ?></a>
						</p>
						<div class="ysca-table-scroll">
							<table class="ys-ec-table ysca-settings-table ysca-settings-table--compact">
								<thead>
									<tr>
										<th><?php esc_html_e( '方法', 'ys-cart-paynow' ); ?></th>
										<th><?php esc_html_e( '類型', 'ys-cart-paynow' ); ?></th>
										<th>方法代碼</th>
										<th><?php esc_html_e( '說明', 'ys-cart-paynow' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( self::METHODS as $method_id => $method ) : ?>
										<tr>
											<td><?php echo esc_html( $method['label'] ); ?></td>
											<td><span class="ysca-badge ysca-badge--neutral"><?php echo esc_html( $method['type'] ); ?></span></td>
											<td><code class="ysca-code-pill"><?php echo esc_html( $method_id ); ?></code></td>
											<td><?php echo esc_html( $method['description'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-rest-api" aria-hidden="true"></span> <?php esc_html_e( 'Headless 串接', 'ys-cart-paynow' ); ?></h3>
					<div class="inside ysca-stack-sm">
						<div class="ysca-field-row">
							<div class="ys-ec-form-group">
								<label><?php esc_html_e( '選店 URL API', 'ys-cart-paynow' ); ?></label>
								<code class="ysca-code-pill ysca-code-pill--lg">POST /wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url</code>
							</div>
							<div class="ys-ec-form-group">
								<label><?php esc_html_e( 'PayNow 選店回呼', 'ys-cart-paynow' ); ?></label>
								<code class="ysca-code-pill ysca-code-pill--lg"><?php echo esc_html( rest_url( 'ys-ecommerce/v1/paynow/store-callback' ) ); ?></code>
							</div>
						</div>
						<p class="description">
							<?php esc_html_e( 'Headless 專案請參考 release 內的 sdk/ys-cart-paynow-headless.js 與 skills/ys-cart-paynow-headless.md。', 'ys-cart-paynow' ); ?>
						</p>
					</div>
				</div>

				<div class="ysca-inline-actions ysca-inline-actions--start">
					<button type="submit" class="ysca-btn ysca-btn--primary">
						<span class="dashicons dashicons-saved ysca-icon--sm" aria-hidden="true"></span> <?php esc_html_e( '儲存 PayNow 設定', 'ys-cart-paynow' ); ?>
					</button>
					<a class="ysca-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">
						<span class="dashicons dashicons-admin-settings ysca-icon--sm" aria-hidden="true"></span> <?php esc_html_e( '前往物流設定', 'ys-cart-paynow' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::close();
		}
	}

	private static function render_status_card( string $label, string $value, string $description ): void {
		?>
		<div class="ys-ec-stat-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong class="ys-ec-stat-value"><?php echo esc_html( $value ); ?></strong>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	private static function is_provider_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_provider_enabled(
				'ys_paynow',
				Plugin::manifest()
			);
		}

		return '1' === (string) YSEcommerce::get_instance()->get_setting( 'paynow_enabled', '0' );
	}
}
