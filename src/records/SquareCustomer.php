<?php

namespace craft\commerce\square\records;

use craft\commerce\square\gateways\SquareGateway;
use craft\db\ActiveRecord;
use craft\elements\User;
use yii\db\ActiveQueryInterface;

/**
 * Class SquareCustomer
 *
 * @property \yii\db\ActiveQueryInterface $gateway
 * @property string                       $gatewayId
 * @property int                          $id
 * @property string                       $reference
 * @property string                       $response
 * @property \yii\db\ActiveQueryInterface $user
 * @property int                          $userId
 * @package craft\commerce\square\records
 */
class SquareCustomer extends ActiveRecord
{
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
