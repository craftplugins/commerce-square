<?php

namespace augmentations\craft\commerce\square\services;

use augmentations\craft\commerce\square\errors\SquareException;
use augmentations\craft\commerce\square\gateways\SquareGateway;
use augmentations\craft\commerce\square\models\SquareCustomer;
use augmentations\craft\commerce\square\records\SquareCustomerRecord;
use craft\base\Component;
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
     * @param \augmentations\craft\commerce\square\gateways\SquareGateway $gateway
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
     * @param \augmentations\craft\commerce\square\gateways\SquareGateway $gateway
     * @param int                                         $userId
     *
     * @return \augmentations\craft\commerce\square\models\SquareCustomer
     * @throws \Square\Exceptions\ApiException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \augmentations\craft\commerce\square\errors\SquareApiErrorException
     * @throws \augmentations\craft\commerce\square\errors\SquareException
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
     * @param \augmentations\craft\commerce\square\gateways\SquareGateway $gateway
     * @param int                                         $userId
     *
     * @return \augmentations\craft\commerce\square\models\SquareCustomer|null
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
     * @param \augmentations\craft\commerce\square\models\SquareCustomer $customer
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
