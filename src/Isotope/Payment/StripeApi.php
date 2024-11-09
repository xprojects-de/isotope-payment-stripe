<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Contao\StringUtil;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Order;
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
     * @param string|null $clientReferenceId
     * @return string|null
     */
    private function getCustomerIdByClientReference(
        StripeClient $stripe,
        ?string      $clientReferenceId
    ): ?string
    {
        try {

            if ($clientReferenceId === null) {
                return null;
            }

            $customerSearch = $stripe->customers->search([
                'query' => 'metadata[\'clientReferenceId\']:\'' . $clientReferenceId . '\'',
            ]);

            if (
                $customerSearch instanceof SearchResult &&
                $customerSearch->count() > 0
            ) {
                $customer = $customerSearch->first();

                if ($customer instanceof Customer) {
                    return $customer->id;
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
     * @param string $redirectUrl
     * @param string|null $clientReferenceId
     * @return array
     * @throws \Exception
     */
    public function createOrder(
        ?string $name,
        float   $amount,
        string  $currency,
        string  $redirectUrl,
        ?string $clientReferenceId
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
                'redirect_on_completion' => 'if_required',
                'return_url' => $redirectUrl
            ];

            if (\is_string($clientReferenceId) && $clientReferenceId !== '') {

                $options['saved_payment_method_options'] = ['payment_method_save' => 'enabled'];

                $currentCustomerId = $this->getCustomerIdByClientReference($stripe, $clientReferenceId);
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
     * @param string|null $clientReferenceId
     * @param array|null $customerInfo
     * @return void
     */
    private function updateCustomerInformation(
        StripeClient $stripe,
        Session      $session,
        ?string      $clientReferenceId,
        ?array       $customerInfo
    ): void
    {
        try {

            if ($clientReferenceId === null) {
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
                    \array_key_exists('clientReferenceId', $metadata) &&
                    $metadata['clientReferenceId'] === $clientReferenceId
                ) {
                    $update = false;
                }

                if ($update === true) {

                    StripeStripe::setApiKey($this->stripePrivateKey);
                    Customer::update($customer->id, [
                        'metadata' => [
                            'clientReferenceId' => $clientReferenceId,
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
     * @param string|null $clientReferenceId
     * @param array|null $customerInfo
     * @return string|null
     */
    public function captureOrder(
        string  $clientSession,
        ?string $clientReferenceId,
        ?array  $customerInfo
    ): ?string
    {
        try {

            $stripe = new StripeClient($this->stripePrivateKey);

            $session = $stripe->checkout->sessions->retrieve($clientSession);
            if (!$session instanceof Session) {
                throw new \Exception('invalid ResultSession');
            }

            $this->updateCustomerInformation($stripe, $session, $clientReferenceId, $customerInfo);

            // @TODO Check paymentStatus if unpaid
            $paymentStatus = $session->payment_status;

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
     * @param array $stripeData
     * @return void
     */
    protected function storePaymentData(
        IsotopeProductCollection $collection,
        array                    $stripeData
    ): void
    {
        $paymentData = StringUtil::deserialize($collection->payment_data, true);

        $paymentData['STRIPE_PAYMENT'] = $stripeData;
        $collection->payment_data = $paymentData;

        $collection->save();

    }

    /**
     * @param IsotopeProductCollection $collection
     * @return array
     */
    protected function retrievePaymentData(IsotopeProductCollection $collection): array
    {
        $paymentData = StringUtil::deserialize($collection->payment_data, true);
        return \array_key_exists('STRIPE_PAYMENT', $paymentData) ? $paymentData['STRIPE_PAYMENT'] : [];

    }

    /**
     * @param $orderId
     * @return string
     */
    public function backendInterface($orderId): string
    {
        if (($objOrder = Order::findByPk($orderId)) === null) {
            return parent::backendInterface($orderId);
        }

        $arrPayment = StringUtil::deserialize($objOrder->payment_data, true);

        if (
            !isset($arrPayment['STRIPE_PAYMENT']) ||
            !is_array($arrPayment['STRIPE_PAYMENT'])
        ) {
            return parent::backendInterface($orderId);
        }

        $strBuffer = '<div id="tl_buttons"></div>';
        $strBuffer .= '<h2 class="sub_headline">' . $this->name . ' (' . $GLOBALS['TL_LANG']['MODEL']['tl_iso_payment'][$this->type][0] . ')' . '</h2>';
        $strBuffer .= '<div id="tl_soverview"><div id="tl_message"><div class="tl_info">Payment-Ident: ' . ($arrPayment['STRIPE_PAYMENT']['paymentIntent'] ?? '') . '</div></div></div>';

        return $strBuffer;

    }

}