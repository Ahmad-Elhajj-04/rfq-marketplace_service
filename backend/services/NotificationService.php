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
        array $data = [],
        ?string $broadcastChannel = null
    ): Notification {
        $n = new Notification();
        $n->recipient_id = $recipientId;
        $n->type = $type;
        $n->title = $title;
        $n->body = $body;

        // works even if DB column is JSON or TEXT
        $n->data_json = empty($data) ? null : json_encode($data, JSON_UNESCAPED_UNICODE);

        $n->is_read = 0;
        $n->created_at = time();
        $n->save(false);

        // Realtime broadcast (optional)
        if ($broadcastChannel) {
            WsPublisher::publish($broadcastChannel, [
                'id' => (int)$n->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'created_at' => (int)$n->created_at,
            ]);
        }

        return $n;
    }
}
