<?php

namespace augmentations\craft\commerce\square\web\assets;

use craft\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Class PaymentFormAsset
 *
 * @package augmentations\craft\commerce\square\web\assets
 */
class PaymentFormAsset extends AssetBundle
{
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->js = ['js/paymentform.js'];

        $this->depends = [JqueryAsset::class];

        parent::init();
    }
}
