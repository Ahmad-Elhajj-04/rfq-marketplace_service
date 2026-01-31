<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\components\JwtAuth;
use app\models\Quotation;
use app\models\Request;
use app\services\NotificationService;

class QuotationsController extends Controller
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
            'by-request' => ['GET'],
            'update' => ['PATCH'],
            'withdraw' => ['POST'],
            'accept' => ['POST'],
            'reject' => ['POST'],
        ];
    }

    private function requireRole(string $role): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity || $identity->role !== $role) {
            throw new ForbiddenHttpException('Forbidden.');
        }
    }

    private function loadRequestOr404(int $id): Request
    {
        $req = Request::findOne($id);
        if (!$req) {
            throw new NotFoundHttpException('Request not found.');
        }
        return $req;
    }

    private function loadQuotationOr404(int $id): Quotation
    {
        $q = Quotation::findOne($id);
        if (!$q) {
            throw new NotFoundHttpException('Quotation not found.');
        }
        return $q;
    }

    // POST /v1/quotations (COMPANY)
    public function actionCreate()
    {
        $this->requireRole('company');

        $body = Yii::$app->request->bodyParams;
        $requestId = (int)($body['request_id'] ?? 0);
        if ($requestId <= 0) {
            throw new BadRequestHttpException('request_id is required.');
        }

        $req = $this->loadRequestOr404($requestId);

        if ($req->status !== 'open' || (int)$req->expires_at <= time()) {
            throw new BadRequestHttpException('Request is not available for quotations.');
        }

        $companyId = (int)Yii::$app->user->id;

        $existing = Quotation::find()
            ->where(['request_id' => $requestId, 'company_id' => $companyId])
            ->one();

        if ($existing && !in_array($existing->status, ['withdrawn'], true)) {
            throw new BadRequestHttpException('You already submitted a quotation for this request.');
        }

        $now = time();
        $q = $existing ?: new Quotation();

        $q->request_id = $requestId;
        $q->company_id = $companyId;
        $q->price_per_unit = $body['price_per_unit'] ?? null;
        $q->total_price = $body['total_price'] ?? null;
        $q->delivery_days = $body['delivery_days'] ?? null;
        $q->delivery_cost = $body['delivery_cost'] ?? 0;
        $q->payment_terms = trim((string)($body['payment_terms'] ?? ''));
        $q->notes = $body['notes'] ?? null;

        $validUntil = $body['valid_until'] ?? null;
        if (is_string($validUntil)) {
            $ts = strtotime($validUntil);
            if ($ts === false) {
                throw new BadRequestHttpException('valid_until must be valid datetime.');
            }
            $q->valid_until = $ts;
        } else {
            $q->valid_until = (int)$validUntil;
        }

        if ((int)$q->valid_until <= $now) {
            throw new BadRequestHttpException('valid_until must be in the future.');
        }

        $q->status = $existing ? 'updated' : 'submitted';
        $q->created_at = $existing ? (int)$q->created_at : $now;
        $q->updated_at = $now;

        if (!$q->save()) {
            return ['message' => 'Validation failed', 'errors' => $q->getErrors()];
        }

        // ✅ Notify request owner (DB + WS broadcast to user channel)
        NotificationService::create(
            (int)$req->user_id,
            'quotation.created',
            'New quotation received',
            'A company submitted a quotation for: ' . $req->title,
            ['request_id' => (int)$req->id, 'quotation_id' => (int)$q->id],
            'user:' . (int)$req->user_id
        );

        return ['quotation' => $q];
    }

    // GET /v1/quotations/mine (COMPANY)
    public function actionMine()
    {
        $this->requireRole('company');

        $rows = Quotation::find()
            ->where(['company_id' => (int)Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return ['quotations' => $rows];
    }

    // GET /v1/quotations/by-request?request_id=123 (USER)
    public function actionByRequest()
    {
        $this->requireRole('user');

        $requestId = (int)Yii::$app->request->get('request_id', 0);
        if ($requestId <= 0) {
            throw new BadRequestHttpException('request_id is required.');
        }

        $req = $this->loadRequestOr404($requestId);

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        $quotes = Quotation::find()
            ->where(['request_id' => $requestId])
            ->andWhere(['not in', 'status', ['withdrawn']])
            ->all();

        return [
            'quotations' => $quotes,
        ];
    }

    // PATCH /v1/quotations/{id} (COMPANY)
    public function actionUpdate($id)
    {
        $this->requireRole('company');

        $q = $this->loadQuotationOr404((int)$id);

        if ((int)$q->company_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if (in_array($q->status, ['accepted', 'rejected'], true)) {
            throw new BadRequestHttpException('Quotation cannot be updated after decision.');
        }

        $body = Yii::$app->request->bodyParams;

        $allowed = ['price_per_unit', 'total_price', 'delivery_days', 'delivery_cost', 'payment_terms', 'notes', 'valid_until'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $q->$field = $body[$field];
            }
        }

        if (array_key_exists('valid_until', $body) && is_string($body['valid_until'])) {
            $ts = strtotime($body['valid_until']);
            if ($ts === false) {
                throw new BadRequestHttpException('valid_until must be valid datetime.');
            }
            $q->valid_until = $ts;
        }

        if ((int)$q->valid_until <= time()) {
            throw new BadRequestHttpException('valid_until must be in the future.');
        }

        $q->status = 'updated';
        $q->updated_at = time();

        if (!$q->save()) {
            return ['message' => 'Validation failed', 'errors' => $q->getErrors()];
        }

        return ['quotation' => $q];
    }

    // POST /v1/quotations/{id}/withdraw (COMPANY)
    public function actionWithdraw($id)
    {
        $this->requireRole('company');

        $q = $this->loadQuotationOr404((int)$id);

        if ((int)$q->company_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if (in_array($q->status, ['accepted', 'rejected'], true)) {
            throw new BadRequestHttpException('Quotation cannot be withdrawn after decision.');
        }

        $q->status = 'withdrawn';
        $q->updated_at = time();
        $q->save(false, ['status', 'updated_at']);

        return ['message' => 'Withdrawn', 'quotation' => $q];
    }

    // POST /v1/quotations/{id}/accept (USER)
    public function actionAccept($id)
    {
        $this->requireRole('user');

        $q = $this->loadQuotationOr404((int)$id);
        $req = $this->loadRequestOr404((int)$q->request_id);

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if ($req->status !== 'open') {
            throw new BadRequestHttpException('Request is not open.');
        }
        if ($q->status === 'withdrawn') {
            throw new BadRequestHttpException('Cannot accept a withdrawn quotation.');
        }

        $now = time();

        $tx = Yii::$app->db->beginTransaction();
        try {
            $q->status = 'accepted';
            $q->updated_at = $now;
            $q->save(false, ['status', 'updated_at']);

            Quotation::updateAll(
                ['status' => 'rejected', 'updated_at' => $now],
                ['and', ['request_id' => (int)$req->id], ['not', ['id' => (int)$q->id]], ['not in', 'status', ['withdrawn']]]
            );

            $req->status = 'awarded';
            $req->awarded_quotation_id = (int)$q->id;
            $req->updated_at = $now;
            $req->save(false, ['status', 'awarded_quotation_id', 'updated_at']);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        // ✅ Notify winning company (DB + WS broadcast to user channel)
        NotificationService::create(
            (int)$q->company_id,
            'quotation.accepted',
            'Your quotation was accepted',
            'You won the request: ' . $req->title,
            ['request_id' => (int)$req->id, 'quotation_id' => (int)$q->id],
            'user:' . (int)$q->company_id
        );

        return ['message' => 'Awarded', 'request' => $req, 'accepted_quotation' => $q];
    }

    // POST /v1/quotations/{id}/reject (USER)
    public function actionReject($id)
    {
        $this->requireRole('user');

        $q = $this->loadQuotationOr404((int)$id);
        $req = $this->loadRequestOr404((int)$q->request_id);

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }
        if ($req->status !== 'open') {
            throw new BadRequestHttpException('Request is not open.');
        }
        if (in_array($q->status, ['accepted', 'withdrawn'], true)) {
            throw new BadRequestHttpException('Cannot reject this quotation.');
        }

        $q->status = 'rejected';
        $q->updated_at = time();
        $q->save(false, ['status', 'updated_at']);

        return ['message' => 'Rejected', 'quotation' => $q];
    }
}