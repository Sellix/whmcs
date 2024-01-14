# WHMCS Module

![](https://img.shields.io/badge/Sellix-WHMCS-blueviolet) ![](https://img.shields.io/badge/Version-v1.0.0-green)

<p align="center">
  <img src="https://cdn.sellix.io/static/previews/woocommerce.jpeg" alt="Sellix Logo"/>
</p>

WHMCS module to use Sellix as a Payment Gateway.

# Installation

0. Download the latest release ZIP [on GitHub](https://github.com/Sellix/whmcs/releases).

1. Unzip and upload file at <WHMCS_INSTALLATION_DIR>/modules/gateways/

2. Login into your WHMCS admin area, go to `System Settings->Payment Gateways`, search for `Sellix` from the list of `All Payment gateways` and **click to activate**.

3. Fill the API details.

## Changelog

= 1.1 =
* Initial release.

== Upgrade Notice ==

= 1.2 =
- Fixed wrong paid total

= 1.3 =
- Added origin parameter to the gateway

= 1.4 =
- Gateway URLs traling slash issue is fixed.
- Used formatted invoice number instead of sequential invoice id.

= 1.4.2 =
- Made invoices open in a new tab when pay is clicked

= 1.5.0 =
- Create a new Sellix invoice whenever payment is requested instead of creating once and use that link.

= 1.5.1 =
- Now for invoice amount 0, sellix invoice will not be created.
- And if already invoice paid, sellix invoice will not be created.
- And also for every page refresh on the invoice page, new sellix invoice will not be created.
- First created sellix invoice url will be used as before.
- But now we made changes to the code that, if an invoice email, currency, or amount changed, then new sellix invoice is created with new customer and payment details instead of previously created sellix invoice url.
