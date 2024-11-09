<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_iso_payment'] = array_merge($GLOBALS['TL_LANG']['tl_iso_payment'], [
    'stripePrivateKey' => ['private Key', 'Insert the private Key'],
    'stripePublicKey' => ['public Key', 'Insert the public Key'],
    'stripeWhitelistStatus' => ['allow additional status', 'Select the status that are also considered valid. ("complete" always valid)'],
    'stripeWhitelistPaymentStatus' => ['allow additional payment status', 'Select the payment status that are also considered valid. ("paid" always valid)']
]);