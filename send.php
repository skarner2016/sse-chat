<?php
use Illuminate\Support\Facades\Redis;

$name    = $_GET['name'] ?? '';
$message = $_GET['message'] ?? '';

if (empty($name) || empty($message)) {
    echo json_encode([
        'code' => -1,
        'msg'  => 'fail',
    ]);

} else {

    $host  = 'sse-chat-redis';
    $port  = '6379';
    $redis = new \Redis();
    $redis->connect($host, $port);

    $redis->rpush('chat_room_1', json_encode([
        'name'       => $name,
        'message'    => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'code' => 0,
        'msg'  => 'success',
    ]);
}
