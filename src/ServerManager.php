<?php

namespace VM\Server;

class ServerManager
{
    /**
     * @var array
     */
    protected static $container = [];

    /**
     * @param array $value [$serverType, $server]
     */
    public static function add(string $name, array $value)
    {
        static::$container[$name] =  $value;
    }

    /**
     * Returns the container.
     */
    public static function list(): array
    {
        return static::$container;
    }

    /**
     * Clear the container.
     */
    public static function clear(): void
    {
        static::$container = [];
    }
}
