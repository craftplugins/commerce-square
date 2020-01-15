<?php

namespace craft\commerce\square\gateways;

use Omnipay\Common\Message\RequestInterface;
use Omnipay\Square\Gateway;

/**
 * Class SquareOmnipayGateway
 * @method RequestInterface authorize(array $options = [])
 * @method RequestInterface completeAuthorize(array $options = [])
 * @method RequestInterface capture(array $options = [])
 * @method RequestInterface void(array $options = [])
 * @method RequestInterface updateCard(array $options = [])
 *
 * @package craft\commerce\square\gateways
 */
class OmnipayGateway extends Gateway
{
}
