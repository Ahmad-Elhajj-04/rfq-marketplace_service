<?php

use yii\db\Migration;
class m260130_102251_m005_create_quotations_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%quotations}}', [
            'id' => $this->primaryKey(),
            'request_id' => $this->integer()->notNull(),
            'company_id' => $this->integer()->notNull(),

            'price_per_unit' => $this->decimal(12, 2)->notNull(),
            'total_price' => $this->decimal(12, 2)->notNull(),

            'delivery_days' => $this->integer()->notNull(),
            'delivery_cost' => $this->decimal(12, 2)->notNull()->defaultValue(0),

            'payment_terms' => $this->string(255)->notNull(),
            'notes' => $this->text()->null(),

            'valid_until' => $this->integer()->notNull(), // unix timestamp

            'status' => "ENUM('submitted','updated','withdrawn','accepted','rejected') NOT NULL DEFAULT 'submitted'",

            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'uq_quotations_request_company',
            '{{%quotations}}',
            ['request_id', 'company_id'],
            true
        );

        $this->createIndex('idx_quotations_request_status', '{{%quotations}}', ['request_id', 'status']);
        $this->createIndex('idx_quotations_company_created', '{{%quotations}}', ['company_id', 'created_at']);

        $this->addForeignKey('fk_quotations_request', '{{%quotations}}', 'request_id', '{{%requests}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_quotations_company', '{{%quotations}}', 'company_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey(
            'fk_requests_awarded_quotation',
            '{{%requests}}',
            'awarded_quotation_id',
            '{{%quotations}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_requests_awarded_quotation', '{{%requests}}');

        $this->dropForeignKey('fk_quotations_request', '{{%quotations}}');
        $this->dropForeignKey('fk_quotations_company', '{{%quotations}}');
        $this->dropTable('{{%quotations}}');
    }
}
