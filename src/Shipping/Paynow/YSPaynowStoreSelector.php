<?php
/**
 * PayNow 超商門市選擇器
 *
 * 負責產生 PayNow 電子地圖 URL、處理選店回呼（含 store_id、store_name、store_address）。
 *
 * @package YangSheep\YSCartPaynow\Shipping\Paynow
 */

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;

class YSPaynowStoreSelector {

    /**
     * PayNow 電子地圖端點
     */
    private const MAP_URL      = 'https://www.paynow.com.tw/logistics/map';
    private const MAP_URL_TEST = 'https://test.paynow.com.tw/logistics/map';

    /**
     * 超商類型對照（PayNow 使用服務代碼）
     */
    private const CVS_TYPES = [
        'ys_ec_paynow_ship_711'    => '01',
        'ys_ec_paynow_ship_family' => '03',
        'ys_ec_paynow_ship_hilife' => '05',
    ];

    /**
     * AJAX Action 名稱
     */
    private const AJAX_MAP_ACTION      = 'ys_ec_paynow_map';
    private const AJAX_CALLBACK_ACTION = 'ys_ec_paynow_store_callback';

    /**
     * 初始化 Hook
     *
     * v2.35.1 hotfix（CODEX P2-2）：原 init() 雖然從未被任何 caller 呼叫（dormant code），
     * 但 source 內的 wp_ajax / wp_ajax_nopriv 註冊若未來誤啟用會把 frontend admin-ajax 帶回。
     * 統一改為 no-op + 明確說明 REST 接管路徑，避免未來爆雷。
     *
     * REST 接管：
     *   POST /wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url    → 取得選店 URL
     *   POST /wp-json/ys-ecommerce/v1/paynow/store-callback             → 回呼處理（v2.35.3）
     * 由 YSInteractionController + YSPayuniNotifyHandler 註冊（呼叫本 class 的 pure helpers）。
     */
    public static function init(): void {
        // intentionally empty — 所有 hooks 已下線（同時 init() 本來就沒人呼叫）
    }

