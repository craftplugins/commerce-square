<?php

namespace augmentations\craft\commerce\square\models;

use augmentations\craft\commerce\square\gateways\SquareGateway;
use augmentations\craft\commerce\square\records\SquareCustomerRecord as CustomerRecord;
use Craft;
use craft\base\Model;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;

/**
 * Class SquareCustomer
 *
 * @package augmentations\craft\commerce\square\models
 */
class SquareCustomer extends Model
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
     * @var string
     */
    public $response;

    /**
     * @var int
     */
    public $userId;

    /**
     * @var \augmentations\craft\commerce\square\gateways\SquareGateway
     */
    protected $gateway;

    /**
     * @var \craft\elements\User
     */
    protected $user;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->reference;
    }

    /**
     * @return \augmentations\craft\commerce\square\gateways\SquareGateway
     */
    public function getGateway(): SquareGateway
    {
        if ($this->gateway === null) {
            $this->gateway = Commerce::getInstance()
                ->getGateways()
                ->getGatewayById($this->gatewayId);
        }

        return $this->gateway;
    }

    /**
     * @return \craft\elements\User
     */
    public function getUser(): User
    {
        if ($this->user === null) {
            $this->user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->user;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                ['reference'],
                'unique',
                'targetAttribute' => ['gatewayId', 'reference'],
                'targetClass' => CustomerRecord::class,
            ],
            [['gatewayId', 'userId', 'reference', 'response'], 'required'],
        ];
    }
}
