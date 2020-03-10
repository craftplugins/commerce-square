<?php

namespace craft\commerce\square\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\square\Plugin;

/**
 * Class SquarePaymentForm
 *
 * @package craft\commerce\square\models
 */
class SquarePaymentForm extends CreditCardPaymentForm
{
    /**
     * @var string
     */
    public $customerReference;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $verificationToken;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [[['token'], 'required']];
    }

    /**
     * @return string|null
     */
    public function getCardholderName()
    {
        $firstName = trim($this->firstName);
        $lastName = trim($this->lastName);

        if (!$firstName && !$lastName) {
            return null;
        }

        $name = $firstName;

        if ($firstName && $lastName) {
            $name .= ' ';
        }

        $name .= $lastName;

        return $name;
    }

    /**
     * @param \craft\commerce\models\PaymentSource $paymentSource
     *
     * @throws \craft\commerce\square\errors\CustomerException
     * @throws \yii\base\InvalidConfigException
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource)
    {
        $this->token = $paymentSource->token;

        /** @var \craft\commerce\square\gateways\Gateway $gateway */
        $gateway = $paymentSource->getGateway();

        /** @var \craft\commerce\square\models\SquareCustomer $customer */
        $customer = Plugin::getInstance()->getCustomers()->getCustomer(
            $gateway,
            $paymentSource->getUser()
        );

        $this->customerReference = $customer->reference;
    }
}
