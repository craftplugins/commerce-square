<?php

namespace augmentations\craft\commerce\square\models;

use augmentations\craft\commerce\square\Plugin;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;

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
     * @throws \Square\Exceptions\ApiException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \augmentations\craft\commerce\square\errors\SquareApiErrorException
     * @throws \augmentations\craft\commerce\square\errors\SquareException
     * @throws \yii\base\InvalidConfigException
     */
    public function populateFromPaymentSource(
        PaymentSource $paymentSource
    ): void {
        $this->nonce = $paymentSource->token;

        /** @var \augmentations\craft\commerce\square\gateways\SquareGateway $gateway */
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
