<?php

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

class YSPaynowShippingHilife extends YSPaynowShipping {
	protected string $id           = 'ys_ec_paynow_ship_hilife';
	protected string $title        = 'PayNow 萊爾富超商取貨';
	protected string $type         = 'cvs';
	protected string $service_code = '05';

	public function calculate_cost( array $cart_items, array $address = [] ): float {
		unset( $address );

		$subtotal       = $this->calculate_subtotal( $cart_items );
		$free_threshold = $this->get_free_threshold();
		if ( $free_threshold > 0 && $subtotal >= $free_threshold ) {
			return 0.0;
		}

		return (float) $this->get_option( 'base_fee', 60 );
	}

	public function supports_cvs_selection(): bool {
		return true;
	}

	public function get_settings_fields(): array {
		$fields = parent::get_settings_fields();
		$fields['max_amount']['default'] = '20000';
		$fields['max_amount']['desc']    = '超商取貨最高訂單金額建議不超過 20,000。';
		$fields['max_weight']['default'] = '5';
		$fields['max_weight']['desc']    = '超商取貨重量限制建議 5kg。';

		return $fields;
	}
}
