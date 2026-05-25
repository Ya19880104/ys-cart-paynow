<?php
/**
 * PayNow 物流抽象基底類別
 *
 * 實作 YSShippingInterface，提供 PayNow 物流 API 共用通訊邏輯、
 * 共用設定（merchant_id、hash_key、hash_iv），以及子類別必須實作的抽象方法。
 *
 * @package YangSheep\YSCartPaynow\Shipping\Paynow
 */

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Shipping\YSShippingInterface;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;

abstract class YSPaynowShipping implements YSShippingInterface {

    /**
     * 物流方式唯一 ID（子類別定義）
     *
     * @var string
     */
    protected string $id = '';

    /**
     * 物流方式標題（子類別定義）
     *
     * @var string
     */
    protected string $title = '';

    /**
     * 物流類型：cvs 或 home
     *
     * @var string
     */
    protected string $type = 'cvs';

    /**
     * PayNow 服務代碼（子類別定義）
     *
     * @var string
     */
    protected string $service_code = '';

    /**
     * PayNow API 端點
     */
    private const API_BASE_URL      = 'https://www.paynow.com.tw/api/logistics';
    private const API_BASE_URL_TEST = 'https://test.paynow.com.tw/api/logistics';

    // ─── YSShippingInterface 實作 ───

    /**
     * 取得物流方式唯一識別碼
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * 取得物流方式顯示名稱
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * 取得物流供應商名稱
     */
    public function get_provider(): string {
        return 'paynow';
    }

    /**
     * 取得物流類型
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * 取得服務代碼
     */
    public function get_service_code(): string {
        return $this->service_code;
    }

    /**
     * 物流方式是否已啟用
     */
    public function is_enabled(): bool {
        return '1' === $this->get_option( 'enabled', '0' );
    }

    /**
     * 判斷物流方式在目前訂單條件下是否可用
     *
     * @param array $order_data 訂單資料
     */
    public function is_available( array $order_data ): bool {
        if ( ! $this->is_enabled() ) {
            return false;
        }

        // 檢查 API 金鑰
        if ( empty( $this->get_merchant_id() ) || empty( $this->get_hash_key() ) || empty( $this->get_hash_iv() ) ) {
            return false;
        }

        // 檢查金額上限
        $max_amount = (float) $this->get_option( 'max_amount', 0 );
        if ( $max_amount > 0 ) {
            $subtotal = $this->calculate_subtotal( $order_data['cart_items'] ?? [] );
            if ( $subtotal > $max_amount ) {
                return false;
            }
        }

        // 檢查重量限制
        $max_weight = (float) $this->get_option( 'max_weight', 0 );
        if ( $max_weight > 0 ) {
            $total_weight = $this->calculate_total_weight( $order_data['cart_items'] ?? [] );
            if ( $total_weight > $max_weight ) {
                return false;
            }
        }

        return true;
    }

    /**
     * 取得免運門檻金額
     */
    public function get_free_threshold(): float {
        return (float) $this->get_option( 'free_threshold', 0 );
    }

    /**
     * 取得後台設定欄位定義
     */
    public function get_settings_fields(): array {
        return [
            'enabled' => [
                'type'    => 'checkbox',
                'label'   => '啟用',
                'default' => '0',
            ],
            'base_fee' => [
                'type'    => 'number',
                'label'   => '基本運費',
                'default' => '60',
                'desc'    => '基本運費金額（TWD）',
            ],
            'free_threshold' => [
                'type'    => 'number',
                'label'   => '免運門檻',
                'default' => '0',
                'desc'    => '訂單金額達此值免運（0 = 不免運）',
            ],
            'max_amount' => [
                'type'    => 'number',
                'label'   => '最大金額',
                'default' => '0',
                'desc'    => '0 表示不限制',
            ],
            'max_weight' => [
                'type'    => 'number',
                'label'   => '最大重量（kg）',
                'default' => '0',
                'desc'    => '0 表示不限制',
            ],
        ];
    }

    /**
     * 是否支援貨到付款（COD）
     *
     * CVS 超商取貨預設支援貨到付款，宅配則由子類別覆寫。
     */
    public function supports_cod(): bool {
        return 'cvs' === $this->type;
    }

    /**
     * PayNow logistics currently supports Taiwan addresses only.
     *
     * @return array<int,string>
     */
    public function get_supported_countries(): array {
        return [ 'TW' ];
    }

    // ─── PayNow 共用方法 ───

    /**
     * 取得商店代號
     */
    protected function get_merchant_id(): string {
        return $this->get_paynow_option( 'merchant_id', '' );
    }

