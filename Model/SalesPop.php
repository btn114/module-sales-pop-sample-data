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

namespace Mageplaza\SalesPopSampleData\Model;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Setup\SampleData\Context as SampleDataContext;
use Magento\Framework\Setup\SampleData\FixtureManager;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\SalesPop\Model\SalesPopFactory;

/**
 * Class SalesPop
 * @package Mageplaza\SalesPopSampleData\Model
 */
class SalesPop
{
    /**
     * @var FixtureManager
     */
    private $fixtureManager;

    /**
     * @var Csv
     */
    protected $csvReader;

    /**
     * @var File
     */
    private $file;
    /**
     * @var SalesPopFactory
     */
    private $salesPopFactory;

    protected $idMapFields = [];

    protected $viewDir = '';
    /**
     * @var Reader
     */
    private $moduleReader;
    /**
     * @var Filesystem\Io\File
     */
    private $ioFile;

    protected $mediaDirectory;
    /**
     * @var QuoteFactory
     */
    private $quote;
    /**
     * @var QuoteManagement
     */
    private $quoteManagement;
    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;
    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var ProductFactory
     */
    private $productFactory;
    /**
     * @var StockItemInterfaceFactory
     */
    private $stockItemInterfaceFactory;
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * SalesPop constructor.
     * @param SampleDataContext $sampleDataContext
     * @param File $file
     * @param Reader $moduleReader
     * @param Filesystem\Io\File $ioFile
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param QuoteFactory $quote
     * @param QuoteManagement $quoteManagement
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param DirectoryList $directoryList
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactory $productFactory
     * @param StockItemInterfaceFactory $stockItemInterfaceFactory
     * @param SalesPopFactory $salesPopFactory
     * @throws FileSystemException
     */
    public function __construct(
        SampleDataContext $sampleDataContext,
        File $file,
        Reader $moduleReader,
        Filesystem\Io\File $ioFile,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        QuoteFactory $quote,
        QuoteManagement $quoteManagement,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        DirectoryList $directoryList,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        StockItemInterfaceFactory $stockItemInterfaceFactory,
        SalesPopFactory $salesPopFactory
    ) {
        $this->fixtureManager = $sampleDataContext->getFixtureManager();
        $this->csvReader = $sampleDataContext->getCsvReader();
        $this->file = $file;
        $this->salesPopFactory = $salesPopFactory;
        $this->moduleReader = $moduleReader;
        $this->ioFile = $ioFile;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->_storeManager = $storeManager;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->stockItemInterfaceFactory = $stockItemInterfaceFactory;
        $this->directoryList = $directoryList;
    }

    /**
     * @param array $fixtures
     *
     * @throws Exception
     */
    public function install(array $fixtures)
    {
        $this->createNewSampleOrder();
        foreach ($fixtures as $fileName) {
            $fileName = $this->fixtureManager->getFixture($fileName);
            if (!$this->file->isExists($fileName)) {
                continue;
            }

            $rows = $this->csvReader->getData($fileName);

            $header = array_shift($rows);

            foreach ($rows as $row) {
                $data = [];
                foreach ($row as $key => $value) {
                    $data[$header[$key]] = $value;
                }

                $oldId = $data['pop_id'];
                $data = $this->prepareData($data);

                $salePop = $this->salesPopFactory->create()
                    ->addData($data)
                    ->save();

                $this->idMapFields[$oldId] = $salePop->getId();
            }
        }
    }

    /**
     * @param $data
     * @return mixed
     * @throws Exception
     */
    protected function prepareData($data)
    {
        unset($data['pop_id']);
        $parentId = $data['parent_id'];
        if ($parentId) {
            $data['parent_id'] = isset($this->idMapFields[$parentId]) ? $this->idMapFields[$parentId] : 0;
        }

        if ($data['background_image']) {
            $this->copyImage($data['background_image']);
        }

        if ($data['checkout_image']) {
            $this->copyImage($data['checkout_image']);
        }

        return $data;
    }

    /**
     * @param $path
     * @return string
     */
    protected function getFilePath($path)
    {
        if (!$this->viewDir) {
            $this->viewDir = $this->moduleReader->getModuleDir(
                Dir::MODULE_VIEW_DIR,
                'Mageplaza_SalesPopSampleData'
            );
        }

        return $this->viewDir . $path;
    }

