<?php

set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// 设置重试间隔时间为 5000 毫秒 (5 秒)
echo "retry: 2000\n\n";
ob_flush();
flush();

$host  = 'sse-chat-redis';
$port  = '6379';
$redis = new \Redis();
$redis->connect($host, $port);

while (true) {
    if (!$messageList = $redis->lpop('chat_room_1', 2)) {
        continue;
    }

    foreach ($messageList as $message) {
        $message = json_decode($message, true);
        $lastEventId = $id = $message['id'];
        // 推送数据到客户端
        echo "id: " . $id . "\n";
        echo "event: update\n";
        echo "data: " . json_encode($message, JSON_UNESCAPED_UNICODE) . "\n\n";
        ob_flush();
        flush();
    }

    usleep(500);
}
