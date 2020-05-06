<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_SalesPopSampleData
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SalesPopSampleData\Setup;

use Exception;
use Magento\Framework\Setup;
use Mageplaza\SalesPopSampleData\Model\SalesPop;

/**
 * Class Installer
 * @package Mageplaza\SalesPopSampleData\Setup
 */
class Installer implements Setup\SampleData\InstallerInterface
{
    /**
     * @var SalesPop
     */
    private $salesPop;

    /**
     * Installer constructor.
     *
     * @param SalesPop $salesPop
     */
    public function __construct(
        SalesPop $salesPop
    ) {
        $this->salesPop = $salesPop;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function install()
    {
        $this->salesPop->install(['Mageplaza_SalesPopSampleData::fixtures/mageplaza_sales_pop.csv']);
    }
}
