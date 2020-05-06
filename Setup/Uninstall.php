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
use Magento\Catalog\Model\ProductFactory;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Class Uninstall
 * @package Mageplaza\SalesPopSampleData\Setup
 */
class Uninstall implements UninstallInterface
{
    /**
     * @var CollectionFactory
     */
    private $configCollectionFactory;
    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;
    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var State
     */
    private $state;
    /**
     * @var CustomerCollectionFactory
     */
    private $customerCollectionFactory;
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * Uninstall constructor.
     * @param CollectionFactory $configCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param Registry $registry
     * @param State $state
     * @param ProductFactory $productFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        CollectionFactory $configCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        Registry $registry,
        State $state,
        ProductFactory $productFactory,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->configCollectionFactory = $configCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->registry = $registry;
        $this->state = $state;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->productFactory = $productFactory;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws Exception
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->configCollectionFactory->create()->addPathFilter('mpsalespop_popupData')->walk('delete');
        $connection = $setup->getConnection();

        $tables = ['mageplaza_sales_pop', 'mageplaza_salespop_actions_index'];
        foreach ($tables as $tableName) {
            $table = $setup->getTable($tableName);
            $connection->delete($table);
        }

        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        $this->registry->register('isSecureArea', true);
        $this->orderCollectionFactory->create()
            ->addFilter('customer_email', 'salespopsample@mageplaza.com')->walk('delete');
        $product = $this->productFactory->create()->loadByAttribute('sku', 'mageplaza_sales_pop_sample_product');
        if ($product) {
            $product->delete();
        }
        $this->customerCollectionFactory->create()->addFilter('email', 'salespopsample@mageplaza.com')->delete();
        $this->registry->unregister('isSecureArea');
    }
}
