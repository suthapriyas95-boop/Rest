<?php
 
/**
* Copyright © 2018 CyberSource. All rights reserved.
* See accompanying LICENSE.txt for applicable terms of use and license.
*/
 
declare(strict_types=1);
 
namespace CyberSource\Payment\Gateway\Http;
 
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use CyberSource\Payment\Service\SecurityUtility;
use Magento\Store\Model\StoreManagerInterface;
use CyberSource\Payment\Model\LoggerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use CyberSource\Payment\Model\Ui\ConfigProvider;
 
 
class StringTransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private $transferBuilder;
 
    private $securityUtility;
 
    private $config;
      /**
     * @var StoreManagerInterface
     */
    private $storeManager;
 
    private $logger;
 
     /**
     * @var Filesystem
     */
    private $filesystem;

    private $configProvider;

 
    /**
     * @param TransferBuilder $transferBuilder
     */
    public function __construct(
        TransferBuilder $transferBuilder,
        SecurityUtility $securityUtility,
        \CyberSource\Payment\Model\Config $config,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        Filesystem $filesystem,
        ConfigProvider $configProvider
 
    ) {
        $this->transferBuilder = $transferBuilder;
        $this->securityUtility = $securityUtility;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->configProvider=$configProvider;
    }
 
    /**
     * Builds HTTP transfer object.
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
{
    if($this->config->isMle()){
    // Get the "var/certificate/" directory path
    $certificateDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
    ->getAbsolutePath('certificate/');
    $certificateName = $this->configProvider->getP12Certificate();
    $certificateFilePath = $certificateDir . $certificateName;
    $keyPass = $this->config->getAccessKey();
    if (!empty($certificateFilePath) && !empty($keyPass)) {
        $result = $this->securityUtility->readP12Certificate($certificateFilePath, $keyPass);
    }
   else{
    $this->logger->debug('Null values for certificatePath or accessKey is not accepted.');
    throw new \Exception("Unable to process your request. Please contact merchant for further assistance..");
   }
    if (!$result) {
        throw new \Exception("Failed to read P12 certificate.");
    }
    
    $payloadData = json_encode($request);
    $storeId = $this->storeManager->getStore()->getId();
    $merchantId =$this->config->getMerchantId($storeId);
 
    $encryptedPayload = $this->securityUtility->encryptPayload($payloadData, $result['certificate'], ['v-c-merchant-id' => $merchantId]);
    if (!$encryptedPayload) {
        throw new \Exception("Payload encryption failed.");
    }
 
    $jwtToken = $this->securityUtility->generateJwtHeaderToken(
        $payloadData,
        $result['privateKey'],
        $result['certificate'],
        ['v-c-merchant-id' => $merchantId]
    );
    if (!$jwtToken) {
        throw new \Exception("JWT token generation failed.");
    }
 
    $headers = [
        'Authorization' => 'Bearer ' . $jwtToken,
        'Content-Type' => 'application/json',
        'X-URL-Params' => json_encode($request['url_params'] ?? [])
    ];
 
    $body = [
        'encryptedRequest' => $encryptedPayload
    ];  
    return $this->transferBuilder
            ->setBody($body)
            ->setHeaders($headers)    
            ->build();
}
else{
    $body=$request;
    return $this->transferBuilder
            ->setBody($body)
            ->build();
 
}         
    }
}
 