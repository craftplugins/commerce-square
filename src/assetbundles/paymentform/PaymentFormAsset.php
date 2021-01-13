<?php

namespace augmentations\craft\commerce\square\assetbundles\paymentform;

use craft\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Class PaymentFormAsset
 *
 * @package augmentations\craft\commerce\square\assetbundles\paymentform
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
