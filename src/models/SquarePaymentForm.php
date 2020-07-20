<?php

namespace craft\commerce\square\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;
use craftplugins\square\Plugin;

/**
 * Class SquarePaymentForm
 *
 * @package craft\commerce\square\models
 * @property null|string $cardholderName
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
    public $nonce;

    /**
     * @var string
     */
    public $verificationToken;

    /**
     * @return string|null
     */
    public function getCardholderName(): ?string
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
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craftplugins\square\errors\SquareCustomerException
     * @throws \yii\base\InvalidConfigException
     */
    public function populateFromPaymentSource(
        PaymentSource $paymentSource
    ): void {
        $this->nonce = $paymentSource->token;

        /** @var \craftplugins\square\gateways\SquareGateway $gateway */
        $gateway = $paymentSource->getGateway();

        $customer = Plugin::getInstance()
            ->getSquareCustomers()
            ->getOrCreateSquareCustomer($gateway, $paymentSource->userId);

        $this->customerReference = $customer->reference;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [[['nonce'], 'required']];
    }
}
