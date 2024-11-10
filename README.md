Stripe Payment for Isotope for Contao
======================

This module enables you to use [Stripe](https://www.stripe.com) as a payment module
for [Isotope eCommerce](https://isotopeecommerce.org) as an embedded view.

## Note

Please feel free to improve the module! :-)

## Configuration

In the payment module, you have to configure the following setting:

* public Key
* private Key

To enable test mode you have to set the test keys to the key fields. For production use the production keys.

Optional you can allow additional "Status" and "Payment-Status" next to "paid" and "complete". One reason may be if direct debit (german "Lastschrift") is used. For this Stripe always returns the status unpaid.

The Stripe-Widget itself (e.g. payment methods, etc.) is configured in stripe account.

### Screenshots

<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/1.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/2.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/3.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/4.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/5.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/6.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/7.png" width="600">
<img src="https://raw.githubusercontent.com/xprojects-de/isotope-payment-stripe/refs/heads/main/tests/docs/8.png" width="600">
