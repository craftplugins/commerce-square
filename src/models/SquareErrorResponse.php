<?php

namespace craftplugins\square\models;

use Square\Models\Error;

/**
 * Class SquareErrorResponse
 *
 * @package craftplugins\square\models
 */
class SquareErrorResponse extends AbstractSquareResponse
{
    /**
     * @var Error[]
     */
    protected $errors;

    /**
     * SquareErrorResponse constructor.
     *
     * @param \Square\Models\Error[] $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->errors[0]->getCode();
    }

    /**
     * @return mixed|string
     */
    public function getData()
    {
        return $this->errors;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->errors[0]->getDetail();
    }

    /**
     * @return string
     */
    public function getTransactionReference(): string
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return false;
    }
}
