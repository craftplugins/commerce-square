<?php

namespace craftplugins\square\errors;

use Square\Models\Error;

/**
 * Class SquareApiErrorException
 *
 * @package craftplugins\square\errors
 */
class SquareApiErrorException extends SquareException
{
    /**
     * SquareApiErrorException constructor.
     *
     * @param Error[] $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct(
            "{$errors[0]->getCode()}: {$errors[0]->getDetail()}"
        );
    }
}
