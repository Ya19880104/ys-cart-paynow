<?php

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

class YSPaynowShippingTcat extends YSPaynowShipping {
	protected string $id           = 'ys_ec_paynow_ship_tcat';
	protected string $title        = 'PayNow 黑貓宅配';
	protected string $type         = 'home';
	protected string $service_code = '36';

	public const TEMP_NORMAL  = 'normal';
	public const TEMP_CHILLED = 'chilled';
	public const TEMP_FROZEN  = 'frozen';

	private const TEMP_LABELS = [
		self::TEMP_NORMAL  => '常溫',
		self::TEMP_CHILLED => '冷藏',
		self::TEMP_FROZEN  => '冷凍',
	];

	private const TEMP_CODES = [
		self::TEMP_NORMAL  => '0001',
		self::TEMP_CHILLED => '0002',
		self::TEMP_FROZEN  => '0003',
	];

	public function calculate_cost( array $cart_items, array $address = [] ): float {
		$subtotal       = $this->calculate_subtotal( $cart_items );
		$free_threshold = $this->get_free_threshold();
		if ( $free_threshold > 0 && $subtotal >= $free_threshold ) {
			return $this->get_island_surcharge( $address );
		}

		$temperature      = $this->get_current_temperature();
		$base_fee         = (float) $this->get_option( 'base_fee_' . $temperature, $this->get_default_base_fee( $temperature ) );
		$weight_surcharge = $this->calculate_weight_surcharge( $cart_items );
		$island_surcharge = $this->get_island_surcharge( $address );

		return round( $base_fee + $weight_surcharge + $island_surcharge, 0 );
	}

	public function supports_cvs_selection(): bool {
		return false;
	}

	public function supports_cod(): bool {
		return true;
	}

	public function get_current_temperature(): string {
		$temp = $this->get_option( 'temperature', self::TEMP_NORMAL );

		return array_key_exists( $temp, self::TEMP_LABELS ) ? $temp : self::TEMP_NORMAL;
	}

	public function get_temperature_code( string $temperature = '' ): string {
		if ( '' === $temperature ) {
			$temperature = $this->get_current_temperature();
		}

		return self::TEMP_CODES[ $temperature ] ?? self::TEMP_CODES[ self::TEMP_NORMAL ];
	}

	public static function get_temperature_labels(): array {
		return self::TEMP_LABELS;
	}

	public function get_settings_fields(): array {
		$fields = parent::get_settings_fields();
		unset( $fields['base_fee'] );

		$fields['temperature'] = [
			'type'    => 'select',
			'label'   => '預設溫層',
			'default' => self::TEMP_NORMAL,
			'options' => self::TEMP_LABELS,
			'desc'    => '此物流方法預設使用的宅配溫層。',
		];
		$fields['base_fee_normal'] = [
			'type'    => 'number',
			'label'   => '常溫運費',
			'default' => '100',
			'desc'    => '常溫宅配基本運費。',
		];
		$fields['base_fee_chilled'] = [
			'type'    => 'number',
			'label'   => '冷藏運費',
			'default' => '150',
			'desc'    => '冷藏宅配基本運費。',
		];
		$fields['base_fee_frozen'] = [
			'type'    => 'number',
			'label'   => '冷凍運費',
			'default' => '180',
			'desc'    => '冷凍宅配基本運費。',
		];
		$fields['weight_surcharge_threshold'] = [
			'type'    => 'number',
			'label'   => '重量加價門檻（kg）',
			'default' => '5',
			'desc'    => '超過此重量後開始加收運費。',
		];
		$fields['weight_surcharge_per_kg'] = [
			'type'    => 'number',
			'label'   => '每公斤加價',
			'default' => '15',
			'desc'    => '超過重量門檻後，每公斤加收的金額。',
		];
		$fields['island_surcharge'] = [
			'type'    => 'number',
			'label'   => '離島加價運費',
			'default' => '0',
			'desc'    => '離島地址額外加收的運費。',
		];

		return $fields;
	}

	private function get_default_base_fee( string $temperature ): int {
		return match ( $temperature ) {
			self::TEMP_CHILLED => 150,
			self::TEMP_FROZEN  => 180,
			default            => 100,
		};
	}

	private function calculate_weight_surcharge( array $cart_items ): float {
		$total_weight = $this->calculate_total_weight( $cart_items );
		$threshold    = (float) $this->get_option( 'weight_surcharge_threshold', 5 );
		$per_kg       = (float) $this->get_option( 'weight_surcharge_per_kg', 15 );

		if ( $total_weight <= $threshold || $per_kg <= 0 ) {
			return 0.0;
		}

		return ceil( $total_weight - $threshold ) * $per_kg;
	}

	private function get_island_surcharge( array $address ): float {
		if ( empty( $address ) ) {
			return 0.0;
		}

		$island_cities = [ '澎湖縣', '金門縣', '連江縣' ];
		$city          = (string) ( $address['city'] ?? '' );
		$is_island     = false;

		foreach ( $island_cities as $island ) {
			if ( str_contains( $city, $island ) ) {
				$is_island = true;
				break;
			}
		}

		if ( ! $is_island ) {
			$island_postcodes = [ '880', '881', '882', '883', '884', '885', '890', '891', '892', '893', '894', '209', '210', '211', '212' ];
			$postcode         = substr( (string) ( $address['postcode'] ?? '' ), 0, 3 );
			$is_island        = in_array( $postcode, $island_postcodes, true );
		}

		return $is_island ? (float) $this->get_option( 'island_surcharge', 0 ) : 0.0;
	}
}
