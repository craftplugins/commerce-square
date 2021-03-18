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
     * @var Error[]
     */
    public $errors;

    /**
     * SquareApiErrorException constructor.
     *
     * @param Error[] $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        
        parent::__construct($errors[0]->getDetail());
    }
}