    /**
     * 共用：組建電子地圖 URL（PayNow GET URL 風格）
     *
     * v2.35.3：新增本 helper，修復 CODEX Round 6 P1-1
     * （/stores/paynow/map-url REST 呼叫不存在的 method → 500 fatal）
     *
     * @param string $shipping_id 物流方式 ID
     * @return array{map_url:string,temp_id:string}|false 表單資料或 false
     */
    public static function build_map_form_data( string $shipping_id ) {
        if ( empty( $shipping_id ) || ! isset( self::CVS_TYPES[ $shipping_id ] ) ) {
            return false;
        }

        $service_code = self::CVS_TYPES[ $shipping_id ];
        $temp_id      = wp_generate_uuid4();

        set_transient( 'ys_ec_paynow_map_' . $temp_id, [
            'shipping_id'  => $shipping_id,
            'service_code' => $service_code,
            'user_id'      => get_current_user_id(),
            'created_at'   => current_time( 'timestamp' ),
        ], 30 * MINUTE_IN_SECONDS );

        // v2.35.3：callback 改走 REST endpoint，與 PayUni 對齊
        $callback_url = rest_url( 'ys-ecommerce/v1/paynow/store-callback' );

        // 讀取 PayNow 設定
        global $wpdb;
        $table = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';

        $merchant_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_merchant_id'
        ) );

        $hash_key_encrypted = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_hash_key'
        ) );

        $hash_iv_encrypted = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_hash_iv'
        ) );

        $testmode = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_testmode'
        ) );

        if ( empty( $merchant_id ) || empty( $hash_key_encrypted ) || empty( $hash_iv_encrypted ) ) {
            return false;
        }

        $hash_key = YSCrypto::decrypt_from_storage( $hash_key_encrypted );
        $hash_iv  = YSCrypto::decrypt_from_storage( $hash_iv_encrypted );

        // 組合參數
        $params = [
            'MerchantID'     => $merchant_id,
            'ServiceCode'    => $service_code,
            'TempId'         => $temp_id,
            'ServerReplyURL' => $callback_url,
        ];

        $raw_data     = http_build_query( $params );
        $encrypt_data = YSCrypto::encrypt_aes_gcm( $raw_data, $hash_key, $hash_iv );
        $hash_data    = YSCrypto::generate_hash( $raw_data, $hash_key, $hash_iv );

        $base_url = ( '1' === $testmode ) ? self::MAP_URL_TEST : self::MAP_URL;

        $map_url = $base_url . '?' . http_build_query( [
            'MerchantID'  => $merchant_id,
            'Version'     => '2.0',
            'EncryptData' => $encrypt_data,
            'HashData'    => $hash_data,
        ] );

        return [
            'map_url' => $map_url,
            'temp_id' => $temp_id,
        ];
    }

    /**
     * 處理 PayNow 門市選擇回呼
     *
     * v2.35.26: REST-only handler; request must be a WP_REST_Request.
     * 由 YSPayuniNotifyHandler::register_routes() 註冊
     * 路徑：POST /wp-json/ys-ecommerce/v1/paynow/store-callback
     *
     * @param \WP_REST_Request $request REST request.
     */
    public static function handle_store_callback( \WP_REST_Request $request ): void {
        $params = $request->get_params();

        $encrypt_data = sanitize_text_field( wp_unslash( $params['EncryptData'] ?? '' ) );
        $hash_data    = sanitize_text_field( wp_unslash( $params['HashData'] ?? '' ) );

        if ( empty( $encrypt_data ) || empty( $hash_data ) ) {
            YSLogger::error( 'paynow_store', '門市回呼缺少加密資料' );
            wp_die( '參數錯誤', '錯誤', [ 'response' => 400 ] );
        }

        // 讀取 PayNow 金鑰
        global $wpdb;
        $table = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';

        $hash_key_encrypted = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_hash_key'
        ) );

        $hash_iv_encrypted = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            'shipping_paynow_hash_iv'
        ) );

        $hash_key = YSCrypto::decrypt_from_storage( $hash_key_encrypted );
        $hash_iv  = YSCrypto::decrypt_from_storage( $hash_iv_encrypted );

        // 解密回應
        $raw_data = YSCrypto::decrypt_aes_gcm( $encrypt_data, $hash_key, $hash_iv );

        if ( empty( $raw_data ) ) {
            YSLogger::error( 'paynow_store', '門市回呼解密失敗' );
            wp_die( '解密失敗', '錯誤', [ 'response' => 400 ] );
        }

        // 驗證 Hash
        $expected_hash = YSCrypto::generate_hash( $raw_data, $hash_key, $hash_iv );
        if ( ! hash_equals( $expected_hash, $hash_data ) ) {
            YSLogger::error( 'paynow_store', '門市回呼 Hash 驗證失敗' );
            wp_die( 'Hash 驗證失敗', '錯誤', [ 'response' => 400 ] );
        }

        parse_str( $raw_data, $store_data );

        $temp_id = sanitize_text_field( $store_data['TempId'] ?? '' );

        // 驗證 temp_id 有效性
        $map_data = get_transient( 'ys_ec_paynow_map_' . $temp_id );
        if ( ! $map_data ) {
            YSLogger::error( 'paynow_store', '門市回呼 TempId 無效或已過期' );
            wp_die( 'TempId 無效', '錯誤', [ 'response' => 400 ] );
        }

        // 儲存門市資訊至 transient
        $store_info = [
            'store_id'      => sanitize_text_field( $store_data['StoreID'] ?? '' ),
            'store_name'    => sanitize_text_field( $store_data['StoreName'] ?? '' ),
            'store_address' => sanitize_text_field( $store_data['StoreAddr'] ?? '' ),
            'store_phone'   => sanitize_text_field( $store_data['StorePhone'] ?? '' ),
            'service_code'  => $map_data['service_code'],
            'shipping_id'   => $map_data['shipping_id'],
            'selected_at'   => current_time( 'mysql' ),
        ];

        set_transient( 'ys_ec_paynow_store_' . $temp_id, $store_info, 30 * MINUTE_IN_SECONDS );
        delete_transient( 'ys_ec_paynow_map_' . $temp_id );

        YSLogger::info( 'paynow_store', '門市選擇成功', $store_info );

        // 輸出關閉彈窗的 HTML
        self::render_callback_page( $store_info );
    }

    /**
     * 輸出回呼頁面 HTML
     *
     * @param array $store_info 門市資訊
     */
    private static function render_callback_page( array $store_info ): void {
        $json_data = wp_json_encode( $store_info );
        ?>
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>門市選擇完成</title></head>
        <body>
        <p>門市選擇完成，正在關閉視窗...</p>
        <script>
        (function() {
            var storeData = <?php echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON 已經過 wp_json_encode 處理 ?>;
            if (window.opener) {
                window.opener.postMessage({
                    type: 'ys_ec_store_selected',
                    provider: 'paynow',
                    data: storeData
                }, '<?php echo esc_url( home_url() ); ?>');
                window.close();
            }
        })();
        </script>
        </body>
        </html>
        <?php
        exit;
    }
}
