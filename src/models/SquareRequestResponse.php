<?php

namespace craftplugins\square\models;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use Square\Exceptions\ApiException;
use Square\Models\CreatePaymentResponse;
use Square\Models\RefundPaymentResponse;

/**
 * Class SquareRequestResponse
 *
 * @package craftplugins\square\models
 * @deprecated
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
     * @return string
     */
    public function getCode(): string
    {
        if ($this->response instanceof ApiException) {
            return $this->response->getCode();
        }

        return '';
    }

    /**
     * @return mixed|\Square\Http\HttpResponse|null
     */
    public function getData()
    {
        if ($this->response instanceof ApiException) {
            return $this->response->getHttpResponse();
        }

        return $this->response;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        if ($this->response instanceof ApiException) {
            return $this->response->getMessage();
        }

        return '';
    }

    /**
     * @return array
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getTransactionReference(): string
    {
        if ($this->response instanceof CreatePaymentResponse) {
            return $this->response->getPayment()->getId();
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        if ($this->response instanceof RefundPaymentResponse) {
            return $this->response->getRefund()->getStatus() === 'PENDING';
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        if ($this->response instanceof CreatePaymentResponse) {
            return in_array($this->response->getPayment()->getStatus(), [
                'APPROVED',
                'COMPLETED',
            ]);
        }

        if ($this->response instanceof RefundPaymentResponse) {
            return in_array($this->response->getPayment()->getStatus(), [
                'PENDING',
                'COMPLETED',
            ]);
        }

        return false;
    }

    /**
     * @return mixed|void
     */
    public function redirect()
    {
        throw new NotImplementedException(
            'Redirecting directly is not implemented for this gateway.'
        );
    }
}
