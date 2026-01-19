<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Plugin\Checkout\Model;

class GuestPaymentInformationManagementInterfacePlugin
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var \Magento\Quote\Model\GuestCart\GuestCartRepository
     */
    private $guestCartRepository;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Model\GuestCart\GuestCartRepository $guestCartRepository
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Quote\Model\GuestCart\GuestCartRepository $guestCartRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->guestCartRepository = $guestCartRepository;
    }

    /**
     * Plugin to save payment information and place order for guest checkout.
     *
     * @param \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject
     * @param callable $proceed
     * @param int $cartId
     * @param string $email
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @return mixed
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject,
        callable $proceed,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        try {
            return $proceed($cartId, $email, $paymentMethod, $billingAddress);
        } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
            if (!$e->getPrevious() instanceof \CyberSource\Payment\Gateway\PaEnrolledException) {
                throw $e;
            }

            $quote = $this->guestCartRepository->get($cartId);

            $exceptionData = $e->getPrevious()->getDetails();
            $processorTransactionId = $exceptionData['order']['OrderDetails']['TransactionId'] ?? null;

            $quote->getPayment()->setAdditionalInformation(
                \CyberSource\Payment\Gateway\Validator\PaEnrolledValidator::KEY_PAYER_AUTH_ENROLL_TRANSACTION_ID,
                $processorTransactionId
            );
            $this->cartRepository->save($quote);
            throw $e;
        }
    }
}
