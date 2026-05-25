<?php
/**
 * PayNow 黑貓宅配物流
 *
 * 服務代碼 36，繼承 YSPaynowShipping。
 * 支援三種溫層：常溫（normal）、冷藏（chilled）、冷凍（frozen）。
 *
 * @package YangSheep\YSCartPaynow\Shipping\Paynow
 */

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

class YSPaynowShippingTcat extends YSPaynowShipping {

    /**
     * 物流方式唯一 ID
     *
     * @var string
     */
    protected string $id = 'ys_ec_paynow_ship_tcat';

    /**
     * 物流方式標題
     *
     * @var string
     */
    protected string $title = 'PayNow 黑貓宅配';

    /**
     * 物流類型
     *
     * @var string
     */
    protected string $type = 'home';

    /**
     * PayNow 服務代碼
     *
     * @var string
     */
    protected string $service_code = '36';

    /**
     * 溫層類型定義
     */
    public const TEMP_NORMAL  = 'normal';
    public const TEMP_CHILLED = 'chilled';
    public const TEMP_FROZEN  = 'frozen';

    /**
     * 溫層標籤對照
     */
    private const TEMP_LABELS = [
        self::TEMP_NORMAL  => '常溫',
        self::TEMP_CHILLED => '冷藏',
        self::TEMP_FROZEN  => '冷凍',
    ];

    /**
     * PayNow 溫層代碼對照
     */
    private const TEMP_CODES = [
        self::TEMP_NORMAL  => '0001',
        self::TEMP_CHILLED => '0002',
        self::TEMP_FROZEN  => '0003',
    ];

    /**
     * 計算運費
     *
     * 宅配運費 = 基本運費（依溫層不同）+ 重量加價 + 離島加價
     *
     * @param array $cart_items 購物車項目
     * @param array $address    收件地址資料
     * @return float 運費金額
     */
    public function calculate_cost( array $cart_items, array $address = [] ): float {
        $subtotal = $this->calculate_subtotal( $cart_items );

        // 免運門檻檢查
        $free_threshold = $this->get_free_threshold();
        if ( $free_threshold > 0 && $subtotal >= $free_threshold ) {
            // 達到免運門檻，但離島加價可能仍需收取
            return $this->get_island_surcharge( $address );
        }

        // 取得當前溫層
        $temperature = $this->get_current_temperature();

        // 基本運費（依溫層）
        $base_fee = (float) $this->get_option( 'base_fee_' . $temperature, $this->get_default_base_fee( $temperature ) );

        // 重量加價
        $weight_surcharge = $this->calculate_weight_surcharge( $cart_items );

        // 離島加價
        $island_surcharge = $this->get_island_surcharge( $address );

        return round( $base_fee + $weight_surcharge + $island_surcharge, 0 );
    }

    /**
     * 是否支援超商選店（宅配不支援）
     */
    public function supports_cvs_selection(): bool {
        return false;
    }

    /**
     * 黑貓宅配支援貨到付款
     */
    public function supports_cod(): bool {
        return true;
    }

    /**
     * 取得目前啟用的溫層
     *
     * @return string 溫層代碼
     */
    public function get_current_temperature(): string {
        $temp = $this->get_option( 'temperature', self::TEMP_NORMAL );
        return array_key_exists( $temp, self::TEMP_LABELS ) ? $temp : self::TEMP_NORMAL;
    }

    /**
     * 取得 PayNow 溫層代碼
     *
     * @param string $temperature 溫層
     * @return string PayNow 溫層代碼
     */
    public function get_temperature_code( string $temperature = '' ): string {
        if ( empty( $temperature ) ) {
            $temperature = $this->get_current_temperature();
        }
        return self::TEMP_CODES[ $temperature ] ?? self::TEMP_CODES[ self::TEMP_NORMAL ];
    }

