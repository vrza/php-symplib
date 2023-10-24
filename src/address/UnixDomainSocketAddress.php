<?php

namespace SimpleIPC\SyMPLib;

class UnixDomainSocketAddress extends SocketAddress
{
    const DOMAIN = AF_UNIX;

    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getDomain(): int
    {
        return self::DOMAIN;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return 0;
    }

    /**
     * @param $socket
     * @return bool
     */
    public function bindTo($socket): bool
    {
        return UnixDomainSocketBinder::bind($socket, $this);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }
}
