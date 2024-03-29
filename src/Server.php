<?php

namespace VM\Server;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

class Server implements ServerInterface
{
    /**
     * @var bool
     */
    protected $enableHttpServer = false;

    /**
     * @var bool
     */
    protected $enableWebsocketServer = false;

    /**
     * @var SwooleServer
     */
    protected $server;

    /**
     * @var array
     */
    protected $onRequestCallbacks = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EventDispatcherInterface $dispatcher)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $dispatcher;
    }

    public function init(ServerConfig $config): ServerInterface
    {
        $this->initServers($config);

        return $this;
    }

    public function start()
    {
        $this->server->start();
    }

    public function getServer()
    {
        return $this->server;
    }

    protected function initServers(ServerConfig $config)
    {
        $servers = $this->sortServers($config->getServers());

        foreach ($servers as $server) {
            $name = $server->getName();
            $type = $server->getType();
            $host = $server->getHost();
            $port = $server->getPort();
            $sockType = $server->getSockType();
            $callbacks = $server->getCallbacks();

            if (! $this->server instanceof SwooleServer) {
                $this->server = $this->makeServer($type, $host, $port, $config->getMode(), $sockType);
                $callbacks = array_replace($config->getCallbacks(), $callbacks);

                $this->registerSwooleEvents($this->server, $callbacks, $name);
                
                $this->server->set(array_replace($config->getSettings(), $server->getSettings()));
                ServerManager::add($name, [$type, current($this->server->ports)]);

  
            } else {
                /** @var bool|\Swoole\Server\Port $slaveServer */
                $slaveServer = $this->server->addlistener($host, $port, $sockType);
                if (! $slaveServer) {
                    throw new \RuntimeException("Failed to listen server port [{$host}:{$port}]");
                }
                $server->getSettings() && $slaveServer->set(array_replace($config->getSettings(), $server->getSettings()));
                $this->registerSwooleEvents($slaveServer, $callbacks, $name);
                ServerManager::add($name, [$type, $slaveServer]);
            }

            // Trigger beforeStart event.
            if (isset($callbacks[Event::ON_BEFORE_START])) {
                [$class, $method] = $callbacks[Event::ON_BEFORE_START];
                if ($this->container->has($class)) {
                    $this->container->make($class)->{$method}();
                }
            }
        }
    }

    /**
     * @param Port[] $servers
     * @return Port[]
     */
    protected function sortServers(array $servers)
    {
        $sortServers = [];
        foreach ($servers as $server) {
            switch ($server->getType() ?? 0) {
                case ServerInterface::SERVER_HTTP:
                    $this->enableHttpServer = true;
                    if (! $this->enableWebsocketServer) {
                        array_unshift($sortServers, $server);
                    } else {
                        $sortServers[] = $server;
                    }
                    break;
                case ServerInterface::SERVER_WEBSOCKET:
                    $this->enableWebsocketServer = true;
                    array_unshift($sortServers, $server);
                    break;
                default:
                    $sortServers[] = $server;
                    break;
            }
        }

        return $sortServers;
    }

    protected function makeServer(int $type, string $host, int $port, int $mode, int $sockType)
    {
        switch ($type) {
            case ServerInterface::SERVER_HTTP:
                return new SwooleHttpServer($host, $port, $mode, $sockType);
            case ServerInterface::SERVER_WEBSOCKET:
                return new SwooleWebSocketServer($host, $port, $mode, $sockType);
            case ServerInterface::SERVER_BASE:
                return new SwooleServer($host, $port, $mode, $sockType);
        }

        throw new \RuntimeException('Server type is invalid.');
    }

    /**
     * @param \Swoole\Server\Port|SwooleServer $server
     */
    protected function registerSwooleEvents($server, array $events, string $serverName): void
    {

        foreach ($events as $event => $callback) {
            if (! Event::isSwooleEvent($event)) {
                continue;
            }
            if (is_array($callback)) {        
                [$className, $method] = $callback;
                if (array_key_exists($className . $method, $this->onRequestCallbacks)) {
                    $this->logger->warning(sprintf('%s will be replaced by %s. Each server should have its own onRequest callback. Please check your configs.', $this->onRequestCallbacks[$className . $method], $serverName));
                }

                $this->onRequestCallbacks[$className . $method] = $serverName;

                $class = $this->container->make($className, [
                    'container'=>$this->container, 
                    'logger'=>$this->logger, 
                    'dispatcher'=>$this->eventDispatcher
                ]);

                if (method_exists($class, 'setServerName')) {
                    // Override the server name.
                    $class->setServerName($serverName);
                }

                $callback = [$class, $method];
            }
            $server->on($event, $callback);
        }
    }
}
