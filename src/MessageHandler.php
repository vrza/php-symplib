<?php

namespace SimpleIPC\SyMPLib;

interface MessageHandler
{
    public function handleMessage(string $msg): string;
}
