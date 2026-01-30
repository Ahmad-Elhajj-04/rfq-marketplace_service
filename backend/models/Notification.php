<?php

namespace app\models;

use yii\db\ActiveRecord;

class Notification extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%notifications}}';
    }

    public function rules()
    {
        return [
            [['recipient_id', 'type', 'title', 'body', 'is_read', 'created_at'], 'required'],
            [['recipient_id', 'is_read', 'created_at'], 'integer'],
            [['type'], 'string', 'max' => 50],
            [['title'], 'string', 'max' => 140],
            [['body'], 'string', 'max' => 255],
            [['data_json'], 'safe'],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'recipient_id',
            'type',
            'title',
            'body',
            'data_json',
            'is_read',
            'created_at',
        ];
    }
}
