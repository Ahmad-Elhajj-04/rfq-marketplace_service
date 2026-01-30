<?php

namespace app\models;

use yii\db\ActiveRecord;

class Quotation extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%quotations}}';
    }

    public function rules()
    {
        return [
            [['request_id', 'company_id', 'price_per_unit', 'total_price', 'delivery_days', 'delivery_cost', 'payment_terms', 'valid_until', 'status', 'created_at', 'updated_at'], 'required'],
            [['request_id', 'company_id', 'delivery_days', 'valid_until', 'created_at', 'updated_at'], 'integer'],

            [['price_per_unit', 'total_price', 'delivery_cost'], 'number', 'min' => 0],
            [['notes'], 'string'],
            [['payment_terms'], 'string', 'max' => 255],

            ['status', 'in', 'range' => ['submitted', 'updated', 'withdrawn', 'accepted', 'rejected']],

            // FK safety checks
            [['request_id'], 'exist', 'targetClass' => \app\models\Request::class, 'targetAttribute' => ['request_id' => 'id'], 'message' => 'Invalid request_id.'],
            [['company_id'], 'exist', 'targetClass' => \app\models\User::class, 'targetAttribute' => ['company_id' => 'id'], 'message' => 'Invalid company_id.'],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'request_id',
            'company_id',
            'price_per_unit',
            'total_price',
            'delivery_days',
            'delivery_cost',
            'payment_terms',
            'notes',
            'valid_until',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
