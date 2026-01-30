<?php

use yii\db\Migration;

class m260130_102245_m003_create_category_subscriptions_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%category_subscriptions}}', [
            'id' => $this->primaryKey(),
            'actor_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'actor_role' => "ENUM('user','company') NULL",
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'uq_subscriptions_actor_category',
            '{{%category_subscriptions}}',
            ['actor_id', 'category_id'],
            true
        );

        $this->createIndex('idx_subscriptions_category', '{{%category_subscriptions}}', 'category_id');

        $this->addForeignKey(
            'fk_subscriptions_actor',
            '{{%category_subscriptions}}',
            'actor_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_subscriptions_category',
            '{{%category_subscriptions}}',
            'category_id',
            '{{%categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_subscriptions_actor', '{{%category_subscriptions}}');
        $this->dropForeignKey('fk_subscriptions_category', '{{%category_subscriptions}}');
        $this->dropTable('{{%category_subscriptions}}');
    }
}
