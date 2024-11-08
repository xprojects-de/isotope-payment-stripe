<?php

declare(strict_types=1);

namespace Alpdesk\IsotopeStripe\ContaoManager;

use Alpdesk\IsotopeStripe\AlpdeskIsotopeStripeBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;

class Plugin implements BundlePluginInterface
{
    /**
     * @param ParserInterface $parser
     * @return array
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [BundleConfig::create(AlpdeskIsotopeStripeBundle::class)->setLoadAfter([ContaoCoreBundle::class, 'isotope'])];
    }

}
