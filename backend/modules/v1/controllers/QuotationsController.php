<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

use app\components\JwtAuth;
use app\models\Quotation;
use app\models\Request as RfqRequest;
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

    private function toFloat($v): float
    {
        if ($v === null) return 0.0;
        return (float)$v;
    }

    private function toInt($v): int
    {
        if ($v === null) return 0;
        return (int)$v;
    }

    private function norm(float $value, float $min, float $max): float
    {
        if ($max <= $min) return 0.0;
        return ($value - $min) / ($max - $min);
    }

    // ----------------------------
    // POST /v1/quotations
    // Company submits quotation
    // ----------------------------
    public function actionCreate()
    {
        $this->requireRole('company');

        $body = Yii::$app->request->bodyParams;
        $now = time();

        $requestId = (int)($body['request_id'] ?? 0);
        if ($requestId <= 0) {
            throw new BadRequestHttpException('request_id is required.');
        }

        $req = RfqRequest::findOne($requestId);
        if (!$req) {
            throw new NotFoundHttpException('Request not found.');
        }

        if ($req->status !== 'open' || $req->expires_at <= $now) {
            throw new BadRequestHttpException('Request is not available.');
        }

        $q = new Quotation();
        $q->request_id = $requestId;
        $q->company_id = (int)Yii::$app->user->id;

        $q->price_per_unit = $body['price_per_unit'] ?? null;
        $q->total_price = $body['total_price'] ?? null;
        $q->delivery_days = $body['delivery_days'] ?? null;
        $q->delivery_cost = $body['delivery_cost'] ?? null;
        $q->payment_terms = (string)($body['payment_terms'] ?? '');
        $q->notes = (string)($body['notes'] ?? '');

  
        $validUntil = $body['valid_until'] ?? null;
        if (is_string($validUntil)) {
            $ts = strtotime($validUntil);
            if ($ts === false) {
                throw new BadRequestHttpException('valid_until must be a valid datetime string or timestamp.');
            }
            $q->valid_until = $ts;
        } else {
            $q->valid_until = (int)$validUntil;
        }

        if ($q->valid_until <= $now) {
            throw new BadRequestHttpException('valid_until must be in the future.');
        }

        $q->status = 'submitted';
        $q->created_at = $now;
        $q->updated_at = $now;

        if (!$q->save()) {
            return ['message' => 'Validation failed', 'errors' => $q->getErrors()];
        }

    
        NotificationService::create(
            (int)$req->user_id,
            'quotation.created',
            'New quotation received',
            "A company submitted a quotation for: {$req->title}",
            ['request_id' => (int)$req->id, 'quotation_id' => (int)$q->id]
        );

        return ['quotation' => $q];
    }


    public function actionMine()
    {
        $this->requireRole('company');

        $rows = Quotation::find()
            ->where(['company_id' => (int)Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return ['quotations' => $rows];
    }

    public function actionByRequest()
    {
        $this->requireRole('user');

        $requestId = (int)Yii::$app->request->get('request_id', 0);
        if ($requestId <= 0) {
            throw new BadRequestHttpException('request_id is required.');
        }

        $req = RfqRequest::findOne($requestId);
        if (!$req) {
            throw new NotFoundHttpException('Request not found.');
        }

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        $rows = Quotation::find()
            ->where(['request_id' => $requestId])
            ->orderBy(['created_at' => SORT_DESC]) 
            ->all();

        $list = [];
        foreach ($rows as $q) {
            $list[] = $q->toArray();
        }

        $submitted = [];
        $others = [];

        foreach ($list as $q) {
            if (($q['status'] ?? '') === 'submitted') {
                $submitted[] = $q;
            } else {
                $q['score'] = null;
                $q['rank'] = null;
                $q['is_best'] = false;
                $others[] = $q;
            }
        }

        if (count($submitted) === 0) {
            return ['quotations' => $list]; 
        }


        $prices = array_map(fn($q) => $this->toFloat($q['total_price'] ?? 0), $submitted);
        $days   = array_map(fn($q) => $this->toFloat($q['delivery_days'] ?? 0), $submitted);
        $costs  = array_map(fn($q) => $this->toFloat($q['delivery_cost'] ?? 0), $submitted);

        $minPrice = min($prices); $maxPrice = max($prices);
        $minDays  = min($days);   $maxDays  = max($days);
        $minCost  = min($costs);  $maxCost  = max($costs);

        $wPrice = 0.60;
        $wDays  = 0.30;
        $wCost  = 0.10;

        for ($i = 0; $i < count($submitted); $i++) {
            $p = $this->toFloat($submitted[$i]['total_price'] ?? 0);
            $d = $this->toFloat($submitted[$i]['delivery_days'] ?? 0);
            $c = $this->toFloat($submitted[$i]['delivery_cost'] ?? 0);

            $score =
                $wPrice * $this->norm($p, $minPrice, $maxPrice) +
                $wDays  * $this->norm($d, $minDays, $maxDays) +
                $wCost  * $this->norm($c, $minCost, $maxCost);

            $submitted[$i]['score'] = round($score, 6);
        }

        // Sort by score ascending (best first)
        usort($submitted, function ($a, $b) {
            return ($a['score'] <=> $b['score']);
        });

        // Assign ranks
        for ($i = 0; $i < count($submitted); $i++) {
            $submitted[$i]['rank'] = $i + 1;
            $submitted[$i]['is_best'] = ($i === 0);
        }

        // Final sorted list: best→worst submitted first, then others
        $final = array_merge($submitted, $others);

        return ['quotations' => $final];
    }


    public function actionWithdraw($id)
    {
        $this->requireRole('company');

        $q = Quotation::findOne((int)$id);
        if (!$q) {
            throw new NotFoundHttpException('Quotation not found.');
        }
        if ((int)$q->company_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        if ($q->status !== 'submitted') {
            throw new BadRequestHttpException('Only submitted quotations can be withdrawn.');
        }

        $q->status = 'withdrawn';
        $q->updated_at = time();
        $q->save(false, ['status', 'updated_at']);

        return ['quotation' => $q];
    }

    public function actionAccept($id)
    {
        $this->requireRole('user');

        $q = Quotation::findOne((int)$id);
        if (!$q) {
            throw new NotFoundHttpException('Quotation not found.');
        }

        $req = RfqRequest::findOne((int)$q->request_id);
        if (!$req) {
            throw new NotFoundHttpException('Request not found.');
        }

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        if ($req->status !== 'open') {
            throw new BadRequestHttpException('Request is not open.');
        }
        $now = time();
        $req->status = 'awarded';
        $req->awarded_quotation_id = (int)$q->id;
        $req->updated_at = $now;
        $req->save(false, ['status', 'awarded_quotation_id', 'updated_at']);
        $q->status = 'accepted';
        $q->updated_at = $now;
        $q->save(false, ['status', 'updated_at']);

    
        Quotation::updateAll(
            ['status' => 'rejected', 'updated_at' => $now],
            ['and',
                ['request_id' => (int)$req->id],
                ['status' => 'submitted'],
                ['<>', 'id', (int)$q->id],
            ]
        );

        NotificationService::create(
            (int)$q->company_id,
            'quotation.accepted',
            'Quotation accepted',
            "Your quotation was accepted for: {$req->title}",
            ['request_id' => (int)$req->id, 'quotation_id' => (int)$q->id]
        );

        return [
            'message' => 'Awarded',
            'request' => $req,
            'accepted_quotation' => $q,
        ];
    }

 
    public function actionReject($id)
    {
        $this->requireRole('user');

        $q = Quotation::findOne((int)$id);
        if (!$q) {
            throw new NotFoundHttpException('Quotation not found.');
        }

        $req = RfqRequest::findOne((int)$q->request_id);
        if (!$req) {
            throw new NotFoundHttpException('Request not found.');
        }

        if ((int)$req->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        if ($q->status !== 'submitted') {
            throw new BadRequestHttpException('Only submitted quotations can be rejected.');
        }

        $q->status = 'rejected';
        $q->updated_at = time();
        $q->save(false, ['status', 'updated_at']);

        return ['quotation' => $q];
    }
}