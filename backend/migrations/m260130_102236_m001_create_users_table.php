<?php

use yii\db\Migration;

class m260130_102236_m001_create_users_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%users}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(120)->notNull(),
            'email' => $this->string(190)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'role' => "ENUM('user','company') NOT NULL",
            'company_name' => $this->string(190)->null(),
            'company_rating' => $this->decimal(3, 2)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_users_role', '{{%users}}', 'role');
    }

    public function safeDown()
    {
        $this->dropTable('{{%users}}');
    }
}
