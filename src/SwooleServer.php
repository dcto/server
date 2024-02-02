<?php

namespace VM\Server;

use Swoole\Coroutine;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

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
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ServerConfig
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
     * @var bool
     */
    protected $mainServerStarted = false;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EventDispatcherInterface $dispatcher) {
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $dispatcher;
    }

    public function init(ServerConfig $config): ServerInterface
    {
        $this->config = $config;
        return $this;
    }

    public function start()
    {
        file_put_contents('runtime/varimax.pid', getmypid());

        \Swoole\Coroutine\Run(function () {
            $this->initServer($this->config);
            $servers = ServerManager::list();
            $config = $this->config->toArray();
            foreach ($servers as $name => [$type, $server]) {
                Coroutine::create(function () use ($name, $server, $config) {
                    if (! $this->mainServerStarted) {
                        $this->mainServerStarted = true;
                        $this->eventDispatcher->dispatch(new MainCoroutineServerStart($name, $server, $config));
                    }
                    $this->eventDispatcher->dispatch(new CoroutineServerStart($name, $server, $config));
                    $server->start();
                    $this->eventDispatcher->dispatch(new CoroutineServerStop($name, $server));
                });
            }
        });

    }

    /**
     * @return \Swoole\Coroutine\Http\Server|\Swoole\Coroutine\Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param mixed $server
     */
    public static function isCoroutineServer($server): bool
    {
        return $server instanceof Coroutine\Http\Server || $server instanceof Coroutine\Server;
    }

    protected function initServer(ServerConfig $config): void
    {
        $servers = $config->getServers();
        foreach ($servers as $server) {
            if (! $server instanceof Port) {
                continue;
            }
            $name = $server->getName();
            $type = $server->getType();
            $host = $server->getHost();
            $port = $server->getPort();
            $callbacks = array_replace($config->getCallbacks(), $server->getCallbacks());

            $this->server = $this->makeServer($type, $host, $port);
            $settings = array_replace($config->getSettings(), $server->getSettings());
            $this->server->set($settings);

            $this->bindServerCallbacks($type, $name, $callbacks);

            ServerManager::add($name, [$type, $this->server, $callbacks]);
        }
    }

    protected function bindServerCallbacks(int $type, string $name, array $callbacks)
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
                die('ServerInterface::SERVER_BASE');
                // if (isset($callbacks[Event::ON_RECEIVE])) {
                //     [$connectHandler, $connectMethod] = $this->getCallbackMethod(Event::ON_CONNECT, $callbacks);
                //     [$receiveHandler, $receiveMethod] = $this->getCallbackMethod(Event::ON_RECEIVE, $callbacks);
                //     [$closeHandler, $closeMethod] = $this->getCallbackMethod(Event::ON_CLOSE, $callbacks);
                //     if ($this->server instanceof \Swoole\Coroutine\Server) {
                //         $this->server->handle(function (Coroutine\Server\Connection $connection) use ($connectHandler, $connectMethod, $receiveHandler, $receiveMethod, $closeHandler, $closeMethod) {
                //             if ($connectHandler && $connectMethod) {
                //                 parallel([static function () use ($connectHandler, $connectMethod, $connection) {
                //                     $connectHandler->{$connectMethod}($connection, $connection->exportSocket()->fd);
                //                 }]);
                //             }
                //             while (true) {
                //                 $data = $connection->recv();
                //                 if (empty($data)) {
                //                     if ($closeHandler && $closeMethod) {
                //                         parallel([static function () use ($closeHandler, $closeMethod, $connection) {
                //                             $closeHandler->{$closeMethod}($connection, $connection->exportSocket()->fd);
                //                         }]);
                //                     }
                //                     $connection->close();
                //                     break;
                //                 }
                //                 // One coroutine at a time, consistent with other servers
                //                 parallel([static function () use ($receiveHandler, $receiveMethod, $connection, $data) {
                //                     $receiveHandler->{$receiveMethod}($connection, $connection->exportSocket()->fd, 0, $data);
                //                 }]);
                //             }
                //         });
                //     }
                // }
                return;
        }

        throw new \RuntimeException('Server type is invalid or the server callback does not exists.');
    }

    protected function getCallbackMethod(string $callack, array $callbacks): array
    {
        $handler = $method = null;
        if (isset($callbacks[$callack])) {
            [$class, $method] = $callbacks[$callack];
            $handler = $this->container->make($class);
        }
        return [$handler, $method];
    }

    protected function makeServer($type, $host, $port)
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
