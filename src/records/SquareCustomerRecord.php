<?php

namespace craftplugins\square\records;

use craft\db\ActiveRecord;
use craft\elements\User;
use craftplugins\square\gateways\SquareGateway;
use yii\db\ActiveQueryInterface;

/**
 * Class SquareCustomerRecord
 *
 * @package craftplugins\square\records
 * @property \yii\db\ActiveQueryInterface $user
 * @property \yii\db\ActiveQueryInterface $gateway
 */
class SquareCustomerRecord extends ActiveRecord
{
    /**
     * @var string
     */
    public $gatewayId;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $reference;

    /**
     * @var mixed
     */
    public $response;

    /**
     * @var string
     */
    public $userId;

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%square_customers}}';
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(SquareGateway::class, ['gatewayId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
