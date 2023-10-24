#!/usr/bin/env php
<?php

error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use SimpleIPC\SyMPLib\InetSocketAddress;
use SimpleIPC\SyMPLib\UnixDomainSocketAddress;
use SimpleIPC\SyMPLib\SocketData;
use SimpleIPC\SyMPLib\SocketStreamsServer;
use SimpleIPC\SyMPLib\Test\MockMessageHandler1;
use SimpleIPC\SyMPLib\Test\MockMessageHandler2;
use SimpleIPC\SyMPLib\Test\MockMessageHandler3;

const TICK_TIME = 1;
const EXIT_SOCKET = 72;

$file1 = '/run/user/' . posix_geteuid() . '/tipc/socket1';
$address1 = new UnixDomainSocketAddress($file1);
$address2 = new InetSocketAddress('127.0.0.1', 1414, AF_INET);
$address3 = new InetSocketAddress('::1', 1616, AF_INET6);
$msgHandler1 = new MockMessageHandler1();
$msgHandler2 = new MockMessageHandler2();
$msgHandler3 = new MockMessageHandler3();
$server = new SocketStreamsServer([
    new SocketData($address1, $msgHandler1),
    new SocketData($address2, $msgHandler2),
    new SocketData($address3, $msgHandler3),
]);
if ($server->listen() === false) {
    fwrite(STDERR, "Fatal error: could not listen on one or more of: $address1, $address2, $address3" . PHP_EOL);
    exit(EXIT_SOCKET);
}
fwrite(STDOUT, '==> Listening on:' . PHP_EOL . $address1 . PHP_EOL . $address2 . PHP_EOL . $address3 . PHP_EOL);
while (true) {
    fwrite(STDERR, '.');
    $server->checkMessages(TICK_TIME);
}
