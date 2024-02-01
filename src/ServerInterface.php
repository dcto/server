<?php

namespace VM\Server;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Server as SwooleCoServer;
use Swoole\Server as SwooleServer;

interface ServerInterface
{
    public const SERVER_HTTP = 1;

    public const SERVER_WEBSOCKET = 2;

    public const SERVER_BASE = 3;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EventDispatcherInterface $dispatcher);

    public function init(ServerConfig $config): ServerInterface;

    public function start();

    /**
     * @return SwooleCoServer|SwooleServer
     */
    public function getServer();
}
