<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Contao\StringUtil;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Model\Payment;
use Isotope\Module\Checkout;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\SearchResult;
use Stripe\StripeClient;
use Stripe\Stripe as StripeStripe;

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
    public function createOrder(
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
     * @param StripeClient $stripe
     * @param Session $session
     * @param string|null $referenceId
     * @param array|null $customerInfo
     * @return void
     */
    private function updateCustomerInformation(
        StripeClient $stripe,
        Session      $session,
        ?string      $referenceId,
        ?array       $customerInfo
    ): void
    {
        try {

            if ($referenceId === null) {
                return;
            }

            $customer = $session->customer;
            if (\is_string($customer)) {
                $customer = $stripe->customers->retrieve($customer);
            }

            if ($customer instanceof Customer) {

                $update = true;

                $metadata = $customer->metadata->toArray();
                if (
                    \is_array($metadata) &&
                    \array_key_exists('referenceId', $metadata) &&
                    $metadata['referenceId'] === $referenceId
                ) {
                    $update = false;
                }

                if ($update === true) {

                    StripeStripe::setApiKey($this->stripePrivateKey);
                    Customer::update($customer->id, [
                        'metadata' => [
                            'referenceId' => $referenceId,
                            'firstName' => ($customerInfo['firstName'] ?? ''),
                            'lastName' => ($customerInfo['lastName'] ?? ''),
                            'email' => ($customerInfo['email'] ?? '')
                        ]
                    ]);

                }

            }

        } catch (\Throwable) {
        }

    }

    /**
     * @param string $clientSession
     * @param string|null $referenceId
     * @param array|null $customerInfo
     * @return string|null
     */
    public function captureOrder(
        string  $clientSession,
        ?string $referenceId,
        ?array  $customerInfo
    ): ?string
    {
        try {

            $stripe = new StripeClient($this->stripePrivateKey);

            $session = $stripe->checkout->sessions->retrieve($clientSession);
            if (!$session instanceof Session) {
                throw new \Exception('invalid ResultSession');
            }

            $this->updateCustomerInformation($stripe, $session, $referenceId, $customerInfo);

            $paymentIntent = $session->payment_intent;
            if (\is_string($paymentIntent)) {
                return $paymentIntent;
            }

            if ($paymentIntent instanceof PaymentIntent) {
                return $paymentIntent->id;
            }

        } catch (\Throwable) {

        }

        return null;

    }

    /**
     * @param IsotopeProductCollection $collection
     * @param string $clientSession
     * @param string $paymentIntent
     * @return void
     */
    protected function storePaymentIntent(
        IsotopeProductCollection $collection,
        string                   $clientSession,
        string                   $paymentIntent
    ): void
    {
        $paymentData = StringUtil::deserialize($collection->payment_data, true);

        $paymentData['STRIPE_PAYMENT_SESSION'] = $clientSession;
        $paymentData['STRIPE_PAYMENT_INTENT'] = $paymentIntent;

        $collection->payment_data = $paymentData;
        $collection->save();

    }

    /**
     * @param IsotopeProductCollection $collection
     * @return array
     */
    protected function retrievePaymentIntent(IsotopeProductCollection $collection): array
    {
        $paymentData = StringUtil::deserialize($collection->payment_data, true);

        $data = [];

        if (\array_key_exists('STRIPE_PAYMENT_SESSION', $paymentData)) {
            $data['STRIPE_PAYMENT_SESSION'] = $paymentData['STRIPE_PAYMENT_SESSION'];
        }

        if (\array_key_exists('STRIPE_PAYMENT_INTENT', $paymentData)) {
            $data['STRIPE_PAYMENT_INTENT'] = $paymentData['STRIPE_PAYMENT_INTENT'];
        }

        return $data;

    }

}