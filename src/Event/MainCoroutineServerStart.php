<?php

namespace VM\Server\Event;

use Swoole\Coroutine\Http\Server as HttpServer;
use Swoole\Coroutine\Server;

class MainCoroutineServerStart
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var HttpServer|object|Server
     */
    public $server;

    /**
     * @var array
     */
    public $serverConfig;

    public function __construct(string $name, $server, array $serverConfig)
    {
        $this->name = $name;
        $this->server = $server;
        $this->serverConfig = $serverConfig;
    }
}
