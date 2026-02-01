<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;
use app\components\JwtAuth;
use app\models\Notification;

class NotificationsController extends Controller
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
            'index' => ['GET'],        // GET /v1/notifications
            'read' => ['POST'],        // POST /v1/notifications/{id}/read
        ];
    }

    // GET /v1/notifications?unread=1
    public function actionIndex()
    {
        $recipientId = (int)Yii::$app->user->id;
        $unread = (int)Yii::$app->request->get('unread', 0);

        $query = Notification::find()
            ->where(['recipient_id' => $recipientId])
            ->orderBy(['created_at' => SORT_DESC]);

        if ($unread === 1) {
            $query->andWhere(['is_read' => 0]);
        }

        return ['notifications' => $query->all()];
    }
public function actionRead($id)
{
    $notification = Notification::findOne([
        'id' => (int)$id,
        'recipient_id' => (int)Yii::$app->user->id,
    ]);

    if (!$notification) {
        throw new NotFoundHttpException('Notification not found.');
    }

    $notification->is_read = 1;
    $notification->save(false, ['is_read']);

    return ['notification' => $notification];
}
}