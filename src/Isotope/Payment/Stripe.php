<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Contao\MemberModel;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
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

        try {

            $enablePaymentMethodSave = false;
            $clientReferenceId = 'iso_address_' . $objOrder->getBillingAddress()->id;

            $memberObject = $objOrder->getMember();
            if ($memberObject instanceof MemberModel) {

                $clientReferenceId = 'iso_member_' . $memberObject->id;
                $enablePaymentMethodSave = true;

            }

            [$clientSecret, $clientSession] = $this->createOrder(
                '#' . $this->generateHash($objOrder->getId()),
                $objOrder->getTotal(), // number_format($order->getTotal(), 2)
                $objOrder->getCurrency(),
                Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $objOrder, null, true),
                $clientReferenceId,
                $enablePaymentMethodSave
            );

            $this->storePaymentData($objOrder, [
                'clientSession' => $clientSession,
                'clientReferenceId' => $clientReferenceId
            ]);

            $template = new Template('iso_payment_stripe');
            $template->setData($this->arrData);

            $template->stripeJsUrl = StringUtil::decodeEntities(StripeApi::$STRIPE_JS);
            $template->clientSecret = $clientSecret;
            $template->clientSession = $clientSession;
            $template->stripePublicKey = $this->stripePublicKey;
            $template->completeUrl = Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $objOrder, null, true);
            $template->cancelUrl = Checkout::generateUrlForStep(Checkout::STEP_FAILED, null, null, true);
            $template->labelBack = $GLOBALS['TL_LANG']['MSC']['stripe_isotope']['back'];

            return $template->parse();

        } catch (\Exception) {

            System::log('Stripe payment failed. See stripe.log for more information.', __METHOD__, TL_ERROR);
            Checkout::redirectToStep(Checkout::STEP_FAILED);

        }

    }

    /**
     * @param IsotopeProductCollection $objOrder
     * @param Module $objModule
     * @return bool
     */
    public function processPayment(IsotopeProductCollection $objOrder, Module $objModule): bool
    {
        if (!$objOrder instanceof IsotopePurchasableCollection) {

            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable', __METHOD__, TL_ERROR);
            return false;

        }

        $paymentData = $this->retrievePaymentData($objOrder);

        if (
            !array_key_exists('clientSession', $paymentData) ||
            !is_string($paymentData['clientSession']) || $paymentData['clientSession'] === ''
        ) {

            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable because of invalid Stripe Session', __METHOD__, TL_ERROR);
            return false;

        }

        $clientSession = $paymentData['clientSession'];
        $clientReferenceId = ($paymentData['clientReferenceId'] ?? null);

        $paymentIntent = $this->captureOrder($clientSession, $clientReferenceId, $objOrder);
        if (!is_string($paymentIntent) || $paymentIntent === '') {

            $this->storePaymentData($objOrder, []);
            System::log('Product collection ID "' . $objOrder->getId() . '" is not purchasable because of invalid Capture Order', __METHOD__, TL_ERROR);

            return false;

        }

        $paymentData['paymentIntent'] = $paymentIntent;
        $this->storePaymentData($objOrder, $paymentData);

        $objOrder->checkout();
        $objOrder->setDatePaid(time());
        $objOrder->updateOrderStatus($this->new_order_status);

        $objOrder->save();

        return true;

    }

}