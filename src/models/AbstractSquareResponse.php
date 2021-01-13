<?php

namespace augmentations\craft\commerce\square\models;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;

/**
 * Class AbstractSquareResponse
 *
 * @package augmentations\craft\commerce\square\models
 */
abstract class AbstractSquareResponse implements RequestResponseInterface
{
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
     * @return bool
     */
    public function isRedirect(): bool
    {
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
