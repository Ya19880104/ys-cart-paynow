# YS CART PayNow Headless Skill

Use this when integrating PayNow logistics with a headless YS CART storefront.

- Use YS CART shipping method IDs beginning with `ys_ec_paynow_ship_`.
- For CVS methods, request `/stores/paynow/map-url` before redirecting customers to store selection.
- Send the selected method as `shipping_id`; do not use `shipping_method` for
  PayNow map requests.
- Treat `/paynow/store-callback` as a provider callback route, not a browser UI
  API.
- Do not expose PayNow merchant credentials or hash values in browser code.
- Keep PayNow runtime source outside YS CART core.
- Verify the plugin is installed and active through YS Hub Installer before debugging shipping registration.
