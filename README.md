# sse-chat
基于 SSE(Server-Sent Events) 技术，实现聊天室功能

### 0. 实现聊天室的功能方式有很多种，比如轮询、websocket、SSE(Server-Sent Events)，我们来对比一下各种方式的区别

|  实现方式    |   优点 | 缺点  | 使用场景|
|:-----------|:-------|:-------|:-------|
| http轮询    | 简单易用，兼容性好，流量可控 | 高延迟，服务器负载高，带宽使用高| 对实时性要求不高，低并发，对带宽不敏感 |
| SSE  | 实时更新，服务器负载低、带宽使用少（相比轮训），实现简单且支持自动重连 |单向通信，数据只能从服务端到客户端|实时性高且服务器负载较小的场景，如股票行情、新闻推送、在线聊天等|
| websocket  | 实时更新，支持全双工通信 |实现和维护相比SSE复杂，需要处理连接的建立、重连、断开等|聊天应用、在线游戏、视频会议等|


### 1. 看了 chatGPT 网站的数据交互使用的是 SSE(Server-Sent Events)，所以此处也用 PHP 来写一个 Demo 实现简单的聊天室

附上代码的连接 [GitHub](https://github.com/skarner2016/sse-chat)

### 2. 首先需要安装 docker 和 docker-compose

如果没有安装，自行搜索解决，此处不表

### 3. 目录结构

```
|____docker
| |____docker-compose.yaml
| |____php.ini-production
| |____Dockerfile
| |____www.conf.ini
| |____.env
| |____nginx.conf
|____index.html
|____sse.php
|____send.php
```

### 4. Dockerfile
```dockerfile
# 使用指定基础镜像
FROM php:8.2-fpm-bullseye

# 设置环境变量 DEBIAN_FRONTEND
ENV DEBIAN_FRONTEND noninteractive

COPY www.conf.ini /usr/local/etc/php-fpm.d/www.conf

# 安装必要的软件包和依赖项
RUN apt-get update \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN docker-php-ext-install pcntl bcmath mysqli pdo_mysql \
    && docker-php-ext-enable pcntl bcmath mysqli

# 安装 git unzip (安装laravel需要)
RUN apt install git unzip -y

RUN pecl install redis \
	&& docker-php-ext-enable redis

RUN apt install -y redis-server redis-tools \
    && /etc/init.d/redis-server start
# 安装 composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

### 5. 修改 PHP 配置，需要在 php.ini 中加入以下两行
```ini
; 禁用输出缓冲
output_buffering = Off

; 启用隐式刷新
implicit_flush = On
```

### 6. Nginx 配置
```nginx
worker_processes 1;
events { worker_connections 1024; }

http {

    keepalive_timeout 65;
    keepalive_requests 100;
        # 禁用输出缓冲
    fastcgi_buffering off;
    proxy_buffering off;

    gzip off;

    server {
        listen 80;
        server_name localhost;

        root /var/www;
        index index.php index.html index.htm;

        location / {
            try_files $uri $uri/ =404;
        }

        location /sse.php {
            # 关闭缓冲区以避免延迟
            proxy_buffering off;
            proxy_cache off;
            chunked_transfer_encoding off;
            # 确保 SSE 响应头正确
            add_header Cache-Control "no-cache";
            add_header Content-Type "text/event-stream";
        }	
	
        location ~ \.php$ {
            # include snippets/fastcgi-php.conf;
            fastcgi_pass sse-chat-php:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;

            # 禁用 FastCGI 缓冲
            fastcgi_buffering off;
            fastcgi_keep_conn on;

            # 设置缓冲区大小
            fastcgi_buffers 8 16k;
            fastcgi_buffer_size 32k;
        }
    }
}
```


### 7. docker-compose.yml

```yaml
version: '3'

services:
  sse-chat-nginx:
    image: nginx:1.27.0
    container_name: sse-chat-nginx
    ports:
      - "80:80"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ../:/var/www
    depends_on:
      - sse-chat-php
      - sse-chat-redis
    networks:
      - local_host

  sse-chat-php:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: sse-chat-php
    ports:
      - 8081:8081
    volumes:
      - ../:/var/www
    stdin_open: true
    tty: true
    working_dir: /var/www
    restart: always
    networks:
      - local_host

  sse-chat-redis:
    container_name: sse-chat-redis
    image: redis:alpine
    ports:
      - 16379:6379
    restart: always
    networks:
      - local_host

networks:
  local_host:
    driver: bridge

```

### 8. 进入 docker 目录，启动容器
```sh

cd docker && docker-compose up -d

```

### 9. 通过 http 发送消息，并写入 Redis List（send.php）
```php
<?php

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

```

### 10. 请求创建消息
```sh

curl localhost/send.php?name=Alice&message=This_is_Alice

```

### 11. 查看消息 index.html 

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSE Chat</title>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof (EventSource) !== "undefined") {
                const source = new EventSource('sse.php');

                source.addEventListener('update', function (event) {
                    const data = JSON.parse(event.data);
                    const messageElement = document.createElement('p');
                    messageElement.textContent = data.created_at + "</br>" + data.name + ":" + data.message;
                    document.getElementById('messages').appendChild(messageElement);
                    console.log('id:', data.id);
                });

                source.addEventListener('close', function (event) {
                    const messageElement = document.createElement('p');
                    messageElement.textContent = "Connection closed by server.";
                    document.getElementById('messages').appendChild(messageElement);
                    source.close();
                });
            } else {
                alert("Your browser does not support Server-Sent Events.");
            }
        });
    </script>
</head>
<body>
    <h1>SSE Chat</h1>
    <div id="messages"></div>
</body>
</html>

```


### 12. sse.php

```php
<?php

require './vendor/autoload.php';

set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// 设置重试间隔时间
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
```

### 13. 至此，一个通过 SSE(Server-Sent Events) 实现了一个简单的聊天室功能
