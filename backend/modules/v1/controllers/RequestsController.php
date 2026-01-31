<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\components\JwtAuth;
use app\models\Request;
use app\models\CategorySubscription;
use app\services\NotificationService;

class RequestsController extends Controller
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
            'create' => ['POST'],
            'mine' => ['GET'],
            'view' => ['GET'],
            'update' => ['PATCH'],
            'cancel' => ['POST'],
            'index' => ['GET'], // company browse
        ];
    }

    private function requireRole(string $role): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity || $identity->role !== $role) {
            throw new ForbiddenHttpException('Forbidden.');
        }
    }

    // POST /v1/requests (user)
    public function actionCreate()
    {
        $this->requireRole('user');

        $body = Yii::$app->request->bodyParams;
        $now = time();

        $model = new Request();
        $model->user_id = (int)Yii::$app->user->id;
        $model->category_id = (int)($body['category_id'] ?? 0);
        $model->title = trim((string)($body['title'] ?? ''));
        $model->description = (string)($body['description'] ?? '');

        $model->quantity = $body['quantity'] ?? null;
        $model->unit = $body['unit'] ?? null;

        $model->delivery_city = trim((string)($body['delivery_city'] ?? ''));
        $model->delivery_lat = $body['delivery_lat'] ?? null;
        $model->delivery_lng = $body['delivery_lng'] ?? null;

        $model->required_delivery_date = $body['required_delivery_date'] ?? null;

        $model->budget_min = $body['budget_min'] ?? null;
        $model->budget_max = $body['budget_max'] ?? null;

        // expires_at can be ISO string or unix timestamp
        $expires = $body['expires_at'] ?? null;
        if (is_string($expires)) {
            $ts = strtotime($expires);
            if ($ts === false) {
                throw new BadRequestHttpException('expires_at must be a valid datetime string or timestamp.');
            }
            $model->expires_at = $ts;
        } else {
            $model->expires_at = (int)$expires;
        }

        if ((int)$model->expires_at <= $now) {
            throw new BadRequestHttpException('expires_at must be in the future.');
        }

        $model->status = 'open';
        $model->created_at = $now;
        $model->updated_at = $now;

        if (!$model->save()) {
            return ['message' => 'Validation failed', 'errors' => $model->getErrors()];
        }

        // ✅ Notify subscribed companies (category-based)
        $subs = CategorySubscription::find()
            ->where(['category_id' => (int)$model->category_id, 'actor_role' => 'company'])
            ->all();

        foreach ($subs as $sub) {
            NotificationService::create(
    (int)$sub->actor_id,
    'request.created',
    'New request posted',
    $model->title,
    ['request_id' => (int)$model->id, 'category_id' => (int)$model->category_id],
    'category:' . (int)$model->category_id
);

        }

        return ['request' => $model];
    }

    // GET /v1/requests/mine (user)
    public function actionMine()
    {
        $this->requireRole('user');

        $rows = Request::find()
            ->where(['user_id' => (int)Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return ['requests' => $rows];
    }

    // GET /v1/requests/{id}
    public function actionView($id)
    {
        $identity = Yii::$app->user->identity;

        $model = Request::findOne((int)$id);
        if (!$model) {
            throw new NotFoundHttpException('Request not found.');
        }

        // User can view their own; Company can view open + not expired
        if ($identity->role === 'user') {
            if ((int)$model->user_id !== (int)$identity->id) {
                throw new ForbiddenHttpException('Forbidden.');
            }
        } else { // company
            if ($model->status !== 'open' || (int)$model->expires_at < time()) {
                throw new ForbiddenHttpException('Request is not available.');
            }
        }

        return ['request' => $model];
    }

    // PATCH /v1/requests/{id} (user)
    public function actionUpdate($id)
    {
        $this->requireRole('user');

        $model = Request::findOne((int)$id);
        if (!$model) {
            throw new NotFoundHttpException('Request not found.');
        }
        if ((int)$model->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if ($model->status !== 'open') {
            throw new BadRequestHttpException('Only open requests can be updated.');
        }

        $body = Yii::$app->request->bodyParams;

        $allowed = [
            'title', 'description', 'quantity', 'unit',
            'delivery_city', 'delivery_lat', 'delivery_lng',
            'required_delivery_date', 'budget_min', 'budget_max', 'expires_at'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $model->$field = $body[$field];
            }
        }

        if (array_key_exists('expires_at', $body) && is_string($body['expires_at'])) {
            $ts = strtotime($body['expires_at']);
            if ($ts === false) {
                throw new BadRequestHttpException('expires_at must be valid datetime.');
            }
            $model->expires_at = $ts;
        }

        if ((int)$model->expires_at <= time()) {
            throw new BadRequestHttpException('expires_at must be in the future.');
        }

        $model->updated_at = time();

        if (!$model->save()) {
            return ['message' => 'Validation failed', 'errors' => $model->getErrors()];
        }

        return ['request' => $model];
    }

    // POST /v1/requests/{id}/cancel (user)
    public function actionCancel($id)
    {
        $this->requireRole('user');

        $model = Request::findOne((int)$id);
        if (!$model) {
            throw new NotFoundHttpException('Request not found.');
        }
        if ((int)$model->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if (in_array($model->status, ['cancelled', 'awarded'], true)) {
            throw new BadRequestHttpException('Request cannot be cancelled.');
        }

        $model->status = 'cancelled';
        $model->updated_at = time();
        $model->save(false, ['status', 'updated_at']);

        return ['request' => $model];
    }

    // ✅ GET /v1/requests (company browse - FILTERED by subscriptions)
    public function actionIndex()
    {
        $this->requireRole('company');

        $companyId = (int)Yii::$app->user->id;
        $now = time();

        // Get subscribed category IDs for this company
        $categoryIds = CategorySubscription::find()
            ->select('category_id')
            ->where(['actor_id' => $companyId, 'actor_role' => 'company'])
            ->column();

        // No subscriptions => return empty (clean)
        if (empty($categoryIds)) {
            return ['requests' => []];
        }

        $query = Request::find()
            ->where(['status' => 'open'])
            ->andWhere(['>', 'expires_at', $now])
            ->andWhere(['category_id' => $categoryIds])
            ->orderBy(['created_at' => SORT_DESC]);

        // Optional: allow filtering to one category (must be subscribed)
        $filterCategoryId = (int)Yii::$app->request->get('category_id', 0);
        if ($filterCategoryId > 0) {
            if (!in_array($filterCategoryId, $categoryIds, true)) {
                // not subscribed to this category => empty
                return ['requests' => []];
            }
            $query->andWhere(['category_id' => $filterCategoryId]);
        }

        return ['requests' => $query->all()];
    }
}
