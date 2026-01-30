<?php

namespace app\models;

use yii\db\ActiveRecord;

class CategorySubscription extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%category_subscriptions}}';
    }

    public function rules()
    {
        return [
            [['actor_id', 'category_id', 'created_at'], 'required'],
            [['actor_id', 'category_id', 'created_at'], 'integer'],
            ['actor_role', 'in', 'range' => ['user', 'company']],
            [
                ['category_id'],
                'exist',
                'targetClass' => Category::class,
                'targetAttribute' => ['category_id' => 'id'],
                'message' => 'Invalid category_id.',
            ],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'actor_id',
            'category_id',
            'actor_role',
            'created_at',
        ];
    }
}
