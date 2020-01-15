<?php

namespace craft\commerce\square\models;

use craft\commerce\models\payments\BasePaymentForm;

/**
 * Class SquarePaymentForm
 *
 * @package craft\commerce\square\models
 */
class SquarePaymentForm extends BasePaymentForm
{
    /**
     * @var string
     */
    public $cardholderName;

    /**
     * @var string
     */
    public $cardNonce;

    /**
     * @var string
     */
    public $verificationToken;
}
