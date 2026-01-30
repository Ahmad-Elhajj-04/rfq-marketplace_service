<?php

namespace app\services;

use app\models\Notification;

class NotificationService
{
    public static function create(
        int $recipientId,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): Notification {
        $n = new Notification();
        $n->recipient_id = $recipientId;
        $n->type = $type;
        $n->title = $title;
        $n->body = $body;
        $n->data_json = empty($data) ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
        $n->is_read = 0;
        $n->created_at = time();
        $n->save(false);
        return $n;
    }
}
