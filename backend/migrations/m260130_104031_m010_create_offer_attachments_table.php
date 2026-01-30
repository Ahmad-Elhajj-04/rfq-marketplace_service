<?php

use yii\db\Migration;

class m260130_104031_m010_create_offer_attachments_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%offer_attachments}}', [
            'id' => $this->primaryKey(),
            'offer_id' => $this->integer()->notNull(),

            'file_path' => $this->string(255)->notNull(),
            'original_name' => $this->string(190)->notNull(),
            'mime_type' => $this->string(120)->notNull(),
            'size_bytes' => $this->integer()->notNull(),

            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_offer_attachments_offer', '{{%offer_attachments}}', 'offer_id');

        $this->addForeignKey(
            'fk_offer_attachments_offer',
            '{{%offer_attachments}}',
            'offer_id',
            '{{%offers}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_offer_attachments_offer', '{{%offer_attachments}}');
        $this->dropTable('{{%offer_attachments}}');
    }
}