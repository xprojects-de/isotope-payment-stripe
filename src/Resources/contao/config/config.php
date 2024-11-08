<?php

declare(strict_types=1);

use Alpdesk\IsotopeStripe\Isotope\Payment\Stripe;
use Isotope\Model\Payment;

Payment::registerModelType('stripe', Stripe::class);