<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_iso_payment']['fields'] = array_merge($GLOBALS['TL_DCA']['tl_iso_payment']['fields'], [
    'stripePrivateKey' => [
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['stripePrivateKey'],
        'exclude' => true,
        'inputType' => 'text',
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'stripePublicKey' => [
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['stripePublicKey'],
        'exclude' => true,
        'inputType' => 'text',
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'stripeWhitelistStatus' => [
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['stripeWhitelistStatus'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'options' => [
            \Stripe\Checkout\Session::STATUS_EXPIRED,
            \Stripe\Checkout\Session::STATUS_OPEN
        ],
        'eval' => ['mandatory' => false, 'multiple' => true, 'tl_class' => 'clr'],
        'sql' => "blob NULL"
    ],
    'stripeWhitelistPaymentStatus' => [
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['stripeWhitelistPaymentStatus'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'options' => [
            \Stripe\Checkout\Session::PAYMENT_STATUS_UNPAID,
            \Stripe\Checkout\Session::PAYMENT_STATUS_NO_PAYMENT_REQUIRED
        ],
        'eval' => ['mandatory' => false, 'multiple' => true, 'tl_class' => 'clr'],
        'sql' => "blob NULL"
    ],
    'stripeDetailView' => [
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['stripeDetailView'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'eval' => array('tl_class' => 'w50 m12'),
        'sql' => "char(1) NOT NULL default ''"
    ]
]);

$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['stripe'] = $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['cash'];

PaletteManipulator::create()
    ->addLegend('gateway_legend', 'price_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('stripePrivateKey', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('stripePublicKey', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('stripeDetailView', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('stripeWhitelistStatus', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('stripeWhitelistPaymentStatus', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('stripe', 'tl_iso_payment');