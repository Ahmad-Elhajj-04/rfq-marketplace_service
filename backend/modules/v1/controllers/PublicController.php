<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use app\models\Request as RfqRequest;

class PublicController extends Controller
{
    // No auth for public endpoints
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // keep CORS behavior from app config
        return $behaviors;
    }

    public function verbs()
    {
        return [
            'requests' => ['GET'],
        ];
    }


    public function actionRequests()
    {
        $now = time();
        $categoryId = (int)Yii::$app->request->get('category_id', 0);

        $query = RfqRequest::find()
            ->where(['status' => 'open'])
            ->andWhere(['>', 'expires_at', $now])
            ->orderBy(['created_at' => SORT_DESC]);

        if ($categoryId > 0) {
            $query->andWhere(['category_id' => $categoryId]);
        }

        $rows = $query->all();

        // Return only safe public fields
        $safe = array_map(function ($r) {
            return [
                'id' => (int)$r->id,
                'category_id' => (int)$r->category_id,
                'title' => (string)$r->title,
                'description' => (string)$r->description,
                'quantity' => (string)$r->quantity,
                'unit' => (string)$r->unit,
                'delivery_city' => (string)$r->delivery_city,
                'required_delivery_date' => (string)$r->required_delivery_date,
                'budget_min' => $r->budget_min === null ? null : (string)$r->budget_min,
                'budget_max' => $r->budget_max === null ? null : (string)$r->budget_max,
                'expires_at' => (int)$r->expires_at,
                'status' => (string)$r->status,
                'created_at' => (int)$r->created_at,
            ];
        }, $rows);

        return ['requests' => $safe];
    }
}