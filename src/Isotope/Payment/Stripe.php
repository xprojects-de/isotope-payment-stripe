<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\Isotope\Payment;

use Isotope\Interfaces\IsotopePayment;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Payment\Postsale;
use Contao\Module;

class Stripe extends Postsale implements IsotopePayment
{

    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        // TODO: Implement processPostsale() method.
    }

    public function getPostsaleOrder()
    {
        // TODO: Implement getPostsaleOrder() method.
    }

    public function checkoutForm(IsotopeProductCollection $order, Module $module)
    {

    }

}