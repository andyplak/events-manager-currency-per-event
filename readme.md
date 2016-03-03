### Events Manager - Currency per Event

Contributors: socarrat
Tags: Events Manager, currency
Requires at least: 3.0.1
Tested up to: 4.2.2
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extend Events Manager by allowing currency to be set per Event

#### Description

An addon for Events Manager: http://wp-events-plugin.com/
This plugin allows an admin user to set the currency per event so that, for example, you can sell one event in Euros and another in US Dollars.

If using Events Manager Pro to send payments to a payment gateway, please note this currently only works with PayPal, PayPal Chained Payments and Sage Pay. If you wish to add another gateway, the plugin will need modifying so that it hooks into the currency selection part of that gateway.

If you're a developer feel free to fork and submit pull requests. Take a look at `em_curr_gateway_sage_get_currency` and `em_curr_gateway_paypal_get_paypal_vars` functions to see how this is done for SagePay and PayPal respectivly.

If you're not a developer but need support of another payment gateway (RealEx, WorldPay, NetBanx etc) then please get in touch via http://www.andyplace.co.uk

NOTE: This will __not__ work in MultiBooking mode.

#### Installation

As you would any other WordPress plugin
