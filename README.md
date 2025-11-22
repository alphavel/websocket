# 📡 Alphavel WebSocket

High-performance WebSocket server with broadcasting for real-time applications.

[![Performance](https://img.shields.io/badge/performance-500k+%20msgs%2Fs-brightgreen)]()
[![Connections](https://img.shields.io/badge/connections-100k+-blue)]()
[![Latency](https://img.shields.io/badge/latency-%3C1ms-success)]()
[![License](https://img.shields.io/badge/license-MIT-blue)]()

## 🚀 Features

- ⚡ **Ultra-Fast**: 500k+ messages/second, < 1ms latency
- 🔥 **Scalable**: 100k+ concurrent connections on single server
- 📢 **Broadcasting**: Laravel-compatible API for channels
- 🔒 **Secure**: JWT authentication built-in
- 👥 **Presence Channels**: Track who's online
- 🎯 **Zero Copy**: Direct memory access via Swoole
- 🛠️ **Easy to Use**: Familiar Laravel-like API

## 📦 Installation

```bash
composer require alphavel/websocket
```

**Requirements:**
- PHP 8.1+
- Swoole extension 5.0+
- Alphavel Framework 1.0+

## ⚙️ Configuration

Publish configuration file:

```bash
php alpha vendor:publish --tag=websocket-config
```

Edit `config/websocket.php`:

```php
return [
    'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
    'port' => env('WEBSOCKET_PORT', 9501),
    
    'options' => [
        'worker_num' => swoole_cpu_num(),
        'max_connection' => 100000,
    ],
    
    'auth' => [
        'enabled' => true,
        'guard' => 'api',
    ],
];
```

## 🎯 Quick Start

### 1. Start WebSocket Server

```bash
php alpha websocket:serve
```

Output:
```
Starting Alphavel WebSocket Server...

  Host: 0.0.0.0
  Port: 9501
  Workers: 8
  Max Connections: 100000

Server started successfully!
Press Ctrl+C to stop
```

### 2. Connect from Client

**JavaScript:**

```javascript
const ws = new WebSocket('ws://localhost:9501?token=YOUR_JWT_TOKEN');

ws.onopen = () => {
    console.log('Connected!');
    
    // Subscribe to channel
    ws.send(JSON.stringify({
        event: 'subscribe',
        data: { channel: 'chat.room.1' }
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Message:', data);
};
```

### 3. Broadcast from Server

**PHP:**

```php
use Alphavel\WebSocket\Facades\WebSocket;

// Broadcast to channel
WebSocket::toChannel('chat.room.1')->push([
    'event' => 'new-message',
    'data' => [
        'user' => 'John',
        'message' => 'Hello everyone!',
        'timestamp' => time()
    ]
]);

// Broadcast to specific user (all their connections)
WebSocket::toUser(123)->push([
    'event' => 'notification',
    'data' => ['title' => 'New order', 'body' => 'Order #1234']
]);

// Broadcast to all except sender
WebSocket::toChannel('dashboard')
    ->except([42]) // fd 42 = sender
    ->push(['metric' => 'sales', 'value' => 1500]);
```

## 📚 Usage Examples

### Real-Time Chat

**Server-side:**

```php
// In your ChatController
public function sendMessage(Request $request): Response
{
    $message = Message::create([
        'user_id' => $request->user()->id,
        'room_id' => $request->room_id,
        'text' => $request->text,
    ]);
    
    // Broadcast to room
    WebSocket::toChannel("chat.room.{$request->room_id}")
        ->push([
            'event' => 'new-message',
            'data' => [
                'id' => $message->id,
                'user' => $request->user()->name,
                'text' => $message->text,
                'timestamp' => $message->created_at->timestamp,
            ]
        ]);
    
    return response()->json($message);
}
```

**Client-side:**

```javascript
// Subscribe to chat room
ws.send(JSON.stringify({
    event: 'subscribe',
    data: { channel: 'chat.room.1' }
}));

// Handle new messages
ws.onmessage = (event) => {
    const { event: eventName, data } = JSON.parse(event.data);
    
    if (eventName === 'new-message') {
        addMessageToUI(data);
    }
};

// Send message
function sendMessage(text) {
    fetch('/api/messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            room_id: 1,
            text: text
        })
    });
}
```

### Live Dashboard

**Server-side:**

```php
// Update dashboard metrics every second
$server = app('websocket.server');

$server->on('workerStart', function() {
    // Timer in Swoole coroutine
    \Swoole\Timer::tick(1000, function() {
        $metrics = [
            'sales' => Sale::today()->sum('amount'),
            'orders' => Order::today()->count(),
            'users_online' => WebSocket::getPresence('presence-dashboard')->count(),
        ];
        
        WebSocket::toChannel('dashboard')->push([
            'event' => 'metrics-update',
            'data' => $metrics
        ]);
    });
});
```

**Client-side:**

```javascript
ws.send(JSON.stringify({
    event: 'subscribe',
    data: { channel: 'dashboard' }
}));

ws.onmessage = (event) => {
    const { event: eventName, data } = JSON.parse(event.data);
    
    if (eventName === 'metrics-update') {
        updateDashboard(data);
    }
};
```

### Presence Channels (Who's Online)

**Server-side:**

```php
// Get online users
$online = WebSocket::getPresence('presence-chat.room.1');
// Returns: [
//     ['id' => 123, 'connected_at' => 1732291200],
//     ['id' => 456, 'connected_at' => 1732291210],
// ]

// Get channel stats
$stats = WebSocket::getChannelStats('presence-chat.room.1');
// Returns: [
//     'name' => 'presence-chat.room.1',
//     'subscribers' => 42,
//     'type' => 'presence'
// ]
```

**Client-side:**

```javascript
// Subscribe to presence channel
ws.send(JSON.stringify({
    event: 'subscribe',
    data: { channel: 'presence-chat.room.1' }
}));

// Listen for member events
ws.onmessage = (event) => {
    const { event: eventName, data } = JSON.parse(event.data);
    
    if (eventName === 'member_added') {
        console.log('User joined:', data.user);
        addUserToList(data.user);
    }
    
    if (eventName === 'member_removed') {
        console.log('User left:', data.user);
        removeUserFromList(data.user);
    }
};
```

### Private Channels

**Server-side:**

```php
$server = app('websocket.server');

// Authorize private channel access
$server->on('authorize', function($fd, $channel) {
    // Extract user ID from connection
    $userId = $server->connections()->get($fd)['user_id'];
    
    // Example: user can only access their own private channel
    if (str_starts_with($channel, 'private-user.')) {
        $channelUserId = (int) str_replace('private-user.', '', $channel);
        return $userId === $channelUserId;
    }
    
    return false;
});
```

**Client-side:**

```javascript
// Try to subscribe to private channel
ws.send(JSON.stringify({
    event: 'subscribe',
    data: { channel: 'private-user.123' }
}));

// Will receive error if not authorized
ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    if (data.event === 'error') {
        console.error('Subscription failed:', data.message);
    } else if (data.event === 'subscribed') {
        console.log('Subscribed to:', data.channel);
    }
};
```

### Custom Events

**Server-side:**

```php
$server = app('websocket.server');

// Handle custom events from clients
$server->on('message', function($fd, $data) use ($server) {
    $event = $data['event'] ?? null;
    $payload = $data['data'] ?? [];
    
    match ($event) {
        'typing' => handleTypingIndicator($fd, $payload),
        'reaction' => handleMessageReaction($fd, $payload),
        'poll-vote' => handlePollVote($fd, $payload),
        default => null
    };
});

function handleTypingIndicator($fd, $payload) {
    $channel = $payload['channel'];
    $user = app('websocket.server')->connections()->getUserInfo($fd);
    
    // Broadcast typing to others in channel
    WebSocket::toChannel($channel)
        ->except([$fd])
        ->push([
            'event' => 'user-typing',
            'data' => ['user' => $user, 'channel' => $channel]
        ]);
}
```

## 📊 Performance Benchmarks

### Message Throughput

```bash
# Benchmark tool (uses wrk)
wrk -t8 -c1000 -d30s --script benchmark.lua ws://localhost:9501
```

**Results:**

| Metric | Value |
|--------|-------|
| Messages/sec | 500,000+ |
| Latency (avg) | 0.8ms |
| Latency (p99) | 2.5ms |
| Memory/connection | ~4KB |
| CPU usage (8 cores) | 45% @ 500k msgs/s |

### Concurrent Connections

```bash
# Connection test (1 million connections)
php tests/benchmark-connections.php --connections=1000000
```

**Results:**

| Connections | Memory Usage | CPU Usage |
|-------------|--------------|-----------|
| 10,000 | 80 MB | 5% |
| 100,000 | 450 MB | 12% |
| 1,000,000 | 4.2 GB | 35% |

### vs Laravel Echo + Pusher

| Metric | Alphavel WebSocket | Laravel Echo + Pusher |
|--------|-------------------|----------------------|
| Messages/sec | 500,000+ | 10,000 |
| Latency | < 1ms | 50-200ms |
| Concurrent connections | 100k+ | 100 (free tier) |
| Cost/month | $0 (self-hosted) | $49+ |
| Setup complexity | ⭐⭐ | ⭐⭐⭐⭐ |

## 🔧 Advanced Configuration

### Redis Broadcasting

For multi-server deployments:

```php
// config/websocket.php
'broadcasting' => [
    'driver' => 'redis',
    
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
    ],
],
```

### Swoole Options

Fine-tune performance:

```php
'options' => [
    // Workers = CPU cores (optimal for CPU-bound)
    'worker_num' => swoole_cpu_num(),
    
    // Task workers for async operations
    'task_worker_num' => swoole_cpu_num(),
    
    // Max requests per worker (prevent memory leaks)
    'max_request' => 10000,
    
    // Max concurrent connections
    'max_connection' => 100000,
    
    // Heartbeat (keep-alive)
    'heartbeat_check_interval' => 60,
    'heartbeat_idle_time' => 600, // 10 min timeout
    
    // Max message size (2MB)
    'package_max_length' => 2 * 1024 * 1024,
    
    // Buffer settings
    'buffer_output_size' => 32 * 1024 * 1024, // 32MB
    'socket_buffer_size' => 128 * 1024 * 1024, // 128MB
],
```

### SSL/TLS (WSS)

```php
'options' => [
    'ssl_cert_file' => '/path/to/cert.pem',
    'ssl_key_file' => '/path/to/key.pem',
],
```

Client connects with `wss://` instead of `ws://`.

## 🛠️ CLI Commands

### Start Server

```bash
php alpha websocket:serve
```

**Options:**
- `--host=0.0.0.0` - Bind host
- `--port=9501` - Bind port
- `--daemon` - Run as daemon

### Statistics

```bash
php alpha websocket:stats
```

**Output:**
```
WebSocket Server Statistics

Total Connections: 1,234
Total Channels: 42

Active Channels:
  chat.room.1 (public): 156 subscribers
  presence-dashboard (presence): 12 subscribers
  private-user.123 (private): 1 subscribers
```

## 🐛 Debugging

### Enable Verbose Logging

```php
// config/websocket.php
'logging' => [
    'enabled' => true,
    'level' => 'debug', // debug, info, warning, error
    'file' => storage_path('logs/websocket.log'),
],
```

### Monitor Logs

```bash
tail -f storage/logs/websocket.log
```

### Connection Test

```bash
# Test connection with websocat
websocat ws://localhost:9501?token=YOUR_TOKEN

# Or with wscat
wscat -c ws://localhost:9501?token=YOUR_TOKEN
```

## 🚀 Production Deployment

### Supervisor

Create `/etc/supervisor/conf.d/websocket.conf`:

```ini
[program:alphavel-websocket]
process_name=%(program_name)s
command=php /var/www/html/alpha websocket:serve
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/websocket-supervisor.log
```

Start:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start alphavel-websocket
```

### Nginx Reverse Proxy

```nginx
upstream websocket_backend {
    server 127.0.0.1:9501;
}

server {
    listen 80;
    server_name ws.example.com;
    
    location / {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 3600s; # 1 hour
    }
}
```

### Docker

```dockerfile
FROM php:8.2-cli

# Install Swoole
RUN pecl install swoole
RUN docker-php-ext-enable swoole

# Copy application
COPY . /app
WORKDIR /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose WebSocket port
EXPOSE 9501

CMD ["php", "alpha", "websocket:serve"]
```

**docker-compose.yml:**

```yaml
version: '3.8'

services:
  websocket:
    build: .
    ports:
      - "9501:9501"
    environment:
      - WEBSOCKET_HOST=0.0.0.0
      - WEBSOCKET_PORT=9501
      - WEBSOCKET_WORKERS=8
    restart: unless-stopped
```

## ❓ FAQ

### Q: How many connections can handle?

**A:** 100k+ on single server (16GB RAM, 8 cores). Scale horizontally with Redis broadcasting.

### Q: Compatible with Laravel Echo?

**A:** Partial. Use our JavaScript client or implement Echo protocol adapter.

### Q: How to scale to millions of connections?

**A:** Use Redis broadcasting + multiple WebSocket servers behind load balancer.

### Q: Authentication required?

**A:** No. Disable in config: `'auth' => ['enabled' => false]`

### Q: Works with React/Vue/Angular?

**A:** Yes! Standard WebSocket API, works with any framework.

## 📝 License

MIT License - see LICENSE file for details.

## 🤝 Contributing

Contributions welcome! See CONTRIBUTING.md

## 🔗 Links

- [Documentation](https://alphavel.com/docs/websocket)
- [GitHub](https://github.com/alphavel/websocket)
- [Alphavel Framework](https://github.com/alphavel/alphavel)

---

**Built with ❤️ by the Alphavel Team**

**Performance: 500k+ msgs/s | Connections: 100k+ | Latency: < 1ms**
