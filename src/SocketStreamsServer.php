<?php

namespace SimpleIPC\SyMPLib;

use InvalidArgumentException;

/**
 * IPC server that listens on multiple Unix sockets,
 * calling a respective message handler to handle data
 * coming in through each socket
 */
class SocketStreamsServer
{
    const ESUCCESS = 0;
    const EINTR = 4;
    const RECV_BUF_SIZE = 64 * 1024;
    const SOCKET_BACKLOG = 4 * 1024;

    private $recvBufSize;
    private $socketsData;
    private $sockets = [];
    private $handlers = [];
    public $verbosity = 0;

    /**
     * @param SocketData[] $socketsData
     * @param int $recvBufSize
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $socketsData, int $recvBufSize = self::RECV_BUF_SIZE)
    {
        $correctTypes = array_reduce(
            $socketsData,
            function ($a, $x) {
                return $a && $x instanceof SocketData;
            },
            true
        );
        if (empty($socketsData) || !$correctTypes) {
            throw new InvalidArgumentException("First argument must be a non-empty array of SocketData objects");
        }

        $this->socketsData = $socketsData;
        $this->recvBufSize = $recvBufSize;

        $this->checkEnv();
    }


    public function __destruct()
    {
        $this->closeAll();
    }

    public function checkEnv()
    {
        if (($v = getenv("SYMPLIB_VERBOSITY")) !== false && ctype_digit($v)) {
            $this->verbosity = intval($v);
        }
    }

    public function closeAll(): void
    {
        foreach ($this->sockets as $socket) {
            if (is_resource($socket) && get_resource_type($socket) === 'Socket') {
                if ($this->verbosity > 1) fwrite(STDERR, "Closing socket (SocketStreamsServer destructor)" . PHP_EOL);
                socket_close($socket);
            }
        }
    }

    private function isListenerSocket(int $i): bool
    {
        return $i < count($this->socketsData);
    }

    public function listen(): bool
    {
        $this->sockets = [];
        $this->handlers = [];
        $success = true;
        for ($i = 0; $i < count($this->socketsData); $i++) {
            $address = $this->socketsData[$i]->getAddress();
            if (($this->sockets[$i] = static::createAndBindSocket($address)) === false) {
                $success = false;
            } else {
                $this->handlers[$i] = $this->socketsData[$i]->getHandler();
            }
        }
        return $success;
    }

    private static function createAndBindSocket(SocketAddress $address)
    {
        $socket = socket_create($address->getDomain(), SOCK_STREAM, 0);
        if ($socket === false) {
            $error = socket_last_error();
            fwrite(STDERR, "socket_create() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            return false;
        }

        $bindSuccess = $address->bindTo($socket);

        if ($bindSuccess === false) {
            $error = socket_last_error($socket);
            fwrite(STDERR, "socket_bind() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            return false;
        }

        if (socket_listen($socket, self::SOCKET_BACKLOG) === false) {
            $error = socket_last_error($socket);
            fwrite(STDERR, "socket_listen() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            return false;
        }

        return $socket;
    }

    /**
     * @param int $timeoutSeconds
     * @param int $timeoutMicroseconds
     *
     * @throws NoListeningSocketsException
     *
     * @return int Returns the number of messages handled
     */
    public function checkMessages(int $timeoutSeconds = 0, int $timeoutMicroseconds = 0): int
    {
        if (empty($this->sockets)) {
            throw new NoListeningSocketsException("No listening sockets, make sure you call listen() before checkMessages()");
        }
        $limit = 1024;
        $cnt = 0;
        $sec = $timeoutSeconds;
        $usec = $timeoutMicroseconds;
        while (($num = $this->handleConnections($sec, $usec)) > 0 && ($cnt += $num) < $limit) {
            $sec = $usec = 0;
        }
        if ($cnt > 0) {
            if ($this->verbosity) fwrite(STDERR, "server handled $cnt messages" . PHP_EOL);
        }
        return $cnt;
    }

