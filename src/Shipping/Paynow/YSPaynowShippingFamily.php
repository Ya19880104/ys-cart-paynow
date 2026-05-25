<?php
/**
 * PayNow 全家超商取貨物流
 *
 * 服務代碼 03，繼承 YSPaynowShipping。
 *
 * @package YangSheep\YSCartPaynow\Shipping\Paynow
 */

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

class YSPaynowShippingFamily extends YSPaynowShipping {

    /**
     * 物流方式唯一 ID
     *
     * @var string
     */
    protected string $id = 'ys_ec_paynow_ship_family';

    /**
     * 物流方式標題
     *
     * @var string
     */
    protected string $title = 'PayNow 全家超商取貨';

    /**
     * 物流類型
     *
     * @var string
     */
    protected string $type = 'cvs';

    /**
     * PayNow 服務代碼
     *
     * @var string
     */
    protected string $service_code = '03';

    /**
     * 計算運費
     *
     * @param array $cart_items 購物車項目
     * @param array $address    收件地址資料（超商取貨不使用）
     * @return float 運費金額
     */
    public function calculate_cost( array $cart_items, array $address = [] ): float {
        $subtotal = $this->calculate_subtotal( $cart_items );

        $free_threshold = $this->get_free_threshold();
        if ( $free_threshold > 0 && $subtotal >= $free_threshold ) {
            return 0.0;
        }

        return (float) $this->get_option( 'base_fee', 60 );
    }

    /**
     * 是否支援超商選店
     */
    public function supports_cvs_selection(): bool {
        return true;
    }

    /**
     * 取得後台設定欄位定義
     */
    public function get_settings_fields(): array {
        $fields = parent::get_settings_fields();

        $fields['max_amount']['default'] = '20000';
        $fields['max_amount']['desc']    = '全家超商取貨金額上限（建議 20,000）';

        $fields['max_weight']['default'] = '5';
        $fields['max_weight']['desc']    = '超商取貨重量上限（kg）';

        return $fields;
    }
}