    /**
     * @param $filePath
     * @return string
     * @throws Exception
     */
    protected function copyImage($filePath)
    {
        if (!$filePath) {
            return '';
        }
        $filePath = ltrim($filePath, '/');
        $pathInfo = $this->ioFile->getPathInfo($filePath);
        $fileName = $pathInfo['basename'];
        $dispersion = $pathInfo['dirname'];
        $file = $this->getFilePath('/files/image/' . $filePath);
        $this->ioFile->checkAndCreateFolder('pub/media/mageplaza/salespop/popup/image/' . $dispersion);
        $fileName = Uploader::getCorrectFileName($fileName);
        $fileName = Uploader::getNewFileName(
            $this->mediaDirectory->getAbsolutePath('mageplaza/salespop/popup/image/' . $dispersion . '/' . $fileName)
        );
        $destinationFile = $this->mediaDirectory->getAbsolutePath(
            'mageplaza/salespop/popup/image/' . $dispersion . '/' . $fileName
        );

        $destinationFilePath = $this->mediaDirectory->getAbsolutePath($destinationFile);
        $this->ioFile->cp($file, $destinationFilePath);

        return $fileName;
    }

    /**
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws LocalizedException
     */
    protected function createNewSampleOrder()
    {
        $product = $this->createNewSampleProduct();
        $orderData = [
            'currency_id' => $this->_storeManager->getStore()->getCurrentCurrencyCode(),
            'email' => 'salespopsample@mageplaza.com', //buyer email id
            'shipping_address' => [
                'firstname' => 'John', //address Details
                'lastname' => 'Doe',
                'street' => '123 Demo',
                'city' => 'Mageplaza',
                'country_id' => 'US',
                'region' => 'xxx',
                'region_id' => '33',
                'postcode' => '10019',
                'telephone' => '0123456789',
                'fax' => '32423',
                'save_in_address_book' => 1
            ],
        ];

        $store = $this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customer by email address
        if (!$customer->getEntityId()) {
            //If not avilable then create this customer
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($orderData['email']);
            $customer->save();
        }
        $quote = $this->quote->create(); //Create object of quote
        $quote->setStore($store); //set store for which you create quote
        // if you have already buyer id then you can load customer directly
        $customer = $this->customerRepository->getById($customer->getEntityId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer

        //add item in quote
        $quote->addProduct($product, 1);

        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->setInventoryProcessed(false); //not effect inventory
        $quote->save(); //Now Save quote and your quote is ready

        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => 'checkmo']);

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        $this->quoteManagement->submit($quote);
    }

    /**
     * @throws FileSystemException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    protected function createNewSampleProduct()
    {
        // check product is exists
        try {
            $product = $this->productRepository->get('mageplaza_sales_pop_sample_product');
        } catch (NoSuchEntityException $e) {
            $product = null;
        }

        // create new sample product if not exits
        if (!$product || !$product->getId()) {
            /** @var Product $product */
            $product = $this->productFactory->create();

        }

        $product->setTypeId('virtual')
            ->setAttributeSetId(4)
            ->setName('Mageplaza Sales Pop Sample Product')
            ->setSku('mageplaza_sales_pop_sample_product')
            ->setDescription('Description for product')
            ->setPrice(0)
            ->setQty(100)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED);
        $product = $this->setProductImage($product, 'https://picsum.photos/400');

        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockItemInterfaceFactory->create();
        $stockItem->setQty(100)
            ->setIsInStock(true);
        $extensionAttributes = $product->getExtensionAttributes();
        $extensionAttributes->setStockItem($stockItem);

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->productRepository;
        $productRepository->save($product);

        return $product;
    }

    /**
     * @param Product $product
     * @param $imageUrl
     * @param bool $visible
     * @param array $imageType
     * @return bool|string
     * @throws FileSystemException
     * @throws Exception
     */
    public function setProductImage(
        $product,
        $imageUrl,
        $visible = false,
        $imageType = ['image', 'small_image', 'thumbnail']
    ) {
        /** @var string $tmpDir */
        $tmpDir = $this->getMediaDirTmpDir();
        /** create folder if it is not exists */
        $this->ioFile->checkAndCreateFolder($tmpDir);
        $pathInfo = $this->ioFile->getPathInfo($imageUrl);
        $fileName = $pathInfo['basename'] . '.jpg';
        /** @var string $newFileName */
        $newFileName = $tmpDir . $fileName;
        /** read file from URL and copy it to the new destination */
        $result = $this->ioFile->read($imageUrl, $newFileName);
        if ($result) {
            /** add saved file to the $product gallery */
            $product->addImageToMediaGallery($newFileName, $imageType, true, $visible);
        }
        return $product;
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    protected function getMediaDirTmpDir()
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 'tmp';
    }
}
