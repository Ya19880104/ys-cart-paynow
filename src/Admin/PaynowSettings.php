<?php

namespace YangSheep\YSCartPaynow\Admin;

use YangSheep\Ecommerce\Admin\YSAdminApp;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\YSEcommerce;

defined( 'ABSPATH' ) || exit;

final class PaynowSettings {
	private const NONCE_ACTION = 'ys_cart_paynow_save_settings';

	private const METHODS = [
		'ys_ec_paynow_ship_711'    => [
			'label'       => '7-ELEVEN 超商取貨',
			'description' => 'PayNow 服務代碼 01，支援電子地圖選店。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_family' => [
			'label'       => '全家超商取貨',
			'description' => 'PayNow 服務代碼 03，支援電子地圖選店。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_hilife' => [
			'label'       => '萊爾富超商取貨',
			'description' => 'PayNow 服務代碼 05，支援電子地圖選店。',
			'type'        => '超商',
		],
		'ys_ec_paynow_ship_tcat'   => [
			'label'       => '黑貓宅配',
			'description' => 'PayNow 服務代碼 36，支援常溫、冷藏、冷凍與狀態查詢。',
			'type'        => '宅配',
		],
	];

	public static function register(): void {
		add_action( 'admin_post_ys_cart_paynow_save_settings', [ __CLASS__, 'handle_save' ] );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-paynow' ), 403 );
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

		wp_safe_redirect( admin_url( 'admin.php?page=ys-ec-paynow&updated=1' ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-paynow' ), 403 );
		}

		$ec           = YSEcommerce::get_instance();
		$enabled      = '1' === (string) $ec->get_setting( 'paynow_enabled', '0' );
		$test_mode    = '1' === (string) $ec->get_setting( 'shipping_paynow_testmode', '0' );
		$merchant     = (string) $ec->get_setting( 'shipping_paynow_merchant_id', '' );
		$has_key      = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_key', '' );
		$has_iv       = '' !== (string) $ec->get_setting( 'shipping_paynow_hash_iv', '' );
		$credentials  = '' !== $merchant && $has_key && $has_iv;

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::open( 'PayNow 設定', '金物流 / PayNow' );
		}
		?>
		<div class="ysca-page-root ysca-page-root--wide ysca-stack-md">
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="ys-ec-notice ys-ec-notice-success">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					PayNow 設定已儲存。
				</div>
			<?php endif; ?>

			<?php if ( ! $enabled ) : ?>
				<div class="ys-ec-notice ysca-notice--warning">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					PayNow 尚未於供應商管理啟用。請至
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-providers' ) ); ?>">供應商管理</a>
					啟用 PayNow；物流方式的顯示、排序與運費請至
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">物流方式</a>
					設定。
				</div>
			<?php endif; ?>

			<div class="ys-ec-stats-grid ysca-stats-grid--four">
				<?php self::render_status_card( '供應商狀態', $enabled ? '已啟用' : '未啟用', '由供應商管理統一控制' ); ?>
				<?php self::render_status_card( 'API 模式', $test_mode ? 'Sandbox' : '正式環境', $test_mode ? '測試環境，不會建立正式物流單' : '會呼叫 PayNow 正式物流 API' ); ?>
				<?php self::render_status_card( '憑證狀態', $credentials ? '完整' : '未完整', $credentials ? 'Merchant ID、Hash Key、Hash IV 已設定' : '選店與建單前需補齊 API 憑證' ); ?>
				<?php self::render_status_card( '可註冊物流', count( self::METHODS ) . ' 種', '方式啟用與運費由物流方式頁管理' ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-stack-md">
				<input type="hidden" name="action" value="ys_cart_paynow_save_settings">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> API 連線設定</h3>
					<div class="inside ysca-stack-sm">
						<label class="ysca-switch-label ysca-switch-label--trailing">
							<span>
								<strong>測試模式（Sandbox）</strong>
								<p class="description">啟用後使用 PayNow 測試環境。</p>
							</span>
							<span class="ysca-switch">
								<input type="checkbox" name="shipping_paynow_testmode" value="1" <?php checked( $test_mode ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
						</label>

						<div class="ysca-field-row">
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">Merchant ID</span>
								<input class="ysca-input ysca-field--md" type="text" name="shipping_paynow_merchant_id" value="<?php echo esc_attr( $merchant ); ?>" autocomplete="off">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">Hash Key</span>
								<input class="ysca-input ysca-field--md" type="password" name="shipping_paynow_hash_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_key ? '已設定，留空不更改' : '尚未設定' ); ?>">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">Hash IV</span>
								<input class="ysca-input ysca-field--md" type="password" name="shipping_paynow_hash_iv" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_iv ? '已設定，留空不更改' : '尚未設定' ); ?>">
							</label>
						</div>
					</div>
				</div>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-businessperson" aria-hidden="true"></span> 寄件人資訊</h3>
					<div class="inside ysca-stack-sm">
						<p class="description">YS CART 建立 PayNow 物流單時會讀取以下欄位。這裡未填會造成出貨建單資料不完整。</p>
						<div class="ysca-field-row">
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">寄件人姓名</span>
								<input class="ysca-input ysca-field--md" type="text" name="shipping_paynow_sender_name" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_name', '' ) ); ?>">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">寄件人手機</span>
								<input class="ysca-input ysca-field--md" type="tel" name="shipping_paynow_sender_phone" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_phone', '' ) ); ?>" placeholder="09xxxxxxxx">
							</label>
							<label class="ys-ec-form-group">
								<span class="ysca-field__label">郵遞區號</span>
								<input class="ysca-input ysca-field--compact" type="text" name="shipping_paynow_sender_zipcode" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_zipcode', '' ) ); ?>">
							</label>
						</div>
						<label class="ys-ec-form-group">
							<span class="ysca-field__label">寄件地址</span>
							<input class="ysca-input ysca-field--lg" type="text" name="shipping_paynow_sender_address" value="<?php echo esc_attr( $ec->get_setting( 'shipping_paynow_sender_address', '' ) ); ?>" placeholder="縣市區鄉鎮 + 詳細地址">
						</label>
					</div>
				</div>

				<div class="ys-ec-card ysca-card">
					<h3><span class="dashicons dashicons-store" aria-hidden="true"></span> PayNow 物流能力</h3>
					<div class="inside ysca-stack-sm">
						<p class="description">
							以下為 PayNow 外掛註冊給 YS CART 的物流能力清單。啟用、排序、運費、免運與重量限制，請統一到
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">物流方式</a>
							管理，避免供應商頁和核心物流頁雙重狀態。
						</p>
						<div class="ysca-table-scroll">
							<table class="ys-ec-table ysca-settings-table ysca-settings-table--compact">
								<thead>
									<tr>
										<th>通道</th>
										<th>類型</th>
										<th>Method ID</th>
										<th>說明</th>
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
					<h3><span class="dashicons dashicons-rest-api" aria-hidden="true"></span> 選店與 Headless 串接</h3>
					<div class="inside ysca-stack-sm">
						<div class="ysca-field-row">
							<div class="ys-ec-form-group">
								<label>選店 URL API</label>
								<code class="ysca-code-pill ysca-code-pill--lg">POST /wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url</code>
							</div>
							<div class="ys-ec-form-group">
								<label>PayNow 門市回呼</label>
								<code class="ysca-code-pill ysca-code-pill--lg"><?php echo esc_html( rest_url( 'ys-ecommerce/v1/paynow/store-callback' ) ); ?></code>
							</div>
						</div>
						<p class="description">
							Headless 前台請使用 release 內的 <code>sdk/ys-cart-paynow-headless.js</code> 與 <code>skills/ys-cart-paynow-headless.md</code>。
						</p>
					</div>
				</div>

				<div class="ysca-inline-actions ysca-inline-actions--start">
					<button type="submit" class="ysca-btn ysca-btn--primary">
						<span class="dashicons dashicons-saved ysca-icon--sm" aria-hidden="true"></span> 儲存 PayNow 設定
					</button>
					<a class="ysca-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">
						<span class="dashicons dashicons-admin-settings ysca-icon--sm" aria-hidden="true"></span> 微調物流方式
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
}
