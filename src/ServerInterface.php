<?php

namespace VM\Server;

use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use Swoole\Server as SwooleServer;
use Swoole\Coroutine\Server as SwooleCoServer;


interface ServerInterface
{
    public const SERVER_HTTP = 1;

    public const SERVER_WEBSOCKET = 2;

    public const SERVER_BASE = 3;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EventDispatcherInterface $dispatcher);

    /**
     * @param array $config
     */
    public function config($config = []);

    public function start();

    /**
     * @return SwooleCoServer|SwooleServer
     */
    public function getServer();
}
