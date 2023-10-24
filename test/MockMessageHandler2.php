<?php

namespace SimpleIPC\SyMPLib\Test;

use SimpleIPC\SyMPLib\MessageHandler;

class MockMessageHandler2 implements MessageHandler
{
    public function handleMessage(string $msg): string
    {
        return "Another handler acknowledging request for $msg";
    }
}
