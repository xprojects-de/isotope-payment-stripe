<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Hashids\Hashids;
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
    public static string $STRIPE_JS = 'https://js.stripe.com/v3/';

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
     * @param bool $enablePaymentMethodSave
     * @return array
     * @throws \Exception
     */
    protected function createOrder(
        ?string $name,
        float   $amount,
        string  $currency,
        string  $redirectUrl,
        ?string $clientReferenceId,
        bool    $enablePaymentMethodSave
    ): array
    {
        try {

            if (!is_string($name) || $name === '') {
                $name = '#' . time();
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

            if (is_string($clientReferenceId) && $clientReferenceId !== '') {

                if ($enablePaymentMethodSave === true) {
                    $options['saved_payment_method_options'] = ['payment_method_save' => 'enabled'];
                }

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
     * @param IsotopeProductCollection $objOrder
     * @return void
     */
    private function updateCustomerInformation(
        StripeClient             $stripe,
        Session                  $session,
        ?string                  $clientReferenceId,
        IsotopeProductCollection $objOrder
    ): void
    {
        try {

            if ($clientReferenceId === null) {
                return;
            }

            $customer = $session->customer;
            if (is_string($customer)) {
                $customer = $stripe->customers->retrieve($customer);
            }

            if ($customer instanceof Customer) {

                $billingAddress = $objOrder->getBillingAddress();

                $updateObject = [
                    'metadata' => [
                        'clientReferenceId' => $clientReferenceId,
                        'email' => ($billingAddress->email ?? '')
                    ]
                ];

                $customerName = ($billingAddress->firstname ?? '') . ' ' . ($billingAddress->lastname ?? '');
                $updateObject['name'] = trim($customerName);

                $email = ($billingAddress->email ?? '');
                if (trim($email) !== '' && Validator::isEmail($email)) {
                    $updateObject['email'] = trim($email);
                }

                if (
                    $billingAddress->street_1 !== null && $billingAddress->street_1 !== '' &&
                    $billingAddress->city !== null && $billingAddress->city !== '' &&
                    $billingAddress->postal !== null && $billingAddress->postal !== '' &&
                    is_string($billingAddress->country) && $billingAddress->country !== ''
                ) {

                    $updateObject['address'] = [
                        'city' => $billingAddress->city,
                        'country' => strtoupper($billingAddress->country),
                        'line1' => $billingAddress->street_1,
                        'postal_code' => $billingAddress->postal
                    ];

                    if (is_string($billingAddress->subdivision) && $billingAddress->subdivision !== '') {
                        $updateObject['address']['state'] = $billingAddress->subdivision;
                    }

                }

                StripeStripe::setApiKey($this->stripePrivateKey);
                Customer::update($customer->id, $updateObject);

            }

        } catch (\Throwable $tr) {
            System::log('Product collection ID "' . $objOrder->getId() . '" update customer failed: ' . $tr->getMessage(), __METHOD__, TL_ERROR);
        }

    }

    /**
     * @param string $clientSession
     * @param string|null $clientReferenceId
     * @param IsotopeProductCollection $objOrder
     * @return string|null
     */
    protected function captureOrder(
        string                   $clientSession,
        ?string                  $clientReferenceId,
        IsotopeProductCollection $objOrder
    ): ?string
    {
        try {

            $validStatus = [
                Session::STATUS_COMPLETE
            ];

            if (is_string($this->stripeWhitelistStatus)) {

                $additionalValidStatus = StringUtil::deserialize($this->stripeWhitelistStatus);
                if (is_array($additionalValidStatus) && count($additionalValidStatus) > 0) {
                    $validStatus = array_merge($validStatus, $additionalValidStatus);
                }
            }

            $validPaymentStatus = [
                Session::PAYMENT_STATUS_PAID
            ];

            if (is_string($this->stripeWhitelistPaymentStatus)) {

                $additionalValidPaymentStatus = StringUtil::deserialize($this->stripeWhitelistPaymentStatus);
                if (is_array($additionalValidPaymentStatus) && count($additionalValidPaymentStatus) > 0) {
                    $validPaymentStatus = array_merge($validPaymentStatus, $additionalValidPaymentStatus);
                }
            }

            $stripe = new StripeClient($this->stripePrivateKey);

            $session = $stripe->checkout->sessions->retrieve($clientSession);
            if (!$session instanceof Session) {
                throw new \Exception('invalid ResultSession');
            }

            $status = $session->status;

            if (
                !\is_string($status) ||
                !\in_array($status, $validStatus, true)
            ) {
                throw new \Exception('invalid status');
            }

            $paymentStatus = $session->payment_status;

            if (
                !\is_string($paymentStatus) ||
                !\in_array($paymentStatus, $validPaymentStatus, true)
            ) {
                throw new \Exception('invalid payment status');
            }

            $this->updateCustomerInformation($stripe, $session, $clientReferenceId, $objOrder);

            $paymentIntent = $session->payment_intent;
            if (is_string($paymentIntent)) {
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
        return array_key_exists('STRIPE_PAYMENT', $paymentData) ? $paymentData['STRIPE_PAYMENT'] : [];

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

        $strBuffer .= '<div id="tl_soverview">';
        $strBuffer .= '<div id="tl_message">';

        $info = 'Payment-Ident: ' . ($arrPayment['STRIPE_PAYMENT']['paymentIntent'] ?? '') . '<br>';
        $info .= 'Product-Name: ' . ($arrPayment['STRIPE_PAYMENT']['productName'] ?? '');
        $strBuffer .= '<div class="tl_info">' . $info . '</div>';

        $strBuffer .= '</div>';
        $strBuffer .= '</div>';

        return $strBuffer;

    }

    /**
     * @param int $id
     * @return string
     */
    protected function generateHash(int $id): string
    {
        try {

            $secret = System::getContainer()->getParameter('kernel.secret');
            return (new Hashids($secret, 8, 'abcdefghijkmnopqrstuvwxyz'))->encode($id);

        } catch (\Throwable) {

        }

        return '' . time();

    }

}