<?php
/**
 * PayNow 物流 Carrier Adapter
 *
 * 將 PayNow 的 status code（CREATED/100、SENDER_DELIVERED/200、IN_TRANSIT/300、
 * ARRIVED_AT_STORE/400、PICKED_UP/500、RETURNING/600、RETURNED/700）對應到
 * unified pipeline state。
 *
 * Source: plugins-dev/ys-paynow-shipping/src/utils/YSShippingStatus.php
 *
 * @package YangSheep\Ecommerce\Services\Shipping\Adapters
 * @since   2.27.0
 */

namespace YangSheep\YSCartPaynow\Services\Shipping\Adapters;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Enums\YSShippingPipelineState;
use YangSheep\Ecommerce\Services\Shipping\YSCarrierAdapter;

class YSPaynowAdapter extends YSCarrierAdapter {

	private const CARRIER_TO_PIPELINE = [
		// PayNow code → pipeline state
		'100' => YSShippingPipelineState::ORDER_PLACED,
		'200' => YSShippingPipelineState::PREPARING,
		'300' => YSShippingPipelineState::IN_TRANSIT,
		'400' => YSShippingPipelineState::ARRIVED_AT_STORE,
		'500' => YSShippingPipelineState::DELIVERED,
		'600' => YSShippingPipelineState::RETURNED,
		'700' => YSShippingPipelineState::RETURNED,

		// ys-cart 既有 internal status（YSShippingHandler 寫入）也對應
		'label_created'   => YSShippingPipelineState::PREPARING,
		'label_cancelled' => YSShippingPipelineState::FAILED,
		'in_transit'      => YSShippingPipelineState::IN_TRANSIT,
		'arrived'         => YSShippingPipelineState::ARRIVED_AT_STORE,
		'delivered'       => YSShippingPipelineState::DELIVERED,
		'returned'        => YSShippingPipelineState::RETURNED,
	];

	public function get_id(): string {
		return 'paynow';
	}

	public function map_to_pipeline_state( string $carrier_status ): ?string {
		return self::CARRIER_TO_PIPELINE[ $carrier_status ] ?? null;
	}

	public function supports_webhook(): bool {
		return true; // PayNow shipping 有 webhook callback
	}

	public function supports_query_api(): bool {
		return true; // PayNow 提供查詢 API
	}
}
