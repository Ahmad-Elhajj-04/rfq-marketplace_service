<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\components\JwtAuth;
use app\models\Offer;

class OffersController extends Controller
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
            'index' => ['GET'],            // user browse offers
            'create' => ['POST'],          // company create offer
            'mine' => ['GET'],             // company my offers
            'update' => ['PATCH'],         // company update offer
            'deactivate' => ['POST'],      // company deactivate offer
            'view' => ['GET'],             // optional: GET /v1/offers/{id}
        ];
    }

    private function requireRole(string $role): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity || $identity->role !== $role) {
            throw new ForbiddenHttpException('Forbidden.');
        }
    }

    private function findOfferOr404(int $id): Offer
    {
        $offer = Offer::findOne($id);
        if (!$offer) {
            throw new NotFoundHttpException('Offer not found.');
        }
        return $offer;
    }

    // -------------------------
    // USER: browse active offers
    // GET /v1/offers?category_id=1&city=Beirut
    // -------------------------
    public function actionIndex()
    {
        $identity = Yii::$app->user->identity;

        // Both user and company can browse offers, but users see active only
        $query = Offer::find();

        // Only show active offers by default
        $query->andWhere(['status' => 'active']);

        // Optional filters
        $categoryId = (int)Yii::$app->request->get('category_id', 0);
        if ($categoryId > 0) {
            $query->andWhere(['category_id' => $categoryId]);
        }

        $city = trim((string)Yii::$app->request->get('city', ''));
        if ($city !== '') {
            $query->andWhere(['delivery_city' => $city]);
        }

        // Hide expired offers automatically
        $query->andWhere(['>', 'valid_until', time()]);

        $query->orderBy(['created_at' => SORT_DESC]);

        return ['offers' => $query->all()];
    }

    // -------------------------
    // COMPANY: create offer
    // POST /v1/offers
    // -------------------------
    public function actionCreate()
    {
        $this->requireRole('company');

        $body = Yii::$app->request->bodyParams;
        $now = time();

        $offer = new Offer();
        $offer->company_id = (int)Yii::$app->user->id;
        $offer->category_id = (int)($body['category_id'] ?? 0);
        $offer->title = trim((string)($body['title'] ?? ''));
        $offer->description = (string)($body['description'] ?? '');

        $offer->price_per_unit = $body['price_per_unit'] ?? null;
        $offer->min_quantity = $body['min_quantity'] ?? null;
        $offer->unit = $body['unit'] ?? null;

        $offer->delivery_days = $body['delivery_days'] ?? null;
        $offer->delivery_cost = $body['delivery_cost'] ?? 0;

        $offer->delivery_city = trim((string)($body['delivery_city'] ?? ''));

        // valid_until string or timestamp
        $valid = $body['valid_until'] ?? null;
        if (is_string($valid)) {
            $ts = strtotime($valid);
            if ($ts === false) {
                throw new BadRequestHttpException('valid_until must be valid datetime.');
            }
            $offer->valid_until = $ts;
        } else {
            $offer->valid_until = (int)$valid;
        }

        if ((int)$offer->valid_until <= $now) {
            throw new BadRequestHttpException('valid_until must be in the future.');
        }

        $offer->status = 'active';
        $offer->created_at = $now;
        $offer->updated_at = $now;

        if (!$offer->save()) {
            return ['message' => 'Validation failed', 'errors' => $offer->getErrors()];
        }

        // Later: notification + WS broadcast to category:{category_id} for subscribed users

        return ['offer' => $offer];
    }

    // -------------------------
    // COMPANY: list my offers
    // GET /v1/offers/mine
    // -------------------------
    public function actionMine()
    {
        $this->requireRole('company');

        $rows = Offer::find()
            ->where(['company_id' => (int)Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return ['offers' => $rows];
    }

    // -------------------------
    // COMPANY: update offer
    // PATCH /v1/offers/{id}
    // -------------------------
    public function actionUpdate($id)
    {
        $this->requireRole('company');

        $offer = $this->findOfferOr404((int)$id);

        if ((int)$offer->company_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        if ($offer->status !== 'active') {
            throw new BadRequestHttpException('Only active offers can be updated.');
        }

        $body = Yii::$app->request->bodyParams;

        $allowed = [
            'title','description','price_per_unit','min_quantity','unit',
            'delivery_days','delivery_cost','delivery_city','valid_until','category_id'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $offer->$field = $body[$field];
            }
        }

        if (array_key_exists('valid_until', $body) && is_string($body['valid_until'])) {
            $ts = strtotime($body['valid_until']);
            if ($ts === false) {
                throw new BadRequestHttpException('valid_until must be valid datetime.');
            }
            $offer->valid_until = $ts;
        }

        if ((int)$offer->valid_until <= time()) {
            throw new BadRequestHttpException('valid_until must be in the future.');
        }

        $offer->updated_at = time();

        if (!$offer->save()) {
            return ['message' => 'Validation failed', 'errors' => $offer->getErrors()];
        }

        return ['offer' => $offer];
    }

    // -------------------------
    // COMPANY: deactivate offer
    // POST /v1/offers/{id}/deactivate
    // -------------------------
    public function actionDeactivate($id)
    {
        $this->requireRole('company');

        $offer = $this->findOfferOr404((int)$id);

        if ((int)$offer->company_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Forbidden.');
        }

        if ($offer->status !== 'active') {
            return ['message' => 'Offer already inactive', 'offer' => $offer];
        }

        $offer->status = 'inactive';
        $offer->updated_at = time();
        $offer->save(false, ['status', 'updated_at']);

        return ['message' => 'Deactivated', 'offer' => $offer];
    }

    // Optional: GET /v1/offers/{id} (active only)
    public function actionView($id)
    {
        $offer = $this->findOfferOr404((int)$id);

        if ($offer->status !== 'active' || (int)$offer->valid_until <= time()) {
            throw new NotFoundHttpException('Offer not found.');
        }

        return ['offer' => $offer];
    }
}
