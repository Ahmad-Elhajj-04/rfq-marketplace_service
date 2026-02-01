<?php

namespace app\components;

use Yii;
use yii\filters\auth\AuthMethod;
use yii\web\UnauthorizedHttpException;
use app\models\User;

class JwtAuth extends AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        $authHeader = $request->getHeaders()->get('Authorization');
        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            return null; 
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            throw new UnauthorizedHttpException('Empty token.');
        }

        $payload = $this->decodeAndVerify($token);

        $uid = $payload['uid'] ?? null;
        if (!$uid) {
            throw new UnauthorizedHttpException('Invalid token payload.');
        }

        $identity = User::findOne(['id' => (int)$uid]);
        if (!$identity) {
            throw new UnauthorizedHttpException('User not found.');
        }

        $user->setIdentity($identity);
        return $identity;
    }

    private function decodeAndVerify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new UnauthorizedHttpException('Invalid token format.');
        }

        [$h64, $p64, $s64] = $parts;

        $base64UrlDecode = function (string $data): string {
            $remainder = strlen($data) % 4;
            if ($remainder) {
                $data .= str_repeat('=', 4 - $remainder);
            }
            return base64_decode(strtr($data, '-_', '+/'));
        };

        $headerJson = $base64UrlDecode($h64);
        $payloadJson = $base64UrlDecode($p64);
        $sig = $base64UrlDecode($s64);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new UnauthorizedHttpException('Invalid token encoding.');
        }

        if (($header['alg'] ?? '') !== 'HS256') {
            throw new UnauthorizedHttpException('Unsupported token algorithm.');
        }

        $secret = Yii::$app->params['jwtSecret'];
        $expected = hash_hmac('sha256', $h64 . '.' . $p64, $secret, true);

        if (!hash_equals($expected, $sig)) {
            throw new UnauthorizedHttpException('Invalid token signature.');
        }

        $exp = (int)($payload['exp'] ?? 0);
        if ($exp && time() > $exp) {
            throw new UnauthorizedHttpException('Token expired.');
        }

        return $payload;
    }
}