    private function handleConnections(int $timeoutSeconds = 0, int $timeoutMicroseconds = 0): int
    {
        $read = $this->sockets;
        $write = $except = null;
        set_error_handler(static function (int $_errno, string $_errstr): bool {
            return true;
        });
        $num = @socket_select($read, $write, $except, $timeoutSeconds, $timeoutMicroseconds);
        restore_error_handler();
        if ($num === false) {
            $error = socket_last_error();
            if ($error !== self::EINTR) {
                fwrite(STDERR, "socket_select() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            }
            return -$error;
        }
        if ($num > 0) {
            foreach ($this->sockets as $socket) {
                if (($i = array_search($socket, $read)) !== false) {
                    if ($this->isListenerSocket($i)) { // handle new connection
                        if (($newSocket = socket_accept($socket)) === false) {
                            $error = socket_last_error($socket);
                            if ($error !== self::ESUCCESS) {
                                fwrite(
                                    STDERR,
                                    "socket_accept() failed with error $error: " . socket_strerror($error) . PHP_EOL
                                );
                                return -$error;
                            }
                        }
                        if ($this->verbosity) fwrite(STDERR, "Accepted new client connection" . PHP_EOL);
                        $this->addSocket($newSocket, $i);
                        $this->handleClientData($newSocket, $i);
                    } else { // handle data from a client
                        $this->handleClientData($socket, $i);
                    }
                }
            }
        }
        return $num;
    }

    private function addSocket($socket, int $i): void
    {
        $this->sockets[] = $socket;
        $this->handlers[] = $this->handlers[$i];
    }

    private function removeSocket($socket): void
    {
        $pos = array_search($socket, $this->sockets);
        if ($pos !== false) {
            unset($this->sockets[$pos]);
            unset($this->handlers[$pos]);
        }
    }

    private function handleClientData($socket, int $i): void
    {
        $msgHandler = $this->handlers[$i];
        $msg = $this->receiveMessage($socket);
        if ($msg !== null) {
            $response = $msgHandler->handleMessage($msg);
            $this->sendResponse($response, $socket);
        } else {
            if ($this->verbosity) fwrite(STDERR, "Client closed connection, removing and closing socket" . PHP_EOL);
            $this->removeSocket($socket);
            socket_close($socket);
            if ($this->verbosity) fwrite(STDERR, "Sockets/handlers set size: " . count($this->socketsData) . "/" . count($this->handlers) . PHP_EOL);
        }
    }

    private function receiveMessage($connectionSocket)
    {
        if ($this->verbosity) fwrite(STDERR, '!' . PHP_EOL);
        if (($bytes = socket_recv($connectionSocket, $buf, $this->recvBufSize, 0)) === false) {
            $error = socket_last_error($connectionSocket);
            fwrite(STDERR, "socket_recv() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            return null;
        }

        if ($bytes === 0) {
            return $buf;
        }

        $message = new Message($buf);

        while (strlen($message->payload()) < $message->length() &&
            ($bytes = socket_recv($connectionSocket, $buf, $this->recvBufSize, 0)) > 0
        ) {
            $message->append($buf);
            if ($this->verbosity) fwrite(STDERR, strlen($message->payload()) . " of " . $message->length() . " bytes received" . PHP_EOL);
        }
        if ($bytes === false) {
            $error = socket_last_error($connectionSocket);
            fwrite(STDERR, "socket_recv() failed with error $error: " . socket_strerror($error) . PHP_EOL);
            return null;
        }

        if ($this->verbosity) fwrite(STDERR, "<<<< " . $message->payload() . PHP_EOL);

        return $message->payload();
    }

    private function sendResponse(string $response, $connectionSocket): void
    {
        $packed = Message::pack($response);
        $bytes = socket_send($connectionSocket, $packed, strlen($packed), 0);
        if ($this->verbosity) fwrite(STDERR, ">>>> $response" . PHP_EOL);
        if ($this->verbosity) fwrite(STDERR, "$bytes bytes sent" . PHP_EOL);
    }

}
