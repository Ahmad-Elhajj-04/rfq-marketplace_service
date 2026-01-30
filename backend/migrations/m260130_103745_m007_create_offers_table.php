<?php

use yii\db\Migration;

class m260130_103745_m007_create_offers_table extends Migration
{
       public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%offers}}', [
            'id' => $this->primaryKey(),

            'company_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),

            'title' => $this->string(180)->notNull(),
            'description' => $this->text()->notNull(),

            'price_per_unit' => $this->decimal(12, 2)->notNull(),
            'min_quantity' => $this->decimal(12, 3)->null(),
            'unit' => "ENUM('ton','kg','piece','meter','liter','box','other') NOT NULL",

            'delivery_days' => $this->integer()->null(),
            'delivery_cost' => $this->decimal(12, 2)->notNull()->defaultValue(0),

            'delivery_city' => $this->string(120)->null(),
            'valid_until' => $this->integer()->notNull(), // unix timestamp

            'status' => "ENUM('active','inactive','expired') NOT NULL DEFAULT 'active'",

            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_offers_category_status', '{{%offers}}', ['category_id', 'status']);
        $this->createIndex('idx_offers_company_created', '{{%offers}}', ['company_id', 'created_at']);

        $this->addForeignKey('fk_offers_company', '{{%offers}}', 'company_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_offers_category', '{{%offers}}', 'category_id', '{{%categories}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_offers_company', '{{%offers}}');
        $this->dropForeignKey('fk_offers_category', '{{%offers}}');
        $this->dropTable('{{%offers}}');
    }
}