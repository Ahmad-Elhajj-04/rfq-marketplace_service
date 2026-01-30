<?php

use yii\db\Migration;

class m260130_102241_m002_create_categories_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%categories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(120)->notNull()->unique(),
            'type' => "ENUM('material','service') NULL",
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
    }

    public function safeDown()
    {
        $this->dropTable('{{%categories}}');
    }
}
