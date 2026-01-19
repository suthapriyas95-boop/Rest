<?php

namespace CyberSource\Payment\Service;

use Exception;
use Magento\Framework\Filesystem\Driver\File;
use CyberSource\Payment\Model\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Core\AlgorithmManager as SignatureAlgorithmManager;
use phpseclib3\Crypt\PublicKeyLoader;


class SecurityUtility
{
    protected $fileDriver;
    protected $logger;
    private $messageManager;

    public function __construct(
        File $fileDriver,
        LoggerInterface $logger,
        ManagerInterface $messageManager
    ) {
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
    }

    public function readP12Certificate($certificateFilePath, $keyPass)
    {
        $certificateInfo = pathinfo($certificateFilePath);
        $certificate = $this->fileDriver->fileGetContents($certificateFilePath);
        if (in_array(strtolower($certificateInfo['extension']), ['p12', 'pfx'])) {
            try {
                if (openssl_pkcs12_read($certificate, $certs, $keyPass)) {
                    $x509Certificate = $certs['cert'] ?? '';
                    $privateKey = $certs['pkey'] ?? '';
                    if (empty(trim($privateKey))) {
                     throw new \RuntimeException('No private key provided.');
                    }
                    try {
                      $private = PublicKeyLoader::load($privateKey);
                 try {
                      $privateKeyString = $private->toString('PKCS1'); 
                } catch (\Throwable $e) {
                    $privateKeyString = $private->toString('PKCS8');
                 }
          } catch (\Throwable $e) {
    throw new \RuntimeException('Failed to load private key: ' . $e->getMessage());
}
                
                    $cyberSourceCertificate = null;
                    $serialNumber = null;
                    $publicKeyString = null;

                    // Iterate through the certificates
                    foreach ($certs['extracerts'] as $cert) {
                        $certData = openssl_x509_parse($cert);
                        $alias = $certData['subject']['CN'] ?? null;
                        if ($alias == 'CyberSource_SJC_US') {
                            $cyberSourceCertificate = $cert;
                            $serialNumber = $certData['subject']['serialNumber'] ?? null;
                            $publicKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($cert));
                            $publicKeyString = $publicKeyDetails['key'] ?? null;
                            $publicKey = $publicKeyString;
                            break;
                        }
                    }

                    if ($cyberSourceCertificate === null) {
                        throw new LocalizedException(__('CyberSource_SJC_US certificate not found.'));
                    }
                   
                    return [
                        'privateKey' => $privateKey,
                        'privateKeyString' => $privateKeyString,
                        'publicKey' => $publicKeyString,
                        'certificate' => $cyberSourceCertificate,
                        'serialNumber' => $serialNumber
                    ];
                } else {
                    $this->logger->info('Incorrect P12 Accesskey or Invalid Certificate.');
                    throw new LocalizedException(__('Unable to process your request. Please contact merchant for further assistance.'));
                }
            } catch (Exception $e) {
                throw new LocalizedException(__($e->getMessage()));
            }
        } else {
            throw new LocalizedException(__('Invalid certificate file extension.'));
        }
    }
    public function encryptPayload($content, $x509Certificate, $customHeaders)
    {
        if (empty($content)) {
            $this->logger->info('Empty or null content');
            return null;
        } elseif ($x509Certificate == null) {
            $this->logger->info('Public certificate is null');
            return null;
        }
    
        $serialNumber = $this->extractSerialNumberFromDN($x509Certificate);
        $jwk = JWKFactory::createFromCertificate($x509Certificate, [
            'kid' => $serialNumber
        ]);
    
        $keyEncryptionAlgorithmManager = new AlgorithmManager([new RSAOAEP256()]);
        $contentEncryptionAlgorithmManager = new AlgorithmManager([new A256GCM()]);
        $compressionMethodManager = new CompressionMethodManager([]);
        $jweBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );
    
        $jwe = $jweBuilder
            ->create()
            ->withPayload($content)
            ->withSharedProtectedHeader(array_merge([
                'alg' => 'RSA-OAEP-256',
                'enc' => 'A256GCM',
                'iat' => time(),
                'kid'=> $serialNumber,

            ]))
            ->addRecipient($jwk)
            ->build();
    
        $serializer = new CompactSerializer();
        $jweToken = $serializer->serialize($jwe);
        return $jweToken;
       }
    private function extractSerialNumberFromDN($x509Certificate)
    {
        $subject = openssl_x509_parse($x509Certificate)['subject'];
        return $subject['serialNumber'] ?? '';
    }
    public static function createJsonString($jweToken) {
        $json = array("encryptedRequest" => $jweToken);
        return $json;
    }

//header signature creation to pass along with encrypted request
public function generateJwtHeaderToken(string $jsonBody, $privateKey, $x509Certificate, array $customHeaders = []): ?string
{
    if (empty($jsonBody) || !$privateKey || !$x509Certificate) {
        $this->logger->info('Missing input for JWT generation.');
        return null;
    }

    $digest = base64_encode(hash('sha256', $jsonBody, true));

    $payload = json_encode([
        'digest' => $digest,
        'digestAlgorithm' => 'SHA-256',
        'iat' => time()
    ]);

    if (empty(trim($privateKey))) {
        throw new \RuntimeException('No private key provided.');
    }
    try {
        $private = PublicKeyLoader::load($privateKey);
    
        try {
            $privateKeyString = $private->toString('PKCS1'); // PEM string
        } catch (\Throwable $e) {
            $privateKeyString = $private->toString('PKCS8');
        }
    } catch (\Throwable $e) {
        throw new \RuntimeException('Failed to load private key: ' . $e->getMessage());
    }

    $jwk = JWKFactory::createFromKey($privateKeyString, null, [
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $this->extractSerialNumberFromDN($x509Certificate)
    ]);

    $jwsBuilder = new JWSBuilder(signatureAlgorithmManager: new SignatureAlgorithmManager([new RS256()]));
    $jws = $jwsBuilder
        ->create()
        ->withPayload($payload)
        ->addSignature($jwk, array_merge([
            'alg' => 'RS256',
            'kid' => $this->extractSerialNumberFromDN($x509Certificate),
            'x5c' => [base64_encode($x509Certificate)]
        ], $customHeaders))
        ->build();

    $serializer = new JwsCompactSerializer();
    return $serializer->serialize($jws, 0);
}

}