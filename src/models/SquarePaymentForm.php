<?php

namespace craft\commerce\square\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\square\Plugin as Square;

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
     * @throws \yii\base\InvalidConfigException
     * @throws \craft\errors\ElementNotFoundException
     */
    public function populateFromPaymentSource(
        PaymentSource $paymentSource
    ): void {
        $this->nonce = $paymentSource->token;

        /** @var \craft\commerce\square\gateways\SquareGateway $gateway */
        $gateway = $paymentSource->getGateway();

        $customer = Square::getInstance()
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
