<?php

namespace app\models;

use yii\db\ActiveRecord;

class Request extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%requests}}';
    }

    public function rules()
    {
        return [
            
            [['user_id', 'category_id', 'title', 'description', 'quantity', 'unit', 'delivery_city', 'required_delivery_date', 'expires_at', 'status', 'created_at', 'updated_at'], 'required'],

           
            [['user_id', 'category_id', 'awarded_quotation_id', 'expires_at', 'created_at', 'updated_at'], 'integer'],
            [['quantity'], 'number', 'min' => 0.0001],
            [['budget_min', 'budget_max'], 'number', 'min' => 0],
            [['delivery_lat', 'delivery_lng'], 'number'],
            [['description'], 'string'],

            [['title'], 'string', 'max' => 180],
            [['delivery_city'], 'string', 'max' => 120],

      
            [['required_delivery_date'], 'date', 'format' => 'php:Y-m-d'],

   
            ['unit', 'in', 'range' => ['ton', 'kg', 'piece', 'meter', 'liter', 'box', 'other']],
            ['status', 'in', 'range' => ['open', 'closed', 'awarded', 'cancelled']],


            [
                ['category_id'],
                'exist',
                'targetClass' => Category::class,
                'targetAttribute' => ['category_id' => 'id'],
                'message' => 'Invalid category_id.',
            ],

            // budget logic
            [
                'budget_max',
                'compare',
                'compareAttribute' => 'budget_min',
                'operator' => '>=',
                'type' => 'number',
                'when' => function (self $model) {
                    return $model->budget_min !== null && $model->budget_max !== null;
                },
                'message' => 'budget_max must be >= budget_min',
            ],

           
            ['delivery_lat', 'compare', 'compareValue' => -90, 'operator' => '>=', 'type' => 'number', 'when' => fn(self $m) => $m->delivery_lat !== null, 'message' => 'delivery_lat must be >= -90'],
            ['delivery_lat', 'compare', 'compareValue' => 90, 'operator' => '<=', 'type' => 'number', 'when' => fn(self $m) => $m->delivery_lat !== null, 'message' => 'delivery_lat must be <= 90'],
            ['delivery_lng', 'compare', 'compareValue' => -180, 'operator' => '>=', 'type' => 'number', 'when' => fn(self $m) => $m->delivery_lng !== null, 'message' => 'delivery_lng must be >= -180'],
            ['delivery_lng', 'compare', 'compareValue' => 180, 'operator' => '<=', 'type' => 'number', 'when' => fn(self $m) => $m->delivery_lng !== null, 'message' => 'delivery_lng must be <= 180'],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'user_id',
            'category_id',
            'title',
            'description',
            'quantity',
            'unit',
            'delivery_city',
            'delivery_lat',
            'delivery_lng',
            'required_delivery_date',
            'budget_min',
            'budget_max',
            'expires_at',
            'status',
            'awarded_quotation_id',
            'created_at',
            'updated_at',
        ];
    }
}