    /**
     * 取得所有溫層標籤
     *
     * @return array 溫層代碼 => 中文標籤
     */
    public static function get_temperature_labels(): array {
        return self::TEMP_LABELS;
    }

    /**
     * 取得後台設定欄位定義
     */
    public function get_settings_fields(): array {
        $fields = parent::get_settings_fields();

        // 替換基本運費為三種溫層的運費
        unset( $fields['base_fee'] );

        $fields['temperature'] = [
            'type'    => 'select',
            'label'   => '預設溫層',
            'default' => self::TEMP_NORMAL,
            'options' => self::TEMP_LABELS,
            'desc'    => '預設出貨溫層',
        ];

        $fields['base_fee_normal'] = [
            'type'    => 'number',
            'label'   => '常溫運費',
            'default' => '100',
            'desc'    => '常溫宅配基本運費',
        ];

        $fields['base_fee_chilled'] = [
            'type'    => 'number',
            'label'   => '冷藏運費',
            'default' => '150',
            'desc'    => '冷藏宅配基本運費',
        ];

        $fields['base_fee_frozen'] = [
            'type'    => 'number',
            'label'   => '冷凍運費',
            'default' => '180',
            'desc'    => '冷凍宅配基本運費',
        ];

        $fields['weight_surcharge_threshold'] = [
            'type'    => 'number',
            'label'   => '重量加價門檻（kg）',
            'default' => '5',
            'desc'    => '超過此重量開始加收運費',
        ];

        $fields['weight_surcharge_per_kg'] = [
            'type'    => 'number',
            'label'   => '每公斤加價',
            'default' => '15',
            'desc'    => '超過門檻重量後，每公斤加收金額',
        ];

        $fields['island_surcharge'] = [
            'type'    => 'number',
            'label'   => '離島加收運費',
            'default' => '0',
            'desc'    => '離島地區額外收取金額',
        ];

        return $fields;
    }

    // ─── 私有輔助方法 ───

    /**
     * 取得溫層預設基本運費
     *
     * @param string $temperature 溫層
     * @return int 預設運費
     */
    private function get_default_base_fee( string $temperature ): int {
        return match ( $temperature ) {
            self::TEMP_CHILLED => 150,
            self::TEMP_FROZEN  => 180,
            default            => 100,
        };
    }

    /**
     * 計算重量加價
     *
     * @param array $cart_items 購物車項目
     * @return float 重量加價金額
     */
    private function calculate_weight_surcharge( array $cart_items ): float {
        $total_weight = $this->calculate_total_weight( $cart_items );
        $threshold    = (float) $this->get_option( 'weight_surcharge_threshold', 5 );
        $per_kg       = (float) $this->get_option( 'weight_surcharge_per_kg', 15 );

        if ( $total_weight <= $threshold || $per_kg <= 0 ) {
            return 0.0;
        }

        return ceil( $total_weight - $threshold ) * $per_kg;
    }

    /**
     * 取得離島加價金額
     *
     * @param array $address 地址資料
     * @return float 離島加價金額
     */
    private function get_island_surcharge( array $address ): float {
        if ( empty( $address ) ) {
            return 0.0;
        }

        // 離島判斷
        $island_cities = [ '澎湖縣', '金門縣', '連江縣' ];
        $city = $address['city'] ?? '';

        $is_island = false;
        foreach ( $island_cities as $island ) {
            if ( str_contains( $city, $island ) ) {
                $is_island = true;
                break;
            }
        }

        // 郵遞區號判斷
        if ( ! $is_island ) {
            $island_postcodes = [ '880', '881', '882', '883', '884', '885', '890', '891', '892', '893', '894', '209', '210', '211', '212' ];
            $postcode = substr( $address['postcode'] ?? '', 0, 3 );
            $is_island = in_array( $postcode, $island_postcodes, true );
        }

        if ( ! $is_island ) {
            return 0.0;
        }

        return (float) $this->get_option( 'island_surcharge', 0 );
    }
}
