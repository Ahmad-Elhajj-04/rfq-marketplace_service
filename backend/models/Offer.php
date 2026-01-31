<?php

namespace app\models;

use yii\db\ActiveRecord;

class Offer extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%offers}}';
    }

    public function rules()
    {
        return [
            [['company_id', 'category_id', 'title', 'description', 'price_per_unit', 'unit', 'valid_until', 'status', 'created_at', 'updated_at'], 'required'],
            [['company_id', 'category_id', 'valid_until', 'created_at', 'updated_at'], 'integer'],
            [['price_per_unit', 'delivery_cost', 'min_quantity'], 'number', 'min' => 0],
            [['delivery_days'], 'integer', 'min' => 0],
            [['description'], 'string'],
            [['title'], 'string', 'max' => 180],
            [['delivery_city'], 'string', 'max' => 120],

            ['unit', 'in', 'range' => ['ton','kg','piece','meter','liter','box','other']],
            ['status', 'in', 'range' => ['active','inactive','expired']],

            [['category_id'], 'exist', 'targetClass' => Category::class, 'targetAttribute' => ['category_id' => 'id'], 'message' => 'Invalid category_id.'],
            [['company_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['company_id' => 'id'], 'message' => 'Invalid company_id.'],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'company_id',
            'category_id',
            'title',
            'description',
            'price_per_unit',
            'min_quantity',
            'unit',
            'delivery_days',
            'delivery_cost',
            'delivery_city',
            'valid_until',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}