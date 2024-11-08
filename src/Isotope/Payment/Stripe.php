<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use Haste\Util\Url;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopePurchasableCollection;
use Isotope\Isotope;
use Isotope\Module\Checkout;
use Isotope\Template;

class Stripe extends StripeApi
{
    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        if (!in_array(Isotope::getConfig()->currency, ['AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD'])) {
            return false;
        }

        return parent::isAvailable();
    }

    /**
     * @param IsotopeProductCollection $objOrder
     * @param Module $objModule
     * @return string
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule): string
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {

            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable', __METHOD__, TL_ERROR);
            Checkout::redirectToStep(Checkout::STEP_COMPLETE, $objOrder);

        }

        $referenceId = 'iso_address_' . $objOrder->getBillingAddress()->id;

        $isoClientSession = Input::get('iso_clientSession');
        if (is_string($isoClientSession) && $isoClientSession !== '') {

            $customerInfoObject = [
                'firstName' => $objOrder->getBillingAddress()->firstname,
                'lastName' => $objOrder->getBillingAddress()->lastname,
                'email' => $objOrder->getBillingAddress()->email
            ];

            $completeOrderId = $this->captureOrder($isoClientSession, $referenceId, $customerInfoObject);
            if (is_string($completeOrderId)) {

                $this->storePaymentIntent($objOrder, $completeOrderId, $isoClientSession);
                Checkout::redirectToStep(Checkout::STEP_COMPLETE, $objOrder);

            }

        }

        try {

            $GLOBALS['TL_JAVASCRIPT'][] = StringUtil::decodeEntities('https://js.stripe.com/v3/');
            $GLOBALS['TL_CSS'][] = 'bundles/alpdeskisotopestripe/stripe.css|static';

            [$clientSecret, $clientSession] = $this->createOrder(
                'iso_order_' . $objOrder->getId(),
                $objOrder->getTotal(), // number_format($order->getTotal(), 2)
                $objOrder->getCurrency(),
                Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $objOrder, null, true),
                $referenceId
            );;

            $template = new Template('iso_payment_stripe');
            $template->setData($this->arrData);

            $template->clientSecret = $clientSecret;
            $template->clientSession = $clientSession;
            $template->stripePublicKey = $this->stripePublicKey;

            $processUrl = Checkout::generateUrlForStep(Checkout::STEP_PROCESS, $objOrder, null, true);
            $processUrl = Url::addQueryString('iso_clientSession=' . $clientSession, $processUrl);
            $template->processUrl = $processUrl;

            $template->cancel_url = Checkout::generateUrlForStep(Checkout::STEP_FAILED, null, null, true);

            return $template->parse();

        } catch (\Exception) {

            System::log('Stripe payment failed. See stripe.log for more information.', __METHOD__, TL_ERROR);
            Checkout::redirectToStep(Checkout::STEP_FAILED);

        }

    }


    public function processPayment(IsotopeProductCollection $objOrder, Module $objModule): bool
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {

            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable', __METHOD__, TL_ERROR);
            return false;

        }

        /*$paypalData = $this->retrievePayment($objOrder);

        if (0 === \count($paypalData)
            || Input::get('paymentID') !== $paypalData['id']
            || 'created' !== $paypalData['state']
        ) {
            return false;
        }

        try {
            $response = $this->executePayment($paypalData['id'], Input::get('payerID'));
        } catch (TransportExceptionInterface $e) {
            return false;
        }

        if (200 !== $response->getStatusCode()) {
            return false;
        }

        $objOrder->checkout();
        $objOrder->setDatePaid(time());
        $objOrder->updateOrderStatus($this->new_order_status);
        $objOrder->save();*/

        return true;

    }
}