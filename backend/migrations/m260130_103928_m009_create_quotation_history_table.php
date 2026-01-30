<?php

use yii\db\Migration;

class m260130_103928_m009_create_quotation_history_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%quotation_history}}', [
            'id' => $this->primaryKey(),
            'quotation_id' => $this->integer()->notNull(),

            'snapshot_json' => $this->json()->notNull(), // store full previous quotation state
            'change_type' => "ENUM('created','updated','withdrawn','accepted','rejected') NOT NULL",

            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_qh_quotation', '{{%quotation_history}}', 'quotation_id');

        $this->addForeignKey(
            'fk_qh_quotation',
            '{{%quotation_history}}',
            'quotation_id',
            '{{%quotations}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_qh_quotation', '{{%quotation_history}}');
        $this->dropTable('{{%quotation_history}}');
    }
}