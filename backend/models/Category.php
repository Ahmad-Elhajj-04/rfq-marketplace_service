<?php

namespace app\models;

use yii\db\ActiveRecord;

class Category extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%categories}}';
    }

    public function rules()
    {
        return [
            [['name', 'type'], 'required'],
            [['name'], 'string', 'max' => 120],
            ['type', 'in', 'range' => ['material', 'service']],
            [['created_at'], 'integer'],
        ];
    }

    public function fields()
    {
     
        return [
            'id',
            'name',
            'type',
            'created_at',
        ];
    }
}