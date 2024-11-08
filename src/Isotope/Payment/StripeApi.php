<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Model\Payment;
use Isotope\Module\Checkout;
use Stripe\Customer;
use Stripe\SearchResult;
use Stripe\StripeClient;

abstract class StripeApi extends Payment
{
    /**
     * @param StripeClient $stripe
     * @param string|null $orderReference
     * @return string|null
     */
    private function getCustomerIdByOrderReference(
        StripeClient $stripe,
        ?string      $orderReference
    ): ?string
    {
        try {

            if ($orderReference === null) {
                return null;
            }

            $customerSearch = $stripe->customers->search([
                'query' => 'metadata[\'referenceId\']:\'' . $orderReference . '\'',
            ]);

            if ($customerSearch instanceof SearchResult) {

                if ($customerSearch->count() > 0) {

                    $customer = $customerSearch->first();

                    if ($customer instanceof Customer) {
                        return $customer->id;
                    }

                }

            }

        } catch (\Exception) {
        }

        return null;

    }

    /**
     * @param string|null $name
     * @param float $amount
     * @param string $currency
     * @param string|null $redirectUrl
     * @param string|null $orderReference
     * @return array
     * @throws \Exception
     */
    private function createOrder(
        ?string $name,
        float   $amount,
        string  $currency,
        ?string $redirectUrl,
        ?string $orderReference
    ): array
    {
        try {

            if (!\is_string($name) || $name === '') {
                $name = 'iso_order_' . \time();
            }

            $amountInt = (int)($amount * 100);

            $stripe = new StripeClient($this->stripePrivateKey);

            $options = [
                'ui_mode' => 'embedded',
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $name,
                        ],
                        'unit_amount' => $amountInt,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'redirect_on_completion' => 'never'
            ];

            if (\is_string($redirectUrl) && \trim($redirectUrl) !== '') {

                $explodePattern = '?';
                if (\str_contains($redirectUrl, '?')) {
                    $explodePattern = '&';
                }

                $options['redirect_on_completion'] = 'if_required';

                if (\is_string($orderReference) && $orderReference !== '') {
                    $options['return_url'] = \trim($redirectUrl) . $explodePattern . 'iso_order_reference=' . $orderReference . '&iso_clientSession={CHECKOUT_SESSION_ID}';
                } else {
                    $options['return_url'] = \trim($redirectUrl) . $explodePattern . 'iso_clientSession={CHECKOUT_SESSION_ID}';
                }

            }

            if (\is_string($orderReference) && $orderReference !== '') {

                $options['saved_payment_method_options'] = ['payment_method_save' => 'enabled'];

                $currentCustomerId = $this->getCustomerIdByOrderReference($stripe, $orderReference);
                if ($currentCustomerId !== null) {
                    $options['customer'] = $currentCustomerId;
                } else {
                    $options['customer_creation'] = 'always';
                }

            }

            $checkout_session = $stripe->checkout->sessions->create($options);

            return [
                $checkout_session->client_secret,
                $checkout_session->id
            ];

        } catch (\Throwable $tr) {
            throw new \Exception($tr->getMessage());
        }

    }


    /**
     * @param IsotopePurchasableCollection $order
     * @return array
     * @throws \Exception
     */
    public function createPayment(IsotopePurchasableCollection $order): array
    {
        return $this->createOrder(
            'iso_order_' . $order->getId(),
            $order->getTotal(), // number_format($order->getTotal(), 2)
            $order->getCurrency(),
            Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $order, null, true),
            'iso_address_' . $order->getBillingAddress()->id
        );

    }

}