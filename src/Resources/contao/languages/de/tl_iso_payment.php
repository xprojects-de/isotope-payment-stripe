<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_iso_payment'] = array_merge($GLOBALS['TL_LANG']['tl_iso_payment'], [
    'stripePrivateKey' => ['privater Schlüssel', 'Geben sie den privaten Schlüssel ein'],
    'stripePublicKey' => ['öffentlicher Schlüssel', 'Geben sie den öffentlichen Schlüssel ein'],
    'stripeDetailView' => ['detaillierte Produktansicht', 'Wählen sie diese Option wenn die Produkte einzeln aufgelistet werden sollen'],
    'stripeWhitelistStatus' => ['weitere Status erlauben', 'Status, welche zusätzlich als valide gelten. ("complete" immer erlaubt)'],
    'stripeWhitelistPaymentStatus' => ['weitere Payment-Status erlauben', 'Payment-Status, welche zusätzlich als valide gelten. ("paid" immer erlaubt)']
]);