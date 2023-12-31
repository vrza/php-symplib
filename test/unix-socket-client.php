#!/usr/bin/env php
<?php

error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use SimpleIPC\SyMPLib\SocketStreamClient;
use SimpleIPC\SyMPLib\UnixDomainSocketAddress;

const EXIT_USAGE = 64;
const EXIT_NO_CONNECTION = 69;

$file = '/run/user/' . posix_geteuid() . '/tipc/socket1';

if ($argc < 2) {
    fwrite(STDERR, "Usage: ${argv[0]} <command>" . PHP_EOL);
    exit(EXIT_USAGE);
}
$msg = $argv[1];

$client = new SocketStreamClient(new UnixDomainSocketAddress($file));
if ($client->connect() === false) {
    exit(EXIT_NO_CONNECTION);
}
$client->sendMessage($msg);
$response = $client->receiveMessage();
fwrite(STDOUT, $response . PHP_EOL);
$client->disconnect();
