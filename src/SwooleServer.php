<?php

namespace VM\Server;

use Swoole\Coroutine;

use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use VM\Server\Event\CoroutineServerStop;
use VM\Server\Event\CoroutineServerStart;
use VM\Server\Event\MainCoroutineServerStart;

class SwooleServer implements ServerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Swoole\Coroutine\Http\Server|\Swoole\Coroutine\Server
     */
    protected $server;

    /**
     * @var callable
     */
    protected $handler;


    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var bool
     */
    protected $mainServerStarted = false;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EventDispatcherInterface $dispatcher) {
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * set config or get config
     * @param Config|null $config
     * @return Config|null
     */
    public function config($config = [])
    {
        if($config){
            $config['setting']['worker_num'] ??= swoole_cpu_num();
            $this->config = new Config($config);
            return $this;
        }
        return $this->config;
    }

    public function start()
    {
        $this->initServer($this->config);
        // \Swoole\Coroutine\Run(function () {
            // $this->initServer($this->config);
            // $config = $this->config->toArray();
            // Coroutine::create(function () use ($config) {
            //     if (! $this->mainServerStarted) {
            //         $this->mainServerStarted = true;
            //         $this->eventDispatcher->dispatch(new MainCoroutineServerStart('main', $this->server, $config));
            //     }
            //     echo 'do something';
            //     $this->eventDispatcher->dispatch(new CoroutineServerStart('coroutine', $this->server, $config));
            //     $this->server->start();
            //     $this->eventDispatcher->dispatch(new CoroutineServerStop('coroutine', $this->server));
            // });
        // });
    }

    /**
     * @return \Swoole\Coroutine\Http\Server|\Swoole\Coroutine\Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param Config $config
     * @return ServerInterface
     */
    public function setServer(Config $config): ServerInterface
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param mixed $server
     */
    public static function isCoroutineServer($server): bool
    {
        return $server instanceof Coroutine\Http\Server || $server instanceof Coroutine\Server;
    }

    protected function initServer(Config $config): void
    {
        $this->server = $this->makeServer(...$config->take('type', 'host', 'port', 'mode', 'socket'));
        
        $this->server->set($config->getSetting());
    
        $this->registerSwooleEvents($this->server, $config->getCallback());

        $this->server->start();
    }


    /**
     * @param \Swoole\Server\Port|SwooleServer $server
     */
    protected function registerSwooleEvents($server, array $events): void
    {
        foreach ($events as $event => $callback) {
            $server->on($event, $callback);
        }
    }

    protected function bindServerCallback(int $type, array $callbacks)
    {
        switch ($type) {
            case ServerInterface::SERVER_HTTP:
                if (isset($callbacks[Event::ON_REQUEST])) {

                    [$handler, $method] = $this->getCallbackMethod(Event::ON_REQUEST, $callbacks);

                    if ($this->server instanceof \Swoole\Coroutine\Http\Server) {
                        $this->server->handle('/', static function ($request, $response) use ($handler, $method) {
                            Coroutine::create(static function () use ($request, $response, $handler, $method) {
                                $handler->{$method}($request, $response);
                            });
                        });
                    }
                }
                return;
            case ServerInterface::SERVER_WEBSOCKET:
                if (isset($callbacks[Event::ON_HAND_SHAKE])) {
                    [$handler, $method] = $this->getCallbackMethod(Event::ON_HAND_SHAKE, $callbacks);
                    if ($this->server instanceof \Swoole\Coroutine\Http\Server) {
                        $this->server->handle('/', [$handler, $method]);
                    }
                }
                return;
            case ServerInterface::SERVER_BASE:
                return;
        }

        throw new \RuntimeException('Server type is invalid or the server callback does not exists.');
    }

    protected function getCallbackMethod(string $callack, array $callbacks): array
    {
        $handler = $method = null;
        if (isset($callbacks[$callack])) {
            [$class, $method] = $callbacks[$callack];
            $handler = new $class($this->container);
        }
        return [$handler, $method];
    }


    protected function makeServer($type, $host, $port, int $mode, int $socket)
    {
        switch ($type) {
            case ServerInterface::SERVER_HTTP:
                return new \Swoole\Http\Server($host, $port, $mode, $socket);
            case ServerInterface::SERVER_WEBSOCKET:
                return new \Swoole\WebSocket\Server($host, $port, $mode, $socket);
            case ServerInterface::SERVER_BASE:
                return new \Swoole\Server($host, $port, $mode, $socket);
        }
        throw new \RuntimeException('Server type is invalid.');
    }


    protected function makePort($type, $host, $port)
    {
        switch ($type) {
            case ServerInterface::SERVER_HTTP:
            case ServerInterface::SERVER_WEBSOCKET:
                return new Coroutine\Http\Server($host, $port, false, true);
            case ServerInterface::SERVER_BASE:
                return new Coroutine\Server($host, $port, false, true);
        }

        throw new \RuntimeException('Server type is invalid.');
    }
}
