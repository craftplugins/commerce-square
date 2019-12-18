<?php

namespace craft\commerce\square\web\assets;

use craft\web\AssetBundle;

/**
 * Class PaymentFormAsset
 *
 * @package craft\commerce\square\web\assets
 */
class PaymentFormAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__;

        // $this->css = [];

        $this->js = [
            'js/paymentform.js',
        ];

        parent::init();
    }
}
