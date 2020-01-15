<?php

namespace craft\commerce\square\gateways;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\commerce\square\models\Customer;
use craft\commerce\square\models\SquarePaymentForm;
use craft\commerce\square\Plugin;
use craft\commerce\square\web\assets\PaymentFormAsset;
use craft\elements\User;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\ResponseInterface;
use SquareConnect\Model\Address as SquareAddress;

/**
 * Class SquareCommerceGateway
 *
 * @package craft\commerce\square\gateways
 */
class Gateway extends CreditCardGateway
{
    /**
     * @var string
     */
    public $appId;

    /**
     * @var string
     */
    public $accessToken;

    /**
     * @var string
     */
    public $locationId;

    /**
     * @var bool
     */
    public $testMode;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Square gateway');
    }

    /**
     * @return string|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-square/gateway/settings', [
            'gateway' => $this,
        ]);
    }

    /**
     * @return string|null
     */
    protected function getGatewayClassName()
    {
        return '\\' . OmnipayGateway::class;
    }

    /**
     * @return \Omnipay\Common\AbstractGateway
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var \Omnipay\Square\Gateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setAppId(Craft::parseEnv($this->appId));
        $gateway->setAccessToken(Craft::parseEnv($this->accessToken));
        $gateway->setLocationId(Craft::parseEnv($this->locationId));
        $gateway->setTestMode((bool) $this->testMode);

        return $gateway;
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
     */
    public function getPaymentFormHtml(array $params)
    {
        // Merge params
        $params = array_replace_recursive([
            'gateway' => $this,
            'containerClass' => null,
            'errorsClass' => null,
            'squareParams' => [
                'applicationId' => Craft::parseEnv($this->appId),
                'locationId' => Craft::parseEnv($this->locationId),
                'inputClass' => 'sq-input',
                'autoBuild' => false,
                'card' => [
                    'elementId' => 'sq-card',
                ],
            ],
        ], $params);

        // Append default classes
        $params['containerClass'] .= ' square-payment-form-container';
        $params['errorsClass'] .= ' square-payment-form-errors';

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile('https://js.squareupsandbox.com/v2/paymentform');
        $view->registerAssetBundle(PaymentFormAsset::class);

        $html = $view->renderTemplate('commerce-square/paymentforms/paymentform', $params);
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
     * @param \craft\elements\User $user
     *
     * @return \craft\commerce\square\models\Customer
     */
    public function createCustomer(User $user): Customer
    {
        /** @var \craft\commerce\square\gateways\OmnipayGateway $gateway */
        $gateway = $this->gateway();

        $commerceCustomer = \craft\commerce\Plugin::getInstance()->getCustomers()->getCustomerByUserId($user->id);

        /** @var \Omnipay\Square\Message\CreateCustomerRequest $request */
        $request = $gateway->createCustomer();
        $request->setFirstName($user->firstName);
        $request->setLastName($user->lastName);
        $request->setEmail($user->email);
        $request->setReferenceId($user->id);

        $address = $this->getSquareAddress($commerceCustomer->primaryBillingAddress);
        $request->setAddress($address);

        /** @var \Omnipay\Square\Message\CustomerResponse $response */
        $response = $this->sendRequest($request);

        return new Customer([
            'userId' => $user->id,
            'gatewayId' => $this->id,
            'reference' => $response->getCustomerReference(),
            'response' => $response->getData(),
        ]);
    }

    /**
     * @param \craft\commerce\models\payments\BasePaymentForm $sourceData
     * @param int                                             $userId
     *
     * @return \craft\commerce\models\PaymentSource
     * @throws \craft\commerce\errors\PaymentException
     * @throws \craft\commerce\square\errors\CustomerException
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        /** @var SquarePaymentForm $paymentForm */
        $paymentForm = $sourceData;

        /** @var \craft\commerce\square\gateways\OmnipayGateway $gateway */
        $gateway = $this->gateway();

        $user = Craft::$app->getUsers()->getUserById($userId);
        $customer = Plugin::getInstance()->customers->getCustomer($this, $user);

        $createCardRequest = $gateway->createCard([
            'card' => $paymentForm->cardNonce,
            'cardholderName' => $paymentForm->cardholderName,
            'customerReference' => $customer->reference,
        ]);

        /** @var \Omnipay\Square\Message\CardResponse $response */
        $response = $this->sendRequest($createCardRequest);

        return new PaymentSource([
            'userId' => $userId,
            'gatewayId' => $this->id,
            'token' => $this->extractCardReference($response),
            'response' => $response->getData(),
            'description' => $this->extractPaymentSourceDescription($response),
        ]);
    }

    /**
     * @param \Omnipay\Common\Message\ResponseInterface $response
     *
     * @return string
     * @throws \craft\commerce\errors\PaymentException
     */
    protected function extractCardReference(ResponseInterface $response): string
    {
        if ($cardReference = parent::extractCardReference($response)) {
            return $cardReference;
        }

        /** @var \SquareConnect\Model\Card $card */
        $card = $response->getData()->getCard();

        return (string) $card->getId();
    }

    /**
     * @param \Omnipay\Common\Message\ResponseInterface $response
     *
     * @return string
     */
    protected function extractPaymentSourceDescription(ResponseInterface $response): string
    {
        /** @var \SquareConnect\Model\Card $card */
        $card = $response->getData()->getCard();

        return Craft::t('commerce-square', '{cardType} ending in ••••{last4}', [
            'cardType' => $card->getCardBrand(),
            'last4' => $card->getLast4(),
        ]);
    }

    /**
     * @param \craft\commerce\models\Address $address
     *
     * @return \SquareConnect\Model\Address
     */
    protected function getSquareAddress(Address $address): SquareAddress
    {
        $squareAddress = new SquareAddress();
        $squareAddress->setFirstName($address->firstName);
        $squareAddress->setLastName($address->lastName);
        $squareAddress->setAddressLine1($address->address1);
        $squareAddress->setAddressLine2($address->address2);
        $squareAddress->setAddressLine3($address->address3);
        $squareAddress->setLocality($address->city);
        $squareAddress->setAdministrativeDistrictLevel1($address->stateName);
        $squareAddress->setPostalCode($address->zipCode);
        $squareAddress->setCountry($address->getCountry()->iso);

        return $squareAddress;
    }
}
