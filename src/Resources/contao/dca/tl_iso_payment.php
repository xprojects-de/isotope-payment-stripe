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
    ]
]);

$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['stripe'] = $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['cash'];

PaletteManipulator::create()
    ->addLegend('gateway_legend', 'price_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('stripePrivateKey', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('stripePublicKey', 'gateway_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('stripe', 'tl_iso_payment');