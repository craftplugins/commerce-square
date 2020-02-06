<?php

namespace craft\commerce\square;

use craft\commerce\services\Gateways;
use craft\commerce\square\gateways\Gateway;
use craft\commerce\square\services\Customers;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;

/**
 * Class Plugin
 *
 * @package craft\commerce\square
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
            'customers' => Customers::class,
        ]);

        // Register gateway
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );
    }

    /**
     * @return \craft\commerce\square\services\Customers
     * @throws \yii\base\InvalidConfigException
     */
    public function getCustomers(): Customers
    {
        /** @var Customers $customers */
        $customers = $this->get('customers');

        return $customers;
    }
}
