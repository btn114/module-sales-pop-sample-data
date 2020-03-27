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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Setup\SampleData\Context as SampleDataContext;
use Magento\Framework\Setup\SampleData\FixtureManager;
use Magento\MediaStorage\Model\File\Uploader;

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
     * @var \Mageplaza\SalesPop\Model\SalesPopFactory
     */
    private $salesPopFactory;

    protected $idMapFields = [];

    protected $viewDir = '';
    /**
     * @var Reader
     */
    private $moduleReader;
    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    private $ioFile;

    protected $mediaDirectory;

    /**
     * SalesPop constructor.
     * @param SampleDataContext $sampleDataContext
     * @param File $file
     * @param Reader $moduleReader
     * @param Filesystem\Io\File $ioFile
     * @param Filesystem $filesystem
     * @param \Mageplaza\SalesPop\Model\SalesPopFactory $salesPopFactory
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        SampleDataContext $sampleDataContext,
        File $file,
        Reader $moduleReader,
        \Magento\Framework\Filesystem\Io\File $ioFile,
        Filesystem $filesystem,
        \Mageplaza\SalesPop\Model\SalesPopFactory $salesPopFactory
    ) {
        $this->fixtureManager = $sampleDataContext->getFixtureManager();
        $this->csvReader = $sampleDataContext->getCsvReader();
        $this->file = $file;
        $this->salesPopFactory = $salesPopFactory;
        $this->moduleReader = $moduleReader;
        $this->ioFile = $ioFile;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @param array $fixtures
     *
     * @throws Exception
     */
    public function install(array $fixtures)
    {
        foreach ($fixtures as $fileName) {
            $fileName = $this->fixtureManager->getFixture($fileName);
            if (!$this->file->isExists($fileName)) {
                continue;
            }

            $rows = $this->csvReader->getData($fileName);

//            var_dump($rows);
//            die;
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
}
