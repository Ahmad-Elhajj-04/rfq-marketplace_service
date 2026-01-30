<?php

use yii\db\Migration;

class m260130_102248_m004_create_requests_table extends Migration

{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%requests}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),

            'title' => $this->string(180)->notNull(),
            'description' => $this->text()->notNull(),

            'quantity' => $this->decimal(12, 3)->notNull(),
            'unit' => "ENUM('ton','kg','piece','meter','liter','box','other') NOT NULL",

            'delivery_city' => $this->string(120)->notNull(),
            'delivery_lat' => $this->decimal(10, 7)->null(),
            'delivery_lng' => $this->decimal(10, 7)->null(),

            'required_delivery_date' => $this->date()->notNull(),

            'budget_min' => $this->decimal(12, 2)->null(),
            'budget_max' => $this->decimal(12, 2)->null(),

            'expires_at' => $this->integer()->notNull(), // unix timestamp
            'status' => "ENUM('open','closed','awarded','cancelled') NOT NULL DEFAULT 'open'",

            'awarded_quotation_id' => $this->integer()->null(),

            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_requests_user_created', '{{%requests}}', ['user_id', 'created_at']);
        $this->createIndex('idx_requests_category_status', '{{%requests}}', ['category_id', 'status']);
        $this->createIndex('idx_requests_expires', '{{%requests}}', 'expires_at');

        $this->addForeignKey('fk_requests_user', '{{%requests}}', 'user_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_requests_category', '{{%requests}}', 'category_id', '{{%categories}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_requests_user', '{{%requests}}');
        $this->dropForeignKey('fk_requests_category', '{{%requests}}');
        $this->dropTable('{{%requests}}');
    }
}
