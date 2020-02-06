<?php

namespace craft\commerce\square\models;

use craft\commerce\base\Model;
use craft\commerce\square\records\Customer as CustomerRecord;

/**
 * Class Customer
 *
 * @package craft\commerce\square\models
 */
class SquareCustomer extends Model
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

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['reference'], 'unique', 'targetAttribute' => ['gatewayId', 'reference'], 'targetClass' => CustomerRecord::class],
            [['gatewayId', 'userId', 'reference', 'response'], 'required'],
        ];
    }
}
