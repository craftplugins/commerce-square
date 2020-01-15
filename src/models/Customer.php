<?php


namespace craft\commerce\square\models;

use craft\base\Model;

/**
 * Class Customer
 *
 * @package craft\commerce\square\models
 */
class Customer extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $userId;

    /**
     * @var string
     */
    public $gatewayId;

    /**
     * @var string
     */
    public $reference;

    /**
     * @var string
     */
    public $response;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->reference;
    }
}
