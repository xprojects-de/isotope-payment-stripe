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

            $items = [];
            $discounts = [];

            $productName = '-';

            if ($this->stripeDetailView === '1' || $this->stripeDetailView === true) {

                foreach ($objOrder->getItems() as $item) {

                    $amountInt = (int)($item->getPrice() * 100);

                    $label = strip_tags($item->name);
                    if ($item->sku) {
                        $label .= ', ' . $item->sku;
                    }

                    $row = [
                        'price_data' => [
                            'currency' => $objOrder->getCurrency(),
                            'product_data' => [
                                'name' => $label,
                            ],
                            'unit_amount' => $amountInt,
                        ],
                        'quantity' => $item->quantity,
                    ];

                    $items[] = $row;

                }

                foreach ($objOrder->getSurcharges() as $surcharge) {

                    if (!$surcharge->addToTotal) {
                        continue;
                    }

                    $amountInt = (int)($surcharge->total_price * 100);
                    $label = strip_tags($surcharge->label);

                    if ($amountInt < 0 && $surcharge->type === 'rule') {

                        $discounts[] = [
                            'currency' => $objOrder->getCurrency(),
                            'duration' => 'once',
                            'amount_off' => $amountInt * -1,
                            'name' => $label
                        ];

                    } else {

                        $items[] = [
                            'price_data' => [
                                'currency' => $objOrder->getCurrency(),
                                'product_data' => [
                                    'name' => $label,
                                ],
                                'unit_amount' => $amountInt,
                            ],
                            'quantity' => 1
                        ];

                    }

                }

            } else {

                $productName = '#' . $this->generateHash($objOrder->getId());

                $items = [
                    [
                        'price_data' => [
                            'currency' => $objOrder->getCurrency(),
                            'product_data' => [
                                'name' => $productName,
                            ],
                            'unit_amount' => (int)($objOrder->getTotal() * 100),
                        ],
                        'quantity' => 1
                    ]
                ];

            }

            [$clientSecret, $clientSession] = $this->createOrder(
                Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $objOrder, null, true),
                $clientReferenceId,
                $enablePaymentMethodSave,
                $items,
                $discounts
            );

            $this->storePaymentData($objOrder, [
                'clientSession' => $clientSession,
                'clientReferenceId' => $clientReferenceId,
                'productName' => $productName
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

        } catch (\Exception $tr) {

            System::log('Stripe payment failed. ' . $tr->getMessage(), __METHOD__, TL_ERROR);
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

        $this->updateStripePaymentIdent($paymentIntent, $objOrder);

        return true;

    }

}