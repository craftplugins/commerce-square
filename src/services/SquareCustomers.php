<?php

namespace craftplugins\square\services;

use craft\base\Component;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craftplugins\square\errors\SquareException;
use craftplugins\square\gateways\SquareGateway;
use craftplugins\square\models\SquareCustomer;
use craftplugins\square\records\SquareCustomerRecord;

/**
 * Class SquareCustomers
 *
 * @package craft\commerce\square\services
 * @property \craft\db\Query $squareCustomerQuery
 */
class SquareCustomers extends Component
{
    /**
     * @param \craftplugins\square\gateways\SquareGateway $gateway
     * @param int                                         $userId
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteSquareCustomer(
        SquareGateway $gateway,
        int $userId
    ): bool {
        /** @var SquareCustomerRecord|null $record */
        $record = $this->getSquareCustomerQuery()
            ->where([
                'gatewayId' => $gateway->id,
                'userId' => $userId,
            ])
            ->one();

        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }

    /**
     * @param \craftplugins\square\gateways\SquareGateway $gateway
     * @param int                                         $userId
     *
     * @return \craftplugins\square\models\SquareCustomer
     * @throws \Square\Exceptions\ApiException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craftplugins\square\errors\SquareApiErrorException
     * @throws \craftplugins\square\errors\SquareException
     */
    public function getOrCreateSquareCustomer(
        SquareGateway $gateway,
        int $userId
    ): SquareCustomer {
        $squareCustomer = $this->getSquareCustomer($gateway, $userId);

        if ($squareCustomer === null) {
            $squareCustomer = $gateway->createCustomer($userId);

            if (!$this->saveSquareCustomer($squareCustomer)) {
                throw new SquareException('Error saving Square Customer');
            }
        }

        return $squareCustomer;
    }

    /**
     * @param \craftplugins\square\gateways\SquareGateway $gateway
     * @param int                                         $userId
     *
     * @return \craftplugins\square\models\SquareCustomer|null
     */
    public function getSquareCustomer(
        SquareGateway $gateway,
        int $userId
    ): ?SquareCustomer {
        /** @var SquareCustomerRecord|null $record */
        $record = $this->getSquareCustomerQuery()
            ->where([
                'gatewayId' => $gateway->id,
                'userId' => $userId,
            ])
            ->one();

        if ($record === null) {
            return null;
        }

        return new SquareCustomer($record);
    }

    /**
     * @param \craftplugins\square\models\SquareCustomer $customer
     *
     * @return bool
     * @throws \craft\errors\ElementNotFoundException
     */
    public function saveSquareCustomer(SquareCustomer $customer): bool
    {
        if ($customer->id) {
            $record = SquareCustomerRecord::findOne($customer->id);

            if (!$record) {
                throw new ElementNotFoundException(
                    "Invalid Square Customer ID: {$customer->id}"
                );
            }
        } else {
            $record = new SquareCustomerRecord();
        }

        $record->userId = (string) $customer->userId;
        $record->gatewayId = $customer->gatewayId;
        $record->reference = $customer->reference;
        $record->response = $customer->response;

        $customer->validate();

        if (!$customer->hasErrors()) {
            $record->save(false);

            $customer->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * @return \craft\db\Query
     */
    protected function getSquareCustomerQuery(): Query
    {
        return (new Query())
            ->select(['id', 'gatewayId', 'userId', 'reference', 'response'])
            ->from(SquareCustomerRecord::tableName());
    }
}
