<?php

namespace augmentations\craft\commerce\square;

use augmentations\craft\commerce\square\gateways\SquareGateway;
use augmentations\craft\commerce\square\services\SquareCustomers;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;

/**
 * Class Plugin
 *
 * @package augmentations\craft\commerce\square
 * @property-read \augmentations\craft\commerce\square\services\SquareCustomers $squareCustomers
 */
class Plugin extends \craft\base\Plugin
{
    /**
     * @var self
     */
    public static $instance;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        self::$instance = $this;

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
     * @return \augmentations\craft\commerce\square\services\SquareCustomers
     * @throws \yii\base\InvalidConfigException
     */
    public function getSquareCustomers(): SquareCustomers
    {
        /** @var SquareCustomers $customers */
        $customers = $this->get('customers');

        return $customers;
    }
}
