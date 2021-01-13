<?php

namespace augmentations\craft\commerce\square\errors;

use Square\Models\Error;

/**
 * Class SquareApiErrorException
 *
 * @package augmentations\craft\commerce\square\errors
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
