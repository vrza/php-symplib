<?php

namespace TIPC;

class UnixSocketStreamServer
{
    const ESUCCESS = 0;
    const RECV_BUF_SIZE = 64 * 1024;
    const SOCKET_BACKLOG = 4 * 1024;

    private $path;
    private $socket;
    private $msgHandler;

    public function __construct(string $path, MessageHandler $msgHandler)
    {
        $this->path = $path;
        $this->msgHandler = $msgHandler;
    }

    private static function cleanUpFile($path)
    {
        $dir = dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir, 0750, true);
        }
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function listen(): bool
    {
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (($this->socket) === false) {
            fwrite(
                STDERR,
                "socket_create() failed:" . PHP_EOL .
                "reason: " . socket_strerror(socket_last_error()) . PHP_EOL
            );
            return false;
        }

        self::cleanUpFile($this->path);
        $umask = umask(0117);
        $bindSuccess = socket_bind($this->socket, $this->path);
        umask($umask);
        if ($bindSuccess === false) {
            fwrite(
                STDERR,
                "socket_bind() failed:" . PHP_EOL .
                "reason: " . socket_strerror(socket_last_error($this->socket)) . PHP_EOL
            );
            return false;
        }

        if (socket_listen($this->socket, self::SOCKET_BACKLOG) === false) {
            fwrite(
                STDERR,
                "socket_listen() failed:" . PHP_EOL .
                "reason: " . socket_strerror(socket_last_error($this->socket)) . PHP_EOL
            );
            return false;
        }

        if (socket_set_nonblock($this->socket) === false) {
            fwrite(
                STDERR,
                "socket_set_nonblock() failed:" . PHP_EOL .
                "reason: " . socket_strerror(socket_last_error($this->socket)) . PHP_EOL
            );
            return false;
        }

        return true;
    }

    public function checkMessages()
    {
        $limit = self::SOCKET_BACKLOG;
        $cnt = 0;
        while (($connectionSocket = socket_accept($this->socket)) !== false && ++$cnt < $limit) {
            $msg = $this->receiveMessage($connectionSocket);
            if ($msg !== null) {
                $response = $this->msgHandler->handleMessage($msg);
                $this->sendResponse($response, $connectionSocket);
            }
        }
        $error = socket_last_error($this->socket);
        if ($error !== self::ESUCCESS) {
            fwrite(
                STDERR,
                "socket_accept() failed:" . PHP_EOL .
                "reason: " . socket_strerror($error) . PHP_EOL
            );
        }
        if ($cnt > 0) {
            fwrite(STDERR, "$cnt messages handled" . PHP_EOL);
        }
    }

    public function receiveMessage($connectionSocket)
    {
        fwrite(STDERR, '!' . PHP_EOL);
        if (($bytes = socket_recv($connectionSocket, $buf, self::RECV_BUF_SIZE, MSG_DONTWAIT)) === false) {
            fwrite(
                STDERR,
                "socket_recv() failed" . PHP_EOL .
                "Reason: ($bytes) " . socket_strerror(socket_last_error($this->socket)) . PHP_EOL
            );
            return null;
        } else {
            fwrite(STDERR, "$bytes bytes received" . PHP_EOL);
            fwrite(STDOUT, "<<<< $buf" . PHP_EOL);
            return $buf;
        }
    }

    public function sendResponse($response, $connectionSocket)
    {
        $bytes = socket_send($connectionSocket, $response, strlen($response), 0);
        fwrite(STDOUT, ">>>> $response" . PHP_EOL);
        fwrite(STDERR, "$bytes bytes sent" . PHP_EOL);
    }
}
