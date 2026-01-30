<?php

use yii\db\Migration;

class m260130_103850_m008_create_request_attachments_table extends Migration
{
     public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%request_attachments}}', [
            'id' => $this->primaryKey(),
            'request_id' => $this->integer()->notNull(),

            'file_path' => $this->string(255)->notNull(), // stored path
            'original_name' => $this->string(190)->notNull(),
            'mime_type' => $this->string(120)->notNull(),
            'size_bytes' => $this->integer()->notNull(),

            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_request_attachments_request', '{{%request_attachments}}', 'request_id');

        $this->addForeignKey(
            'fk_request_attachments_request',
            '{{%request_attachments}}',
            'request_id',
            '{{%requests}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_request_attachments_request', '{{%request_attachments}}');
        $this->dropTable('{{%request_attachments}}');
    }
}