<?php

use yii\db\Migration;

class m260201_163300_add_type_to_categories_table extends Migration
{
   public function safeUp()
    {

        $this->addColumn('{{%categories}}', 'type', $this->string(20)->notNull()->defaultValue('material')->after('name'));
        $this->createIndex('idx_categories_type', '{{%categories}}', 'type');
        $this->update('{{%categories}}', ['type' => 'service'], ['in', 'name', [
            'Electrical Services',
            'Plumbing',
            'Plumbing Services',
            'Logistics',
            'Logistics Delivery',
        ]]);

        $this->update('{{%categories}}', ['type' => 'material'], ['in', 'name', [
            'Iron',
            'Iron Supply',
            'Cement',
            'Cement Supply',
        ]]);
    }

    public function safeDown()
    {
        $this->dropIndex('idx_categories_type', '{{%categories}}');
        $this->dropColumn('{{%categories}}', 'type');
    }
}