<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;


use Magento\Payment\Helper\Formatter;
use CyberSource\Payment\Helper\AbstractDataBuilder;
use CyberSource\Payment\Gateway\Request\Rest\GenerateCaptureContextRequest;


class AddressDataBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    use Formatter;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    private $helper;



    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        GenerateCaptureContextRequest $helper
    ) {
        $this->subjectReader = $subjectReader;
        $this->helper=$helper;
    }

    /**
     * Builds Address Data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $request = [];

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        $request['orderInformation']['billTo'] = $this->buildAddress($order->getBillingAddress());
        $request['orderInformation']['billTo']['company']['name'] = $order->getBillingAddress()->getCompany();
        if(($order->getBillingAddress()->getCompany()=='')){
            $request['orderInformation']['billTo']['company']['name']="na";
        }

        if ($order->getShippingAddress()) {
            $request['orderInformation']['shipTo'] = $this->buildAddress($order->getShippingAddress());
            $request['orderInformation']['shipTo']['company'] = $order->getShippingAddress()->getCompany();
            if($order->getShippingAddress()->getCompany()==''){
                $request['orderInformation']['shipTo']['company']="na";
            }
        }
        $request['orderInformation']['shippTo']['email'] = $order->getBillingAddress()->getEmail();
        $request['orderInformation']['billTo']['email'] = $order->getBillingAddress()->getEmail();      
        $countries=$this->helper->getAllowedCountries();
        if(!in_array($order->getShippingAddress()->getCountryId(),$countries)){
            throw new \Magento\Framework\Exception\LocalizedException(
                __(argc: 'Shipping to this Country is not allowed')
            );       
         }
 
        return $request;
    }

    /**
     * Builds Address Data
     *
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Address $address
     * @return array
     */
    private function buildAddress($address)
    {
        $addressFields =
        [
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'email' => $address->getEmail(),
            'locality' => $address->getCity(),
            'administrativeArea' => $address->getRegionCode(),
            'country' => $address->getCountryId(),
            'phoneNumber' => $address->getTelephone(),
            'postalCode' => $address->getPostcode(),
        ];

        if ($address instanceof \Magento\Payment\Gateway\Data\AddressAdapterInterface) {
            $addressFields['address1'] = $address->getStreetLine1();
            $addressFields['address2'] = $address->getStreetLine2();
        } else {
            $addressFields['address1'] = $address->getStreetLine(1);
            $addressFields['address2'] = $address->getStreetLine(2);
        }
        return $addressFields;
    }
}
