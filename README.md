# YS CART - PayNow

External PayNow logistics provider for YS CART.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- YS CART 2.48.4+

## Provider contract

- Shipping method IDs:
  - `ys_ec_paynow_ship_711`
  - `ys_ec_paynow_ship_family`
  - `ys_ec_paynow_ship_hilife`
  - `ys_ec_paynow_ship_tcat`
- Admin page slug: `ys-provider-paynow`
- Store map route: `/wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url`
- Store callback route: `/wp-json/ys-ecommerce/v1/paynow/store-callback`

## Headless use

Use YS CART checkout APIs as usual. If the selected shipping method is a PayNow convenience-store method, request the store-map URL from the route above and send the customer to PayNow store selection.

The map request payload uses the YS CART shipping method ID:

```json
{
  "shipping_id": "ys_ec_paynow_ship_711",
  "return_url": "https://example.com/checkout"
}
```

The callback route receives PayNow store selection data and stores it in the YS CART cart/order context.
The callback route is provider-facing and should not be called by browser UI.

## YS Hub updates

This provider bundles the YS Plugin Hub Client runtime under `vendor/yangsheep/ys-plugin-hub-client`.
YS CART can install the provider from YS Hub, and the provider can then receive updates through YS Hub without adding the PayNow/JKoPay runtime back into YS CART core.
Production defaults to `https://yangsheep.com.tw`; staging may override the Hub URL with `YS_CART_HUB_URL` or the `ys_cart_hub_url` option.

## Included user files

- `docs/headless.md`: headless store-map integration notes
- `sdk/ys-cart-paynow-headless.js`: lightweight storefront helper
- `skills/ys-cart-paynow-headless.md`: Codex/agent implementation guidance
- `vendor/yangsheep/ys-plugin-hub-client`: YS Hub install/update runtime
