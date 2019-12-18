<?php

namespace craft\commerce\square;

use craft\commerce\services\Gateways;
use craft\commerce\square\gateways\Gateway;
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

        // Register gateway
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );
    }
}
