<?php

namespace craft\commerce\square\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\commerce\square\models\SquareCustomer;
use craft\commerce\square\models\SquarePaymentForm;
use craft\commerce\square\models\SquareRequestResponse;
use craft\commerce\square\Plugin as Square;
use craft\commerce\square\web\assets\PaymentFormAsset;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\ModelEvent;
use craft\web\Response as WebResponse;
use craft\web\View;
use SquareConnect\Api\CustomersApi;
use SquareConnect\Api\PaymentsApi;
use SquareConnect\Api\RefundsApi;
use SquareConnect\ApiClient;
use SquareConnect\ApiException;
use SquareConnect\Configuration;
use SquareConnect\Model\Address;
use SquareConnect\Model\CreateCustomerCardRequest;
use SquareConnect\Model\CreateCustomerRequest;
use SquareConnect\Model\CreatePaymentRequest;
use SquareConnect\Model\Money;
use SquareConnect\Model\RefundPaymentRequest;
use SquareConnect\ObjectSerializer;
use yii\base\Event;

/**
 * Class SquareGateway
 *
 * @package craft\commerce\square\gateways
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
    public $appId;

    /**
     * @var string
     */
    public $locationId;

    /**
     * @var bool
     */
    public $testMode;

    /**
     * @var \SquareConnect\ApiClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $paymentSourceVerificationToken;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        Event::on(User::class, User::EVENT_BEFORE_DELETE, static function (
            ModelEvent $event
        ) {
            /** @var User $user */
            $user = $event->sender;

            Square::getInstance()
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
     * @inheritDoc
     */
    public function authorize(
        Transaction $transaction,
        BasePaymentForm $form
    ): RequestResponseInterface {
        return $this->authorizeOrPurchase($transaction, $form, false);
    }

    /**
     * @inheritDoc
     */
    public function capture(
        Transaction $transaction,
        string $reference
    ): RequestResponseInterface {
        try {
            $payments = new PaymentsApi($this->getClient());
            $response = $payments->completePayment($reference);

            return new SquareRequestResponse($response);
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'commerce-square');

            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function completeAuthorize(
        Transaction $transaction
    ): RequestResponseInterface {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function completePurchase(
        Transaction $transaction
    ): RequestResponseInterface {
        return null;
    }

    /**
     * @param int $userId
     *
     * @return \craft\commerce\square\models\SquareCustomer|null
     * @throws \craft\errors\ElementNotFoundException
     */
    public function createCustomer(int $userId): ?SquareCustomer
    {
        $user = Craft::$app->getUsers()->getUserById($userId);

        if ($user === null) {
            throw new ElementNotFoundException(
                "No user exists with the ID '{$userId}'"
            );
        }

        $request = new CreateCustomerRequest();
        $request->setGivenName($user->firstName);
        $request->setFamilyName($user->lastName);
        $request->setEmailAddress($user->email);

        try {
            $customersApi = new CustomersApi($this->getClient());
            $response = $customersApi->createCustomer($request);
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'commerce-square');

            return null;
        }

        $squareCustomer = new SquareCustomer();
        $squareCustomer->userId = $user->id;
        $squareCustomer->gatewayId = $this->id;
        $squareCustomer->reference = $response->getCustomer()->getId();
        $squareCustomer->response = ObjectSerializer::sanitizeForSerialization(
            $response
        );

        return $squareCustomer;
    }

    /**
     * @inheritDoc
     * @throws \yii\base\InvalidConfigException
     * @throws \craft\errors\ElementNotFoundException
     */
    public function createPaymentSource(
        BasePaymentForm $sourceData,
        int $userId
    ): PaymentSource {
        /** @var \craft\commerce\square\models\SquarePaymentForm $sourceData */

        $user = Craft::$app->getUsers()->getUserById($userId);
        $customer = Commerce::getInstance()
            ->getCustomers()
            ->getCustomerByUserId($user->id);
        $squareCustomer = Square::getInstance()
            ->getSquareCustomers()
            ->getOrCreateSquareCustomer($this, $user->id);

        // Save the verification token locally
        $this->paymentSourceVerificationToken = $sourceData->verificationToken;

        $request = new CreateCustomerCardRequest();
        $request->setCardNonce($sourceData->nonce);
        $request->setVerificationToken($sourceData->verificationToken);
        $request->setCardholderName($sourceData->getCardholderName());

        if ($primaryBillingAddress = $customer->primaryBillingAddress) {
            $billingAddress = new Address();
            $billingAddress->setFirstName($primaryBillingAddress->firstName);
            $billingAddress->setLastName($primaryBillingAddress->lastName);
            $billingAddress->setAddressLine1($primaryBillingAddress->address1);
            $billingAddress->setAddressLine2($primaryBillingAddress->address2);
            $billingAddress->setAddressLine3($primaryBillingAddress->address3);
            $billingAddress->setPostalCode($primaryBillingAddress->zipCode);
            $billingAddress->setLocality($primaryBillingAddress->stateName);
            $billingAddress->setCountry(
                $primaryBillingAddress->getCountryIso()
            );

            $request->setBillingAddress($billingAddress);
        }

        try {
            $customersApi = new CustomersApi($this->getClient());
            $response = $customersApi->createCustomerCard(
                $squareCustomer->reference,
                $request
            );
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'commerce-square');

            return null;
        }

        $description = Craft::t(
            'commerce-square',
            '{cardType} ending in ••••{last4}',
            [
                'cardType' => $response->getCard()->getCardBrand(),
                'last4' => $response->getCard()->getLast4(),
            ]
        );

        $paymentSource = new PaymentSource();
        $paymentSource->userId = $userId;
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = $response->getCard()->getId();
        $paymentSource->response = ObjectSerializer::sanitizeForSerialization(
            $response
        );
        $paymentSource->description = $description;

        return $paymentSource;
    }

    /**
     * @inheritDoc
     *
     * @param $token
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

        $squareCustomer = Square::getInstance()
            ->getSquareCustomers()
            ->getSquareCustomer($this, $userId);

        if ($squareCustomer === null) {
            return false;
        }

        try {
            $squareCustomers = new CustomersApi($this->getClient());
            $squareCustomers->deleteCustomerCard(
                $squareCustomer->reference,
                $token
            );
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'commerce-square');

            return false;
        }

        return true;
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
                'square' => [
                    'applicationId' => Craft::parseEnv($this->appId),
                    'locationId' => Craft::parseEnv($this->locationId),
                ],
            ],
            $params
        );

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile('https://js.squareupsandbox.com/v2/paymentform');
        $view->registerAssetBundle(PaymentFormAsset::class);

        $html = $view->renderTemplate(
            'commerce-square/paymentforms/paymentform',
            [
                'params' => $params,
            ]
        );

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
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app
            ->getView()
            ->renderTemplate('commerce-square/gateway/settings', [
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
     */
    public function getVerificationFormHtml(array $params): ?string
    {
        // TODO: Implement getVerificationFormHtml() method.
    }

    /**
     * @inheritDoc
     */
    public function processWebHook(): WebResponse
    {
        // TODO: Implement processWebHook() method.
    }

    /**
     * @inheritDoc
     */
    public function purchase(
        Transaction $transaction,
        BasePaymentForm $form
    ): RequestResponseInterface {
        return $this->authorizeOrPurchase($transaction, $form);
    }

    /**
     * @inheritDoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $amount = $this->getAmountMoney($transaction);

        $request = new RefundPaymentRequest();
        $request->setIdempotencyKey($transaction->hash);
        $request->setAmountMoney($amount);
        $request->setReason($transaction->message);

        try {
            $refundsApi = new RefundsApi($this->getClient());
            $response = $refundsApi->refundPayment($request);

            return new SquareRequestResponse($response);
        } catch (ApiException $exception) {
            Craft::error($exception->getMessage(), 'commerce-square');

            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $form
     * @param bool                                            $autocomplete
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    protected function authorizeOrPurchase(
        Transaction $transaction,
        BasePaymentForm $form,
        bool $autocomplete = true
    ): RequestResponseInterface {
        /** @var \craft\commerce\square\models\SquarePaymentForm $form */

        $amount = $this->getAmountMoney($transaction);

        // Fallback to newly created payment source verification token
        $verificationToken =
            $form->verificationToken ?? $this->paymentSourceVerificationToken;

        $request = new CreatePaymentRequest();
        $request->setAmountMoney($amount);
        $request->setAutocomplete($autocomplete);
        $request->setCustomerId($form->customerReference);
        $request->setIdempotencyKey($transaction->hash);
        $request->setLocationId($this->getLocationId());
        $request->setSourceId($form->nonce);
        $request->setVerificationToken($verificationToken);

        // Unset the payment source verification token to prevent misuse
        $this->paymentSourceVerificationToken = null;

        try {
            $payments = new PaymentsApi($this->getClient());
            $response = $payments->createPayment($request);

            return new SquareRequestResponse($response);
        } catch (ApiException $exception) {
            return new SquareRequestResponse($exception);
        }
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \SquareConnect\Model\Money
     */
    protected function getAmountMoney(Transaction $transaction): Money
    {
        $currency = Commerce::getInstance()
            ->getCurrencies()
            ->getCurrencyByIso($transaction->paymentCurrency);

        $amount = $transaction->paymentAmount * 10 ** $currency->minorUnit;

        $money = new Money();
        $money->setAmount($amount);
        $money->setCurrency($currency->alphabeticCode);

        return $money;
    }

    /**
     * @param \craft\elements\User $user
     *
     * @return array
     */
    protected function getBillingContactForUser(User $user): array
    {
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
     * @return \SquareConnect\ApiClient
     */
    protected function getClient(): ApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = new Configuration();
        $config->setAccessToken(Craft::parseEnv($this->accessToken));

        if ($this->testMode) {
            $config->setHost('https://connect.squareupsandbox.com');
        } else {
            $config->setHost('https://connect.squareup.com');
        }

        return $this->client = new ApiClient($config);
    }

    /**
     * @return string
     */
    protected function getLocationId(): string
    {
        return Craft::parseEnv($this->locationId);
    }
}
