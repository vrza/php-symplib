<?php

namespace SimpleIPC\SyMPLib;

class Message
{
    const FORMAT_FIELD_SEPARATOR = '/';
    const FORMAT_LENGTH = 'J';
    const KEY_LENGTH = 'length';
    const FORMAT_PAYLOAD = 'A*';
    const KEY_PAYLOAD = 'payload';
    const PACK_FORMAT = self::FORMAT_LENGTH . self::FORMAT_PAYLOAD;
    const UNPACK_FORMAT = self::FORMAT_LENGTH . self::KEY_LENGTH
                        . self::FORMAT_FIELD_SEPARATOR
                        . self::FORMAT_PAYLOAD . self::KEY_PAYLOAD;


    private $length;
    private $payload;

    public function __construct(string $buf) {
        $unpacked = self::unpack($buf);
        $this->length = $unpacked[self::KEY_LENGTH];
        $this->payload = $unpacked[self::KEY_PAYLOAD];
    }

    public static function pack($payload): string
    {
        return pack(self::PACK_FORMAT, strlen($payload), $payload);
    }

    /**
     * @param string $buf
     * @return array [$size, $message]
     */
    public static function unpack(string $buf): array
    {
        return unpack(self::UNPACK_FORMAT, $buf);
    }

    public function length(): int
    {
        return $this->length;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function append($buf): Message
    {
        $this->payload .= $buf;
        return $this;
    }

    public function __toString(): string
    {
        return $this->payload;
    }
}
