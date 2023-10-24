<?php

namespace SimpleIPC\SyMPLib\Test;

use SimpleIPC\SyMPLib\MessageHandler;

class MockMessageHandler1 implements MessageHandler
{
    public function handleMessage(string $msg): string
    {
        return "Hello client, this is server, acknowledging request for $msg";
    }
}
