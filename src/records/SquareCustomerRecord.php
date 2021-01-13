<?php

namespace augmentations\craft\commerce\square\records;

use augmentations\craft\commerce\square\gateways\SquareGateway;
use craft\db\ActiveRecord;
use craft\elements\User;
use yii\db\ActiveQueryInterface;

/**
 * Class SquareCustomerRecord
 *
 * @package augmentations\craft\commerce\square\records
 * @property string                       $gatewayId
 * @property int                          $id
 * @property string                       $reference
 * @property mixed                        $response
 * @property string                       $userId
 * @property \yii\db\ActiveQueryInterface $user
 * @property \yii\db\ActiveQueryInterface $gateway
 */
class SquareCustomerRecord extends ActiveRecord
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
