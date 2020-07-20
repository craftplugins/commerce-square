<?php

namespace craftplugins\square;

use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craftplugins\square\gateways\SquareGateway;
use craftplugins\square\services\SquareCustomers;
use yii\base\Event;

/**
 * Class Plugin
 *
 * @package craftplugins\square
 * @property \craftplugins\square\services\SquareCustomers $squareCustomers
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * @inheritDoc
     */
    public function init(): void
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
     * @return \craftplugins\square\services\SquareCustomers
     * @throws \yii\base\InvalidConfigException
     */
    public function getSquareCustomers(): SquareCustomers
    {
        /** @var SquareCustomers $customers */
        $customers = $this->get('customers');

        return $customers;
    }
}
