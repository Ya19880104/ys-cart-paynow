# PayNow Headless Integration

## Shipping method IDs

Use these IDs when rendering or submitting shipping choices:

```text
ys_ec_paynow_ship_711
ys_ec_paynow_ship_family
ys_ec_paynow_ship_hilife
ys_ec_paynow_ship_tcat
```

## Store selection

For convenience-store methods, request a PayNow store-map URL:

```text
POST /wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url
```

Expected payload:

```json
{
  "shipping_method": "ys_ec_paynow_ship_711",
  "return_url": "https://example.com/checkout"
}
```

The returned URL should be opened by the customer. PayNow posts selected store data back to:

```text
/wp-json/ys-ecommerce/v1/paynow/store-callback
```

## Security notes

- Validate shipping method IDs against the YS CART checkout response.
- Do not expose PayNow hash key or hash IV to browser code.
- Treat callback payloads as untrusted until provider validation succeeds.
