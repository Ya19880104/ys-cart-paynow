<?php
/**
 * PayNow 物流 API 請求器
 *
 * 負責與 PayNow 物流 API 通訊，包含建立物流單、查詢狀態、取消、取得列印 URL。
 * 使用 wp_remote_post() 搭配 AES 加密進行安全通訊。
 *
 * @package YangSheep\YSCartPaynow\Shipping\Paynow
 */

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;

class YSPaynowShippingRequester {

    /**
     * API 端點路徑
     */
    private const ENDPOINT_CREATE = '/create';
    private const ENDPOINT_QUERY  = '/query';
    private const ENDPOINT_CANCEL = '/cancel';
    private const ENDPOINT_PRINT  = '/print';

    /**
     * PayNow 物流實例（提供加密金鑰與 API URL）
     *
     * @var YSPaynowShipping
     */
    private YSPaynowShipping $shipping;

    /**
     * 建構子
     *
     * @param YSPaynowShipping $shipping PayNow 物流實例
     */
    public function __construct( YSPaynowShipping $shipping ) {
        $this->shipping = $shipping;
    }

    /**
     * 建立物流訂單
     *
     * @param array $order_data 訂單資料
     *   - order_id:        int    訂單 ID
     *   - order_number:    string 訂單編號
     *   - sender_name:     string 寄件人姓名
     *   - sender_phone:    string 寄件人電話
     *   - sender_zipcode:  string 寄件人郵遞區號
     *   - sender_address:  string 寄件人地址
     *   - receiver_name:   string 收件人姓名
     *   - receiver_phone:  string 收件人電話
     *   - receiver_store_id:    string 超商門市代號（CVS 專用）
     *   - receiver_zipcode:     string 收件人郵遞區號（宅配專用）
     *   - receiver_address:     string 收件人地址（宅配專用）
     *   - product_name:    string 商品名稱
     *   - product_amount:  int    商品金額
     *   - temperature_code: string 溫層代碼（宅配專用）
     * @return array 成功: [success => true, label_id, tracking_no, ...] 失敗: [success => false, message]
     */
    public function create_order( array $order_data ): array {
        $params = [
            'OrderNo'      => sanitize_text_field( $order_data['order_number'] ?? '' ),
            'ServiceCode'  => $this->shipping->get_service_code(),
            'SenderName'   => sanitize_text_field( $order_data['sender_name'] ?? '' ),
            'SenderPhone'  => sanitize_text_field( $order_data['sender_phone'] ?? '' ),
            'ReceiverName'  => sanitize_text_field( $order_data['receiver_name'] ?? '' ),
            'ReceiverPhone' => sanitize_text_field( $order_data['receiver_phone'] ?? '' ),
            'GoodsName'     => mb_substr( sanitize_text_field( $order_data['product_name'] ?? '' ), 0, 30 ),
            'Amount'        => absint( $order_data['product_amount'] ?? 0 ),
        ];

        // 超商取貨需要門市代號
        if ( $this->shipping->get_type() === 'cvs' ) {
            $params['StoreID'] = sanitize_text_field( $order_data['receiver_store_id'] ?? '' );
        } else {
            // 宅配需要地址
            $params['SenderZipCode']    = sanitize_text_field( $order_data['sender_zipcode'] ?? '' );
            $params['SenderAddress']    = sanitize_text_field( $order_data['sender_address'] ?? '' );
            $params['ReceiverZipCode']  = sanitize_text_field( $order_data['receiver_zipcode'] ?? '' );
            $params['ReceiverAddress']  = sanitize_text_field( $order_data['receiver_address'] ?? '' );

            // 溫層代碼
            if ( ! empty( $order_data['temperature_code'] ) ) {
                $params['TempCode'] = sanitize_text_field( $order_data['temperature_code'] );
            }
        }

        /**
         * 篩選建立物流訂單的參數
         *
         * @param array $params     請求參數
         * @param array $order_data 原始訂單資料
         */
        $params = apply_filters( 'ys_ec_paynow_shipping_create_params', $params, $order_data );

        $response = $this->send_request( self::ENDPOINT_CREATE, $params );

        if ( ! $response['success'] ) {
            return $response;
        }

        $data = $response['data'];

        YSLogger::info( 'paynow_shipping', '物流單建立成功', [
            'order_number' => $order_data['order_number'] ?? '',
            'label_id'     => $data['ShipNo'] ?? '',
        ] );

        return [
            'success'     => true,
            'label_id'    => $data['ShipNo'] ?? '',
            'tracking_no' => $data['TrackingNo'] ?? $data['ShipNo'] ?? '',
            'status'      => $data['Status'] ?? '',
            'raw'         => $data,
        ];
    }

