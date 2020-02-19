<?php

namespace craft\commerce\square\gateways;

use Omnipay\Square\Gateway;

/**
 * Class OmnipayGateway
 *
 * @package craft\commerce\square\gateways
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface capture(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface void(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = [])
 */
class OmnipayGateway extends Gateway
{
}
