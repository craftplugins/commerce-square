<?php

namespace craft\commerce\square;

use craft\commerce\services\Gateways;
use craft\commerce\square\gateways\SquareGateway;
use craft\commerce\square\services\SquareCustomers;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;

/**
 * Class Plugin
 *
 * @package craft\commerce\square
 * @property \craft\commerce\square\services\SquareCustomers $squareCustomers
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->setComponents([
            'customers' => SquareCustomers::class,
        ]);

        // Register gateway
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = SquareGateway::class;
            }
        );
    }

    /**
     * @return \craft\commerce\square\services\SquareCustomers
     * @throws \yii\base\InvalidConfigException
     */
    public function getSquareCustomers(): SquareCustomers
    {
        /** @var SquareCustomers $customers */
        $customers = $this->get('customers');

        return $customers;
    }
}
