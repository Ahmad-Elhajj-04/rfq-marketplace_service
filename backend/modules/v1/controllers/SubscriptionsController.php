<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use app\components\JwtAuth;
use app\models\CategorySubscription;

class SubscriptionsController extends Controller
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
            'create' => ['POST'],
            'delete' => ['DELETE'],
        ];
    }
    public function actionIndex()
    {
        $actorId = (int)Yii::$app->user->id;

        $items = CategorySubscription::find()
            ->where(['actor_id' => $actorId])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return ['subscriptions' => $items];
    }


    public function actionCreate()
    {
        $actor = Yii::$app->user->identity;
        $body = Yii::$app->request->bodyParams;

        $categoryId = (int)($body['category_id'] ?? 0);
        if ($categoryId <= 0) {
            throw new BadRequestHttpException('category_id is required.');
        }

        $exists = CategorySubscription::find()
            ->where(['actor_id' => (int)$actor->id, 'category_id' => $categoryId])
            ->exists();

        if ($exists) {
            return ['message' => 'Already subscribed.'];
        }

        $sub = new CategorySubscription();
        $sub->actor_id = (int)$actor->id;
        $sub->category_id = $categoryId;
        $sub->actor_role = (string)$actor->role;
        $sub->created_at = time();

        if (!$sub->save()) {
            return ['message' => 'Validation failed', 'errors' => $sub->getErrors()];
        }

        return ['subscription' => $sub];
    }

    public function actionDelete($id)
    {
        $actorId = (int)Yii::$app->user->id;

        $sub = CategorySubscription::findOne((int)$id);
        if (!$sub || (int)$sub->actor_id !== $actorId) {
            throw new NotFoundHttpException('Subscription not found.');
        }

        $sub->delete();
        return ['message' => 'Unsubscribed'];
    }
}
