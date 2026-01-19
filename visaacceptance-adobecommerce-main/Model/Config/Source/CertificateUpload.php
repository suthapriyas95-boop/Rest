<?php
namespace CyberSource\Payment\Model\Config\Source;

use Magento\Config\Model\Config\Backend\File;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use CyberSource\Payment\Model\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CertificateUpload extends File
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param RequestDataInterface $requestData
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        RequestDataInterface $requestData,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection,
            $data
        );
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->_scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Save uploaded file before saving config value
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $file = $this->getFileData();

        if ((isset($value['name']) && isset($file['name'])) || isset($value['delete'])) {
            $deleteFlag = is_array($value) && !empty($value['delete']);

            if (!empty($file)) {
                $uploadDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath('certificate/');

                // Create the directory using Magento's filesystem utility
                $directoryWrite = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

                if (!$directoryWrite->isExist('certificate/')) {
                    try {
                        $directoryWrite->create('certificate');
                    } catch (\Exception $e) {
                        $this->logger->error('Error creating certificate directory: ' . $e->getMessage());
                    }
                }

                // Delete the old file if it exists and delete checkbox is checked
                if ($deleteFlag) {
                    $existingFilePath = $this->getOldCertificatePath();
                    if ($existingFilePath && file_exists($existingFilePath)) {
                        unlink($existingFilePath);
                    }
                    $this->setValue('');
                }

                if (!empty($value)) {
                    try {
                        // Clean the file name before saving
                        $fileName = $value['name'];
                        // Remove everything after ".p12"
                        $pos = strpos($fileName, '.p12');
                        if ($pos !== false) {
                            // Truncate the string after ".p12"
                            $cleanedFileName = substr($fileName, 0, $pos + 4); // +4 to include ".p12"
                        } else {
                            $cleanedFileName = $fileName;
                        }
                        // Append field ID to the file name to make it unique
                        // $uniqueFileName = $this->getFieldId() . '_' . $cleanedFileName;
                        $uniqueFileName = $cleanedFileName;
                        // Set the cleaned file name for the uploader
                        $file['name'] = $uniqueFileName;
                        // Validate file extension
                        $fileExtension = strtolower(pathinfo($uniqueFileName, PATHINFO_EXTENSION));
                        if ($fileExtension !== 'p12') {
                            $this->logger->error('Disallowed file extension: ' . $fileExtension);
                            throw new LocalizedException(__('Disallowed file type.'));
                        }

                        // Create uploader and save the file
                        $uploader = $this->uploaderFactory->create(['fileId' => $file]);
                        $uploader->setAllowedExtensions(['p12']);
                        $uploader->setAllowRenameFiles(false);

                        // Save the file and get the result
                        $result = $uploader->save($uploadDir);

                        // Log successful upload result
                        if ($result !== false) {
                            $filename = $result['file'];

                            // Set the clean file name for the config value
                            $this->setValue($filename);
                            $this->logger->info('File successfully uploaded: ' . $filename);
                        }

                    } catch (\Exception $e) {
                        $this->logger->error('Error uploading file: ' . $e->getMessage());
                        throw new LocalizedException(__('%1', $e->getMessage()));
                    }
                }
            } else {
                // Log file deletion or clearing value
                if (is_array($value) && !empty($value['delete'])) {
                    $existingFilePath = $this->getOldCertificatePath();
                    if ($existingFilePath && file_exists($existingFilePath)) {
                        unlink($existingFilePath);
                        $this->setValue('');
                    }
                } elseif (is_array($value) && !empty($value['value'])) {
                    // Ensure 'value' is a string and not an array
                    $filePath = is_array($value['value']) ? implode(',', $value['value']) : $value['value'];
                    $this->setValue($filePath);
                } else {
                    $this->setValue('');
                }
            }
        } else {
            $this->setValue($this->getConfigValue($this->getConfigPath()));
        }

        return $this;
    }

    /**
     * Retrieve old certificate file path
     *
     * @return string|null
     */
    public function getOldCertificatePath()
    {
        $configValue = $this->getConfigValue($this->getConfigPath());
        if ($configValue) {
            $uploadDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath('certificate/');
            return $uploadDir . $configValue;
        }
        return null;
    }

    /**
     * Retrieve config value by path
     *
     * @param string $path
     * @return string|null
     */
    protected function getConfigValue($path)
    {
        return $this->_scopeConfig->getValue($path);
    }

    /**
     * Retrieve field ID
     *
     * @return string|null
     */
    protected function getFieldId()
    {
        return $this->getData('field_config/id');
    }

    /**
     * Retrieve the configuration path
     *
     * @return string|null
     */
    protected function getConfigPath()
    {
        return $this->getData('field_config/config_path');
    }

    /**
     * Retrieve upload directory path
     *
     * @return string
     */
    protected function _getUploadDir()
    {
        return 'certificate/';
    }

    /**
     * Getter for allowed extensions of uploaded files
     *
     * @return array
     */
    protected function _getAllowedExtensions()
    {
        return ['p12', 'P12'];
    }
}