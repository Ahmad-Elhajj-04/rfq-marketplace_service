<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use app\models\User;

class AuthController extends Controller
{
public function behaviors()
{
    $behaviors = parent::behaviors();

    $behaviors['authenticator'] = [
        'class' => \app\components\JwtAuth::class,
        'except' => ['login', 'register'],
    ];

    return $behaviors;
}


    public function verbs()
    {
        return [
            'register' => ['POST'],
            'login' => ['POST'],
            'me' => ['GET'],
        ];
    }

    public function actionRegister()
    {
        $body = Yii::$app->request->bodyParams;

        $name = trim($body['name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $role = $body['role'] ?? 'user';
        $companyName = trim($body['company_name'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            throw new BadRequestHttpException('name, email, and password are required.');
        }
        if (!in_array($role, ['user', 'company'], true)) {
            throw new BadRequestHttpException('role must be user or company.');
        }
        if ($role === 'company' && $companyName === '') {
            throw new BadRequestHttpException('company_name is required for company role.');
        }
        if (User::findByEmail($email)) {
            throw new BadRequestHttpException('Email already exists.');
        }

        $now = time();

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->role = $role;
        $user->company_name = $role === 'company' ? $companyName : null;
        $user->company_rating = 0;
        $user->created_at = $now;
        $user->updated_at = $now;
        $user->setPassword($password);

        if (!$user->save()) {
            return [
                'message' => 'Validation failed',
                'errors' => $user->getErrors(),
            ];
        }

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    public function actionLogin()
    {
        $body = Yii::$app->request->bodyParams;

        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new BadRequestHttpException('email and password are required.');
        }

        $user = User::findByEmail($email);
        if (!$user || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Invalid credentials.');
        }

        $user->updated_at = time();
        $user->save(false, ['updated_at']);

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    public function actionMe()
    {
        $identity = Yii::$app->user->identity;
        if (!$identity) {
            throw new UnauthorizedHttpException('Unauthorized.');
        }

        return ['user' => $identity];
    }

    private function issueToken(User $user): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];

    $now = time();
    $payload = [
        'iss' => 'rfq-marketplace-service',
        'iat' => $now,
        'exp' => $now + (10 * 24 * 60 * 60), // ✅ 10 days
        'uid' => (int)$user->id,
        'role' => (string)$user->role,
    ];

    $base64UrlEncode = function (string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $secret = Yii::$app->params['jwtSecret'];

    $segments = [];
    $segments[] = $base64UrlEncode(json_encode($header));
    $segments[] = $base64UrlEncode(json_encode($payload));

    $signingInput = $segments[0] . '.' . $segments[1];
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = $base64UrlEncode($signature);

    return implode('.', $segments);
}
}