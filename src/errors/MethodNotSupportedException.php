<?php

namespace craftplugins\square\errors;

/**
 * Class MethodNotSupportedException
 *
 * @package craftplugins\square\errors
 */
class MethodNotSupportedException extends SquareException
{
    /**
     * @var string
     */
    protected $message = 'Method is not supported';
}
