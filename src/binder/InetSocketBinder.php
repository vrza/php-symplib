<?php

namespace SimpleIPC\SyMPLib;

class InetSocketBinder
{
    public static function bind(&$socket, InetSocketAddress $address): bool
    {
        return socket_bind($socket, $address->getAddress(), $address->getPort());
    }
}
