<?php

namespace craftplugins\square\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * Class Install
 *
 * @package craftplugins\square\migrations
 */
class Install extends Migration
{
    /**
     * @return bool
     */
    public function safeDown(): bool
    {
        MigrationHelper::dropAllForeignKeysOnTable(
            '{{%square_customers}}',
            $this
        );

        $this->dropTable('{{%square_customers}}');

        return true;
    }

    /**
     * @return bool
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%square_customers}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'gatewayId' => $this->integer()->notNull(),
            'reference' => $this->string()->notNull(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            '{{%square_customers}}',
            'gatewayId',
            '{{%commerce_gateways}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%square_customers}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE',
            null
        );

        return true;
    }
}
