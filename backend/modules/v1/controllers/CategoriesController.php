<?php

namespace app\modules\v1\controllers;

use yii\rest\Controller;
use app\components\JwtAuth;
use app\models\Category;

class CategoriesController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuth::class,
        ];
        return $behaviors;
    }

    public function verbs()
    {
        return [
            'index' => ['GET'],
        ];
    }

    public function actionIndex()
    {
        $items = Category::find()
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return ['categories' => $items];
    }
}
