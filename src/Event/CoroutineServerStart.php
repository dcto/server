<?php
 
namespace VM\Server\Event;

use Swoole\Coroutine\Server;

class CoroutineServerStart
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var object|Server
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