    /**
     * 查詢物流狀態
     *
     * @param string $label_id 物流單號
     * @return array 成功: [success => true, status, status_text, ...] 失敗: [success => false, message]
     */
    public function query_status( string $label_id ): array {
        $params = [
            'ShipNo' => sanitize_text_field( $label_id ),
        ];

        $response = $this->send_request( self::ENDPOINT_QUERY, $params );

        if ( ! $response['success'] ) {
            return $response;
        }

        $data = $response['data'];

        return [
            'success'     => true,
            'status'      => $data['ShipStatus'] ?? '',
            'status_text' => $data['ShipStatusMsg'] ?? '',
            'tracking_no' => $data['TrackingNo'] ?? '',
            'update_time' => $data['UpdateTime'] ?? '',
            'raw'         => $data,
        ];
    }

    /**
     * 取消物流訂單
     *
     * @param string $label_id 物流單號
     * @return bool 是否取消成功
     */
    public function cancel_order( string $label_id ): bool {
        $params = [
            'ShipNo' => sanitize_text_field( $label_id ),
        ];

        $response = $this->send_request( self::ENDPOINT_CANCEL, $params );

        if ( $response['success'] ) {
            YSLogger::info( 'paynow_shipping', '物流單取消成功', [
                'label_id' => $label_id,
            ] );
        }

        return $response['success'];
    }

    /**
     * 取得列印 URL
     *
     * @param string|array $label_ids 單一或多個物流單號
     * @return string 列印 URL，失敗回傳空字串
     */
    public function get_print_url( string|array $label_ids ): string {
        if ( is_string( $label_ids ) ) {
            $label_ids = [ $label_ids ];
        }

        $params = [
            'ShipNo' => implode( ',', array_map( 'sanitize_text_field', $label_ids ) ),
        ];

        $response = $this->send_request( self::ENDPOINT_PRINT, $params );

        if ( ! $response['success'] ) {
            return '';
        }

        return $response['data']['PrintUrl'] ?? '';
    }

    // ─── 私有方法 ───

    /**
     * 發送 API 請求
     *
     * @param string $endpoint API 端點路徑
     * @param array  $params   請求參數（明文）
     * @return array [success => bool, data => array|null, message => string]
     */
    private function send_request( string $endpoint, array $params ): array {
        $encrypted = $this->shipping->encrypt_request( $params );

        if ( empty( $encrypted ) ) {
            return [
                'success' => false,
                'data'    => null,
                'message' => '請求加密失敗',
            ];
        }

        $url = $this->shipping->get_api_base_url() . $endpoint;

        YSLogger::debug( 'paynow_shipping', "發送請求至 {$endpoint}", [
            'url'    => $url,
            'params' => array_keys( $params ),
        ] );

        $response = wp_remote_post( $url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $encrypted,
        ] );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            YSLogger::error( 'paynow_shipping', "HTTP 請求失敗：{$error_message}", [
                'endpoint' => $endpoint,
            ] );
            return [
                'success' => false,
                'data'    => null,
                'message' => "HTTP 請求失敗：{$error_message}",
            ];
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );

        if ( 200 !== $http_code ) {
            YSLogger::error( 'paynow_shipping', "API 回應異常：HTTP {$http_code}", [
                'endpoint' => $endpoint,
                'body'     => mb_substr( $body, 0, 500 ),
            ] );
            return [
                'success' => false,
                'data'    => null,
                'message' => "API 回應異常：HTTP {$http_code}",
            ];
        }

        $json = json_decode( $body, true );

        if ( ! is_array( $json ) ) {
            YSLogger::error( 'paynow_shipping', 'API 回應格式錯誤', [
                'endpoint' => $endpoint,
            ] );
            return [
                'success' => false,
                'data'    => null,
                'message' => 'API 回應格式錯誤',
            ];
        }

        // 檢查 PayNow 回應狀態碼
        $result_code = $json['ResultCode'] ?? '';
        if ( '00' !== $result_code && 'SUCCESS' !== ( $json['Status'] ?? '' ) ) {
            $message = $json['ResultMessage'] ?? $json['Message'] ?? '未知錯誤';
            YSLogger::error( 'paynow_shipping', "API 回應錯誤：{$message}", [
                'endpoint'    => $endpoint,
                'result_code' => $result_code,
            ] );
            return [
                'success' => false,
                'data'    => null,
                'message' => $message,
            ];
        }

        // 解密回應資料
        $encrypt_data = $json['EncryptData'] ?? '';
        $hash_data    = $json['HashData'] ?? '';

        if ( empty( $encrypt_data ) || empty( $hash_data ) ) {
            return [
                'success' => true,
                'data'    => $json,
                'message' => '',
            ];
        }

        $decrypted = $this->shipping->decrypt_response( $encrypt_data, $hash_data );

        if ( null === $decrypted ) {
            return [
                'success' => false,
                'data'    => null,
                'message' => '回應資料解密失敗',
            ];
        }

        return [
            'success' => true,
            'data'    => $decrypted,
            'message' => '',
        ];
    }
}
