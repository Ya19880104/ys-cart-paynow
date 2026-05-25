/**
 * Lightweight helper for headless YS CART + PayNow storefronts.
 */
export const YS_CART_PAYNOW_METHODS = Object.freeze({
  sevenEleven: 'ys_ec_paynow_ship_711',
  family: 'ys_ec_paynow_ship_family',
  hilife: 'ys_ec_paynow_ship_hilife',
  tcat: 'ys_ec_paynow_ship_tcat',
});

export const YS_CART_PAYNOW_ROUTES = Object.freeze({
  storeMapUrl: '/wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url',
  storeCallback: '/wp-json/ys-ecommerce/v1/paynow/store-callback',
});

export async function requestPaynowStoreMapUrl(apiBase, shippingMethod, returnUrl, fetchImpl = fetch) {
  const response = await fetchImpl(apiBase.replace(/\/$/, '') + YS_CART_PAYNOW_ROUTES.storeMapUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({
      shipping_method: shippingMethod,
      return_url: returnUrl,
    }),
  });

  if (!response.ok) {
    throw new Error(`PayNow store-map request failed: ${response.status}`);
  }

  return response.json();
}
