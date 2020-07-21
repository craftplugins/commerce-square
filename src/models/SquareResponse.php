<?php

namespace craftplugins\square\models;

use Square\Models\CompletePaymentResponse;
use Square\Models\CreatePaymentResponse;
use Square\Models\RefundPaymentResponse;

/**
 * Class SquareResponse
 *
 * @package craftplugins\square\models
 */
class SquareResponse extends AbstractSquareResponse
{
    /**
     * @var mixed
     */
    protected $result;

    /**
     * SquareResponse constructor.
     *
     * @param $result
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return '';
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->result;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        if (
            $this->result instanceof CreatePaymentResponse ||
            $this->result instanceof CompletePaymentResponse
        ) {
            return $this->result->getPayment()->getStatus();
        }

        if ($this->result instanceof RefundPaymentResponse) {
            return $this->result->getRefund()->getStatus();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getTransactionReference(): string
    {
        if (
            $this->result instanceof CreatePaymentResponse ||
            $this->result instanceof CompletePaymentResponse
        ) {
            return $this->result->getPayment()->getId();
        }

        if ($this->result instanceof RefundPaymentResponse) {
            return $this->result->getRefund()->getId();
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        if ($this->result instanceof RefundPaymentResponse) {
            return $this->result->getRefund()->getStatus() === 'PENDING';
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        if (
            $this->result instanceof CreatePaymentResponse ||
            $this->result instanceof CompletePaymentResponse
        ) {
            return in_array($this->result->getPayment()->getStatus(), [
                'APPROVED',
                'COMPLETED',
            ]);
        }

        if ($this->result instanceof RefundPaymentResponse) {
            return in_array($this->result->getRefund()->getStatus(), [
                'PENDING',
                'COMPLETED',
            ]);
        }

        return false;
    }
}
