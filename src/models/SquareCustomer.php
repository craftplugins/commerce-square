<?php

namespace craft\commerce\square\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin as Commerce;
use craft\commerce\square\gateways\SquareGateway;
use craft\commerce\square\records\SquareCustomer as CustomerRecord;
use craft\elements\User;

/**
 * Class SquareCustomer
 *
 * @package craft\commerce\square\models
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
     * @var \craft\commerce\square\gateways\SquareGateway
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
     * @return \craft\commerce\square\gateways\SquareGateway
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
