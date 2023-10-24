<?php

namespace SimpleIPC\SyMPLib;

abstract class SocketAddress
{
    /**
     * @return string
     */
    abstract public function getAddress(): string;

    /**
     * @return int
     */
    abstract public function getDomain(): int;

    /**
     * @return int
     */
    abstract public function getPort(): int;

    /**
     * @param $socket
     * @return bool
     */
    abstract public function bindTo($socket): bool;

    /**
     * @return string
     */
   abstract public function __toString(): string;
}
