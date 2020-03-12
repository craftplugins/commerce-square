<?php

namespace craft\commerce\square\services;

use Craft;
use craft\base\Component;
use craft\commerce\square\gateways\SquareGateway;
use craft\commerce\square\models\SquareCustomer;
use craft\commerce\square\records\SquareCustomer as SquareCustomerRecord;
use craft\db\Query;
use craft\errors\ElementNotFoundException;

/**
 * Class SquareCustomers
 *
 * @package craft\commerce\square\services
 * @property \craft\db\Query $squareCustomerQuery
 */
class SquareCustomers extends Component
{
    /**
     * @param \craft\commerce\square\gateways\SquareGateway $gateway
     * @param int                                           $userId
     *
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteSquareCustomer(SquareGateway $gateway, int $userId): bool
    {
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
     * @param \craft\commerce\square\gateways\SquareGateway $gateway
     * @param int                                           $userId
     *
     * @return \craft\commerce\square\models\SquareCustomer|null
     */
    public function getSquareCustomer(SquareGateway $gateway, int $userId):?SquareCustomer
    {
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
     * @param \craft\commerce\square\gateways\SquareGateway $gateway
     * @param int                                           $userId
     *
     * @return \craft\commerce\square\models\SquareCustomer
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getOrCreateSquareCustomer(SquareGateway $gateway, int $userId): SquareCustomer
    {
        if ($squareCustomer = $this->getSquareCustomer($gateway, $userId)) {
            return $squareCustomer;
        }

        $squareCustomer = $gateway->createCustomer($userId);

        if (!$this->saveSquareCustomer($squareCustomer)) {
            $errors = implode(', ', $squareCustomer->getErrorSummary(true));
            Craft::error($errors, 'commerce-square');

            return null;
        }

        return $squareCustomer;
    }

    /**
     * @param \craft\commerce\square\models\SquareCustomer $customer
     *
     * @return bool
     * @throws \craft\errors\ElementNotFoundException
     */
    public function saveSquareCustomer(SquareCustomer $customer): bool
    {
        if ($customer->id) {
            $record = SquareCustomerRecord::findOne($customer->id);

            if (!$record) {
                throw new ElementNotFoundException("No Square Customer exists with the ID '{$customer->id}'");
            }
        } else {
            $record = new SquareCustomerRecord();
        }

        $record->userId = $customer->userId;
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
            ->select([
                'id',
                'gatewayId',
                'userId',
                'reference',
                'response',
            ])
            ->from(SquareCustomerRecord::tableName());
    }
}
