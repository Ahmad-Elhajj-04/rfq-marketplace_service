<?php

namespace app\services;

use Yii;

class WsPublisher
{
    public static function publish(string $channel, array $data): void
    {
        $cfg = Yii::$app->params['centrifugo'] ?? null;
        if (!$cfg || empty($cfg['api_url']) || empty($cfg['api_key'])) {
            return;
        }

        $payload = [
            'channel' => $channel,
            'data' => $data,
        ];

        $ch = curl_init($cfg['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $cfg['api_key'],
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_exec($ch);
        curl_close($ch);
    }
}