    /**
     * 取得 Hash Key
     */
    protected function get_hash_key(): string {
        $encrypted = $this->get_paynow_option( 'hash_key', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        return YSCrypto::decrypt_from_storage( $encrypted );
    }

    /**
     * 取得 Hash IV
     */
    protected function get_hash_iv(): string {
        $encrypted = $this->get_paynow_option( 'hash_iv', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        return YSCrypto::decrypt_from_storage( $encrypted );
    }

    /**
     * 是否為測試模式
     */
    protected function is_testmode(): bool {
        return '1' === $this->get_paynow_option( 'testmode', '0' );
    }

    /**
     * 取得 API 端點基底 URL
     */
    protected function get_api_base_url(): string {
        return $this->is_testmode() ? self::API_BASE_URL_TEST : self::API_BASE_URL;
    }

    /**
     * 加密請求參數
     *
     * PayNow 使用 AES + SHA256 Hash 驗證。
     *
     * @param array $params 請求參數
     * @return array 加密後的參數
     */
    protected function encrypt_request( array $params ): array {
        $key = $this->get_hash_key();
        $iv  = $this->get_hash_iv();

        if ( empty( $key ) || empty( $iv ) ) {
            YSLogger::error( 'paynow_shipping', '加密失敗：缺少 Hash Key 或 Hash IV' );
            return [];
        }

        $raw_data     = http_build_query( $params );
        $encrypt_info = YSCrypto::encrypt_aes_gcm( $raw_data, $key, $iv );
        $hash_info    = YSCrypto::generate_hash( $raw_data, $key, $iv );

        return [
            'MerchantID'  => $this->get_merchant_id(),
            'Version'     => '2.0',
            'EncryptData' => $encrypt_info,
            'HashData'    => $hash_info,
        ];
    }

    /**
     * 解密回應資料
     *
     * @param string $encrypt_data 加密資料
     * @param string $hash_data    驗證碼
     * @return array|null 解密後的參數陣列，驗證失敗回傳 null
     */
    protected function decrypt_response( string $encrypt_data, string $hash_data ): ?array {
        $key = $this->get_hash_key();
        $iv  = $this->get_hash_iv();

        if ( empty( $key ) || empty( $iv ) ) {
            return null;
        }

        $raw_data = YSCrypto::decrypt_aes_gcm( $encrypt_data, $key, $iv );

        if ( empty( $raw_data ) ) {
            YSLogger::error( 'paynow_shipping', '解密失敗：EncryptData 解密結果為空' );
            return null;
        }

        // 驗證 Hash
        $expected_hash = YSCrypto::generate_hash( $raw_data, $key, $iv );
        if ( ! hash_equals( $expected_hash, $hash_data ) ) {
            YSLogger::error( 'paynow_shipping', 'Hash 驗證失敗' );
            return null;
        }

        parse_str( $raw_data, $result );
        return $result;
    }

    // ─── 設定讀取輔助方法 ───

    /**
     * 取得此物流方式的選項值
     *
     * @param string $key     選項鍵名
     * @param mixed  $default 預設值
     * @return mixed 選項值
     */
    protected function get_option( string $key, $default = '' ) {
        global $wpdb;
        $table      = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';
        $option_key = 'shipping_' . $this->id . '_' . $key;

        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            $option_key
        ) );

        return $value !== null ? $value : $default;
    }

    /**
     * 取得 PayNow 供應商共用選項
     *
     * @param string $key     選項鍵名
     * @param mixed  $default 預設值
     * @return mixed 選項值
     */
    protected function get_paynow_option( string $key, $default = '' ) {
        global $wpdb;
        $table      = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';
        $option_key = 'shipping_paynow_' . $key;

        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            $option_key
        ) );

        return $value !== null ? $value : $default;
    }

    /**
     * 計算購物車總重量
     *
     * @param array $cart_items 購物車項目
     * @return float 總重量（kg）
     */
    protected function calculate_total_weight( array $cart_items ): float {
        $total = 0.0;
        foreach ( $cart_items as $item ) {
            $total += (float) ( $item['weight'] ?? 0 ) * (int) ( $item['quantity'] ?? 1 );
        }
        return $total;
    }

    /**
     * 計算購物車小計金額
     *
     * @param array $cart_items 購物車項目
     * @return float 小計金額
     */
    protected function calculate_subtotal( array $cart_items ): float {
        $subtotal = 0.0;
        foreach ( $cart_items as $item ) {
            $subtotal += (float) ( $item['price'] ?? 0 ) * (int) ( $item['quantity'] ?? 1 );
        }
        return $subtotal;
    }
}
