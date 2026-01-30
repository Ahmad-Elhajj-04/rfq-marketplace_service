<?php

use yii\db\Migration;

class m260130_102256_m006_create_notifications_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%notifications}}', [
            'id' => $this->primaryKey(),
            'recipient_id' => $this->integer()->notNull(),

            'type' => $this->string(50)->notNull(),
            'title' => $this->string(140)->notNull(),
            'body' => $this->string(255)->notNull(),

            'data_json' => $this->json()->null(),
            'is_read' => $this->tinyInteger(1)->notNull()->defaultValue(0),

            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'idx_notifications_recipient_read_created',
            '{{%notifications}}',
            ['recipient_id', 'is_read', 'created_at']
        );

        $this->addForeignKey('fk_notifications_recipient', '{{%notifications}}', 'recipient_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_notifications_recipient', '{{%notifications}}');
        $this->dropTable('{{%notifications}}');
    }
}
