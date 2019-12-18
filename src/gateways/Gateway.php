<?php

namespace craft\commerce\square\gateways;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\commerce\Plugin as Commerce;
use craft\commerce\square\models\SquarePaymentForm;
use craft\commerce\square\web\assets\PaymentFormAsset;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;

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
        $gateway->setTestMode($this->testMode);

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
     * @param \craft\commerce\models\payments\BasePaymentForm $sourceData
     * @param int                                             $userId
     *
     * @return \craft\commerce\models\PaymentSource
     * @throws \craft\commerce\errors\PaymentException
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        /** @var \craft\commerce\square\gateways\OmnipayGateway $gateway */
        $gateway = $this->gateway();

        $createCardRequest = $gateway->createCard([
            'card' => $sourceData->token,
            'customerReference' => 'GMCCQCDG1X1DB2N6V5AAAKJ61G',
        ]);

        $response = $this->sendRequest($createCardRequest);

        dd($response, $response->getData());

        return new PaymentSource([
            'userId' => $userId,
            'gatewayId' => $this->id,
            'token' => $this->extractCardReference($response),
            'response' => $response->getData(),
            'description' => $this->extractPaymentSourceDescription($response)
        ]);
    }
}
