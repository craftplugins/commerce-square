<?php

namespace craftplugins\square\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\commerce\square\models\SquarePaymentForm;
use craft\commerce\square\models\SquareRequestResponse;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\ModelEvent;
use craft\web\Response as WebResponse;
use craft\web\View;
use craftplugins\square\errors\MethodNotSupportedException;
use craftplugins\square\errors\PaymentSourceException;
use craftplugins\square\models\SquareCustomer;
use craftplugins\square\Plugin;
use craftplugins\square\web\assets\PaymentFormAsset;
use Square\Environment;
use Square\Exceptions\ApiException;
use Square\Models\CreateCustomerCardRequest;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Models\RefundPaymentRequest;
use Square\SquareClient;
use yii\base\Event;

/**
 * Class SquareGateway
 *
 * @package craftplugins\square\gateways
 * @property null|string $settingsHtml
 */
class SquareGateway extends Gateway
{
    /**
     * @var string
     */
    public $accessToken;

    /**
     * @var string
     */
    public $applicationId;

    /**
     * @var string
     */
    public $locationId;

    /**
     * @var bool
     */
    public $testMode;

    /**
     * @var string
     */
    protected $paymentSourceVerificationToken;

    /**
     * @var SquareClient
     */
    protected $squareClient;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        Event::on(User::class, User::EVENT_BEFORE_DELETE, function (
            ModelEvent $event
        ) {
            /** @var User $user */
            $user = $event->sender;

            Plugin::getInstance()
                ->getSquareCustomers()
                ->deleteSquareCustomer($this, $user->id);
        });
    }

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return 'Square';
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $form
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function authorize(
        Transaction $transaction,
        BasePaymentForm $form
    ): RequestResponseInterface {
        return $this->authorizeOrPurchase($transaction, $form, false);
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     * @param string                             $reference
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function capture(
        Transaction $transaction,
        string $reference
    ): RequestResponseInterface {
        try {
            $paymentsApi = $this->getSquareClient()->getPaymentsApi();
            $apiResponse = $paymentsApi->completePayment($reference);

            return new SquareRequestResponse($apiResponse);
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'square');

            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \craft\commerce\base\RequestResponseInterface
     * @throws \craftplugins\square\errors\MethodNotSupportedException
     */
    public function completeAuthorize(
        Transaction $transaction
    ): RequestResponseInterface {
        throw new MethodNotSupportedException();
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \craft\commerce\base\RequestResponseInterface
     * @throws \craftplugins\square\errors\MethodNotSupportedException
     */
    public function completePurchase(
        Transaction $transaction
    ): RequestResponseInterface {
        throw new MethodNotSupportedException();
    }

    /**
     * @param int $userId
     *
     * @return \craftplugins\square\models\SquareCustomer|null
     * @throws \craft\errors\ElementNotFoundException
     */
    public function createCustomer(int $userId): ?SquareCustomer
    {
        $user = Craft::$app->getUsers()->getUserById($userId);

        if ($user === null) {
            throw new ElementNotFoundException("Invalid user ID: {$userId}");
        }

        $body = new CreateCustomerRequest();
        $body->setReferenceId($user->id);
        $body->setGivenName($user->firstName);
        $body->setFamilyName($user->lastName);
        $body->setEmailAddress($user->email);

        try {
            $customersApi = $this->getSquareClient()->getCustomersApi();
            $apiResponse = $customersApi->createCustomer($body);

            /** @var \Square\Models\CreateCustomerResponse $createCustomerResponse */
            $createCustomerResponse = $apiResponse->getResult();

            $squareCustomer = new SquareCustomer();
            $squareCustomer->response = $createCustomerResponse;
            $squareCustomer->userId = $user->id;
            $squareCustomer->gatewayId = $this->id;
            $squareCustomer->reference = $createCustomerResponse
                ->getCustomer()
                ->getId();

            return $squareCustomer;
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'square');

            return null;
        }
    }

    /**
     * @param \craft\commerce\models\payments\BasePaymentForm $sourceData
     * @param int                                             $userId
     *
     * @return \craft\commerce\models\PaymentSource
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craftplugins\square\errors\PaymentSourceException
     * @throws \craftplugins\square\errors\SquareCustomerException
     * @throws \yii\base\InvalidConfigException
     */
    public function createPaymentSource(
        BasePaymentForm $sourceData,
        int $userId
    ): PaymentSource {
        /** @var \craft\commerce\square\models\SquarePaymentForm $sourceData */

        $user = Craft::$app->getUsers()->getUserById($userId);

        $squareCustomer = Plugin::getInstance()
            ->getSquareCustomers()
            ->getOrCreateSquareCustomer($this, $user->id);

        // Save the verification token locally
        $this->paymentSourceVerificationToken = $sourceData->verificationToken;

        $body = new CreateCustomerCardRequest($sourceData->nonce);
        $body->setVerificationToken($sourceData->verificationToken);

        // todo: Add $addressId to SquarePaymentForm - https://github.com/square/square-php-sdk/blob/master/doc/customers.md#create-customer-card
        // $body->setCardholderName($sourceData->getCardholderName());
        // if ($primaryBillingAddress = $customer->primaryBillingAddress) {
        //     $billingAddress = new Address();
        //     $billingAddress->setFirstName($primaryBillingAddress->firstName);
        //     $billingAddress->setLastName($primaryBillingAddress->lastName);
        //     $billingAddress->setAddressLine1($primaryBillingAddress->address1);
        //     $billingAddress->setAddressLine2($primaryBillingAddress->address2);
        //     $billingAddress->setAddressLine3($primaryBillingAddress->address3);
        //     $billingAddress->setPostalCode($primaryBillingAddress->zipCode);
        //     $billingAddress->setLocality($primaryBillingAddress->stateName);
        //     $billingAddress->setCountry(
        //         $primaryBillingAddress->getCountryIso()
        //     );
        //
        //     $request->setBillingAddress($billingAddress);
        // }

        try {
            $customersApi = $this->getSquareClient()->getCustomersApi();

            /** @var \Square\Models\CreateCustomerCardResponse $apiResponse */
            $apiResponse = $customersApi->createCustomerCard(
                $squareCustomer->reference,
                $body
            );
        } catch (ApiException $exception) {
            throw new PaymentSourceException($exception->getMessage());
        }

        $description = Craft::t('square', '{cardType} ending in ••••{last4}', [
            'cardType' => $apiResponse->getCard()->getCardBrand(),
            'last4' => $apiResponse->getCard()->getLast4(),
        ]);

        $paymentSource = new PaymentSource();
        $paymentSource->userId = $userId;
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = $apiResponse->getCard()->getId();
        $paymentSource->response = $apiResponse;
        $paymentSource->description = $description;

        return $paymentSource;
    }

    /**
     * @param string $token
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function deletePaymentSource($token): bool
    {
        $userId = PaymentSourceRecord::find()
            ->select(['userId'])
            ->where(['token' => $token])
            ->scalar();

        $squareCustomer = Plugin::getInstance()
            ->getSquareCustomers()
            ->getSquareCustomer($this, $userId);

        if ($squareCustomer === null) {
            return false;
        }

        try {
            $customersApi = $this->getSquareClient()->getCustomersApi();
            $customersApi->deleteCustomerCard(
                $squareCustomer->reference,
                $token
            );

            return true;
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'square');

            return false;
        }
    }

    /**
     * @param array $params
     *
     * @return string|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        $params = array_replace_recursive(
            [
                'containerClass' => 'square-payment-form',
                'errorClass' => 'square-payment-form-errors',
                'paymentForm' => [
                    'autoBuild' => false,
                    'inputClass' => 'sq-input',
                    'cardNumber' => [
                        'elementId' => 'sq-card-number',
                    ],
                    'cvv' => [
                        'elementId' => 'sq-cvv',
                    ],
                    'expirationDate' => [
                        'elementId' => 'sq-expiration-date',
                    ],
                    'postalCode' => [
                        'elementId' => 'sq-postal-code',
                    ],
                ],
            ],
            $params,
            [
                'paymentForm' => [
                    'applicationId' => $this->getApplicationId(),
                    'locationId' => $this->getLocationId(),
                ],
            ]
        );

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile('https://js.squareupsandbox.com/v2/paymentform', [
            'position' => View::POS_HEAD,
        ]);

        $view->registerAssetBundle(PaymentFormAsset::class);

        $html = $view->renderTemplate('square/paymentforms/paymentform', [
            'params' => $params,
        ]);

        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new SquarePaymentForm();
    }

    /**
     * @return string|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app
            ->getView()
            ->renderTemplate('square/gateway/settings', [
                'gateway' => $this,
            ]);
    }

    /**
     * @param \craft\commerce\elements\Order $order
     * @param \craft\elements\User|null      $user
     *
     * @return array
     */
    public function getVerificationDetails(
        Order $order,
        User $user = null
    ): array {
        $verificationDetails = [];

        if ($order) {
            $verificationDetails['amount'] = number_format(
                $order->getTotalPrice(),
                2
            );
            $verificationDetails['currency'] = $order->paymentCurrency;
        }

        $customers = Commerce::getInstance()->getCustomers();

        if ($user !== null) {
            $customer = $customers->getCustomerByUserId($user->id);
        } else {
            $customer = $customers->getCustomer();
        }

        if (
            $customer !== null &&
            ($address = $customer->getPrimaryBillingAddress())
        ) {
            $verificationDetails['billingContact']['givenName'] =
                $address->firstName ?? $user->firstName;
            $verificationDetails['billingContact']['familyName'] =
                $address->lastName ?? $user->lastName;

            $verificationDetails['billingContact'][
                'addressLines'
            ] = array_filter([
                $address->address1,
                $address->address2,
                $address->address3,
            ]);

            $verificationDetails['billingContact']['city'] = $address->city;
            $verificationDetails['billingContact']['postalCode'] =
                $address->zipCode;
            $verificationDetails['billingContact'][
                'country'
            ] = $address->getCountry()->iso;
        }

        return $verificationDetails;
    }

    /**
     * @param array $params
     *
     * @return string|null
     * @throws \craftplugins\square\errors\MethodNotSupportedException
     */
    public function getVerificationFormHtml(array $params): ?string
    {
        // todo: Implement getVerificationFormHtml() method.
        throw new MethodNotSupportedException();
    }

    /**
     * @return \craft\web\Response
     * @throws \craftplugins\square\errors\MethodNotSupportedException
     */
    public function processWebHook(): WebResponse
    {
        // todo: Implement processWebHook() method.
        throw new MethodNotSupportedException();
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $form
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function purchase(
        Transaction $transaction,
        BasePaymentForm $form
    ): RequestResponseInterface {
        return $this->authorizeOrPurchase($transaction, $form);
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $idempotencyKey = $transaction->hash;
        $amountMoney = $this->getAmountMoney($transaction);
        $paymentId = $transaction->reference;

        $body = new RefundPaymentRequest(
            $idempotencyKey,
            $amountMoney,
            $paymentId
        );
        $body->setReason($transaction->message);

        try {
            $refundsApi = $this->getSquareClient()->getRefundsApi();
            $apiResponse = $refundsApi->refundPayment($body);

            return new SquareRequestResponse($apiResponse);
        } catch (ApiException $exception) {
            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @return bool
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsWebhooks(): bool
    {
        // todo: Switch to true when updating support
        return false;
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $paymentForm
     * @param bool                                            $autocomplete
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    protected function authorizeOrPurchase(
        Transaction $transaction,
        BasePaymentForm $paymentForm,
        bool $autocomplete = true
    ): RequestResponseInterface {
        /** @var \craft\commerce\square\models\SquarePaymentForm $paymentForm */

        $sourceId = $paymentForm->nonce;
        $idempotencyKey = $transaction->hash;
        $amountMoney = $this->getAmountMoney($transaction);

        $body = new CreatePaymentRequest(
            $sourceId,
            $idempotencyKey,
            $amountMoney
        );
        $body->setAutocomplete($autocomplete);
        $body->setCustomerId($paymentForm->customerReference);
        $body->setLocationId($this->getLocationId());
        $body->setVerificationToken($paymentForm->verificationToken);

        try {
            $paymentsApi = $this->getSquareClient()->getPaymentsApi();
            $apiResponse = $paymentsApi->createPayment($body);

            return new SquareRequestResponse($apiResponse);
        } catch (ApiException $exception) {
            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \Square\Models\Money
     */
    protected function getAmountMoney(Transaction $transaction): Money
    {
        $currency = Commerce::getInstance()
            ->getCurrencies()
            ->getCurrencyByIso($transaction->paymentCurrency);

        $amount = $transaction->paymentAmount * 10 ** $currency->minorUnit;

        $amountMoney = new Money();
        $amountMoney->setAmount($amount);
        $amountMoney->setCurrency($currency->alphabeticCode);

        return $amountMoney;
    }

    /**
     * @return string
     */
    protected function getApplicationId(): string
    {
        return Craft::parseEnv($this->applicationId);
    }

    /**
     * @param \craft\elements\User $user
     *
     * @return array
     */
    protected function getBillingContactForUser(User $user): array
    {
        // todo: Remove unused method (?)

        $billingContact = [];

        $customer = Commerce::getInstance()
            ->getCustomers()
            ->getCustomerByUserId($user->id);

        if ($customer && ($address = $customer->getPrimaryBillingAddress())) {
            $billingContact = array_merge($billingContact, [
                'givenName' => $address->firstName,
                'familyName' => $address->lastName,
                'addressLines' => array_filter([
                    $address->address1,
                    $address->address2,
                    $address->address3,
                ]),
                'city' => $address->city,
                'postalCode' => $address->zipCode,
                'country' => $address->getCountry()->iso,
            ]);
        }

        return array_merge($billingContact, [
            'givenName' => $user->firstName,
            'familyName' => $user->lastName,
        ]);
    }

    /**
     * @return string
     */
    protected function getLocationId(): string
    {
        return Craft::parseEnv($this->locationId);
    }

    /**
     * @return \Square\SquareClient
     */
    protected function getSquareClient(): SquareClient
    {
        if ($this->squareClient !== null) {
            return $this->squareClient;
        }

        return $this->squareClient = new SquareClient([
            'accessToken' => Craft::parseEnv($this->accessToken),
            'environment' => $this->testMode
                ? Environment::SANDBOX
                : Environment::PRODUCTION,
        ]);
    }
}
