<?php

namespace craft\commerce\square\models;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use SquareConnect\ApiException;
use SquareConnect\Model\CreatePaymentResponse;
use SquareConnect\Model\RefundPaymentResponse;
use SquareConnect\ObjectSerializer;

/**
 * Class SquareRequestResponse
 *
 * @package craft\commerce\square\models
 */
class SquareRequestResponse implements RequestResponseInterface
{
    /**
     * @var mixed
     */
    protected $response;

    /**
     * SquareRequestResponse constructor.
     *
     * @param $response
     */
    public function __construct($response)
    {
        $this->response = $response;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        if ($this->response instanceof ApiException) {
            return $this->response->getCode();
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        if ($this->response instanceof ApiException) {
            return ObjectSerializer::sanitizeForSerialization($this->response->getResponseObject());
        }

        return ObjectSerializer::sanitizeForSerialization($this->response);
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        if ($this->response instanceof ApiException) {
            return $this->response->getMessage();
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getTransactionReference(): string
    {
        if ($this->response instanceof CreatePaymentResponse) {
            return $this->response->getPayment()->getId();
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function isProcessing(): bool
    {
        if ($this->response instanceof RefundPaymentResponse) {
            return $this->response->getPayment()->getStatus() === 'PENDING';
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isSuccessful(): bool
    {
        if ($this->response instanceof CreatePaymentResponse) {
            return in_array($this->response->getPayment()->getStatus(), ['APPROVED', 'COMPLETED']);
        }

        if ($this->response instanceof RefundPaymentResponse) {
            return in_array($this->response->getPayment()->getStatus(), ['PENDING', 'COMPLETED']);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function redirect()
    {
        throw new NotImplementedException('Redirecting directly is not implemented for this gateway.');
    }
}
