<?php

namespace craft\commerce\square\records;

use craft\commerce\square\gateways\Gateway;
use craft\db\ActiveRecord;
use craft\elements\User;
use yii\db\ActiveQueryInterface;

/**
 * Class Customer
 *
 * @property int    $id
 * @property int    $userId
 * @property string $gatewayId
 * @property string $reference
 * @property string $response
 * @package craft\commerce\square\records
 */
class Customer extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%square_customers}}';
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['gatewayId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
