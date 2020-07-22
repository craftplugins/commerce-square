<?php

namespace craftplugins\square\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\Address;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use craftplugins\square\errors\SquareApiErrorException;
use craftplugins\square\models\SquareCustomer;
use craftplugins\square\models\SquareErrorResponse;
use craftplugins\square\models\SquarePaymentForm;
use craftplugins\square\models\SquareResponse;
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
use Throwable;
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
     * The query parameter used to verify webhook requests
     */
    protected const WEBHOOK_TOKEN = 'sq-token';

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
     * @throws \Square\Exceptions\ApiException
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
     * @throws \Square\Exceptions\ApiException
     */
    public function capture(
        Transaction $transaction,
        string $reference
    ): RequestResponseInterface {
        $paymentsApi = $this->getSquareClient()->getPaymentsApi();
        $apiResponse = $paymentsApi->completePayment($reference);

        if ($apiResponse->isError()) {
            return new SquareErrorResponse($apiResponse->getErrors());
        }

        return new SquareResponse($apiResponse->getResult());
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function completeAuthorize(
        Transaction $transaction
    ): RequestResponseInterface {
        throw new NotImplementedException('completeAuthorize is not supported');
    }

    /**
     * @param \craft\commerce\models\Transaction $transaction
     *
     * @return \craft\commerce\base\RequestResponseInterface
     */
    public function completePurchase(
        Transaction $transaction
    ): RequestResponseInterface {
        throw new NotImplementedException('completePurchase is not supported');
    }

    /**
     * @param int $userId
     *
     * @return \craftplugins\square\models\SquareCustomer|null
     * @throws \Square\Exceptions\ApiException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craftplugins\square\errors\SquareApiErrorException
     */
    public function createCustomer(int $userId): ?SquareCustomer
    {
        $user = Craft::$app->getUsers()->getUserById($userId);

        if ($user === null) {
            throw new ElementNotFoundException("Invalid user ID: {$userId}");
        }

        $body = new CreateCustomerRequest();
        $body->setReferenceId($userId);
        $body->setGivenName($user->firstName);
        $body->setFamilyName($user->lastName);
        $body->setEmailAddress($user->email);

        $customersApi = $this->getSquareClient()->getCustomersApi();
        $apiResponse = $customersApi->createCustomer($body);

        if ($apiResponse->isError()) {
            throw new SquareApiErrorException($apiResponse->getErrors());
        }

        /** @var \Square\Models\CreateCustomerResponse $result */
        $result = $apiResponse->getResult();

        $squareCustomer = new SquareCustomer();
        $squareCustomer->response = $result;
        $squareCustomer->userId = $userId;
        $squareCustomer->gatewayId = $this->id;
        $squareCustomer->reference = $result->getCustomer()->getId();

        return $squareCustomer;
    }

    /**
     * @param \craft\commerce\models\payments\BasePaymentForm $paymentForm
     * @param int                                             $userId
     *
     * @return \craft\commerce\models\PaymentSource
     * @throws \Square\Exceptions\ApiException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craftplugins\square\errors\SquareApiErrorException
     * @throws \craftplugins\square\errors\SquareException
     * @throws \yii\base\InvalidConfigException
     */
    public function createPaymentSource(
        BasePaymentForm $paymentForm,
        int $userId
    ): PaymentSource {
        /** @var SquarePaymentForm $paymentForm */

        $user = Craft::$app->getUsers()->getUserById($userId);

        $squareCustomer = Plugin::getInstance()
            ->getSquareCustomers()
            ->getOrCreateSquareCustomer($this, $user->id);

        $body = new CreateCustomerCardRequest($paymentForm->nonce);
        $body->setVerificationToken($paymentForm->verificationToken);

        $customersApi = $this->getSquareClient()->getCustomersApi();
        $apiResponse = $customersApi->createCustomerCard(
            $squareCustomer->reference,
            $body
        );

        if ($apiResponse->isError()) {
            throw new SquareApiErrorException($apiResponse->getErrors());
        }

        /** @var \Square\Models\CreateCustomerCardResponse $result */
        $result = $apiResponse->getResult();
        $card = $result->getCard();

        $paymentSource = new PaymentSource();
        $paymentSource->userId = $userId;
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = $card->getId();
        $paymentSource->response = $result;
        $paymentSource->description = Craft::t(
            'square',
            '{cardType} ending in ••••{last4}',
            [
                'cardType' => $card->getCardBrand(),
                'last4' => $card->getLast4(),
            ]
        );

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
     * @param array $userParams
     *
     * @return string|null
     * @throws \Throwable
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function getPaymentFormHtml(array $userParams = []): ?string
    {
        $intent = ArrayHelper::getValue($userParams, 'intent', 'CHARGE');

        $order = Commerce::getInstance()
            ->getCarts()
            ->getCart();

        $params = [
            'paymentForm' => [
                'applicationId' => $this->getApplicationId(),
                'locationId' => $this->getLocationId(),
                'autoBuild' => false,
                'inputClass' => 'sq-input',
                'cardNumber' => [
                    'elementId' => 'sq-cardNumber',
                    'placeholder' => Craft::t('square', 'Card Number'),
                ],
                'cvv' => [
                    'elementId' => 'sq-cvv',
                    'placeholder' => Craft::t('square', 'CVV'),
                ],
                'expirationDate' => [
                    'elementId' => 'sq-expirationDate',
                    'placeholder' => Craft::t('square', 'MM/YY'),
                ],
                'postalCode' => [
                    'elementId' => 'sq-postalCode',
                    'placeholder' => Craft::t('square', 'Postal'),
                ],
            ],

            'initialPostalCode' => $this->getBillingAddressZipCode($order),

            'verificationDetails' => $this->getVerificationDetails(
                $intent,
                $order
            ),
        ];

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
     * @return SquarePaymentForm
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

    public function getWebhookUrl(array $params = []): string
    {
        $params[self::WEBHOOK_TOKEN] = $this->getWebhookToken();

        return parent::getWebhookUrl($params);
    }

    /**
     * @return \craft\web\Response
     * @throws \craft\commerce\errors\TransactionException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function processWebHook(): WebResponse
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        $requestToken = $request->getQueryParam(self::WEBHOOK_TOKEN);
        $webhookToken = $this->getWebhookToken();

        if ($requestToken !== $webhookToken) {
            $response->setStatusCode(403);
            $response->data = 'Invalid token';

            return $response;
        }

        if ($request->getBodyParam('type') === 'refund.updated') {
            $this->processRefundUpdatedWebhook();
        }

        $response->data = 'ok';

        return $response;
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $form
     *
     * @return \craft\commerce\base\RequestResponseInterface
     * @throws \Square\Exceptions\ApiException
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
     * @throws \Square\Exceptions\ApiException
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

        $refundsApi = $this->getSquareClient()->getRefundsApi();
        $apiResponse = $refundsApi->refundPayment($body);

        if ($apiResponse->isError()) {
            return new SquareErrorResponse($apiResponse->getErrors());
        }

        return new SquareResponse($apiResponse->getResult());
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
        return true;
    }

    /**
     * @param \craft\commerce\models\Transaction              $transaction
     * @param \craft\commerce\models\payments\BasePaymentForm $paymentForm
     * @param bool                                            $autocomplete
     *
     * @return \craft\commerce\base\RequestResponseInterface
     * @throws \Square\Exceptions\ApiException
     */
    protected function authorizeOrPurchase(
        Transaction $transaction,
        BasePaymentForm $paymentForm,
        bool $autocomplete = true
    ): RequestResponseInterface {
        /** @var SquarePaymentForm $paymentForm */

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

        $paymentsApi = $this->getSquareClient()->getPaymentsApi();
        $apiResponse = $paymentsApi->createPayment($body);

        if ($apiResponse->isError()) {
            return new SquareErrorResponse($apiResponse->getErrors());
        }

        return new SquareResponse($apiResponse->getResult());
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
     * @param \craft\commerce\elements\Order $order
     *
     * @return string|null
     */
    protected function getBillingAddressZipCode(Order $order): ?string
    {
        if (
            $order &&
            ($address = $order->getBillingAddress()) &&
            $address->zipCode
        ) {
            return $address->zipCode;
        }

        return null;
    }

    /**
     * @param \craft\commerce\models\Address $address
     *
     * @return array|null
     */
    protected function getBillingContactForAddress(Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
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
        ];
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

    /**
     * @param string                         $intent
     * @param \craft\commerce\elements\Order $order
     *
     * @return array|null
     */
    protected function getVerificationDetails(
        string $intent,
        Order $order
    ): ?array {
        if ($order === null) {
            return null;
        }

        $verificationDetails = [
            'intent' => $intent,
        ];

        if ($intent === 'CHARGE') {
            $verificationDetails['currencyCode'] = $order->paymentCurrency;
            $verificationDetails[
                'amount'
            ] = Craft::$app
                ->getFormatter()
                ->asDecimal($order->getTotalPrice(), 2);
        }

        $address = $order->getBillingAddress();

        if ($address === null) {
            return $verificationDetails;
        }

        $verificationDetails[
            'billingContact'
        ] = $this->getBillingContactForAddress($address);

        return $verificationDetails;
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    protected function getWebhookToken()
    {
        return Craft::$app->getSecurity()->hashData($this->id);
    }

    /**
     * @throws \craft\commerce\errors\TransactionException
     * @throws \yii\base\InvalidConfigException
     */
    protected function processRefundUpdatedWebhook(): void
    {
        $bodyParams = Craft::$app->getRequest()->getBodyParams();

        // Get the refund object
        $refund = $bodyParams['data']['object']['refund'];

        // Get the currency from the returned currency code
        $currency = Commerce::getInstance()
            ->getCurrencies()
            ->getCurrencyByIso($refund['amount_money']['currency']);

        // Get correct amount based on minor unit
        $amount =
            $refund['amount_money']['amount'] / 10 ** $currency->minorUnit;

        $transactions = Commerce::getInstance()->getTransactions();

        // Find the main refund transaction
        $parentTransaction = $transactions->getTransactionByReferenceAndStatus(
            $refund['id'],
            TransactionRecord::STATUS_PROCESSING
        );

        // Find any existing successfully transaction (avoid duplicates)
        $childTransaction = $transactions->getTransactionByReferenceAndStatus(
            $refund['id'],
            TransactionRecord::STATUS_SUCCESS
        );

        if ($childTransaction || $refund['status'] !== 'COMPLETED') {
            return;
        }

        // Create a new transaction to register the refund success
        $childTransaction = $transactions->createTransaction(
            null,
            $parentTransaction
        );

        $childTransaction->code = '';
        $childTransaction->amount = $amount;
        $childTransaction->currency = (string) $currency;
        $childTransaction->message = $refund['status'];
        $childTransaction->response = $bodyParams;
        $childTransaction->status = TransactionRecord::STATUS_SUCCESS;

        $transactions->saveTransaction($childTransaction);
    }
}
