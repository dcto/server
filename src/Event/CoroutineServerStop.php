<?php

namespace VM\Server\Event;

use Swoole\Coroutine\Server;

class CoroutineServerStop
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var object|Server
     */
    public $server;

    public function __construct(string $name, $server)
    {
        $this->name = $name;
        $this->server = $server;
    }
}
