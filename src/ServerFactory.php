<?php
 
namespace VM\Server;

use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use VM\Server\Entry\EventDispatcher;

class ServerFactory
{
    
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ServerInterface
     */
    protected $server;

    /**
     * @var null|LoggerInterface
     */
    protected $logger;

    /**
     * @var null|EventDispatcherInterface
     */
    protected $eventDispatcher;


    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function start()
    {
        return $this->getServer()->start();
    }

    public function getServer($name = null): ServerInterface
    {
        if (!$this->server instanceof ServerInterface) {
            $serverName = sprintf(__NAMESPACE__.'\%sServer', ucfirst($name));
            $this->server = new $serverName(
                $this->container,
                $this->getLogger(),
                $this->getEventDispatcher()
            );
        }
        return $this->server;
    }

    public function setServer($server): self
    {
        $this->server = $server;
        return $this;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            return $this->eventDispatcher;
        }
        return $this->getDefaultEventDispatcher();
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }
        return $this->getDefaultLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    private function getDefaultEventDispatcher(): EventDispatcher
    {
        return new EventDispatcher();
    }

    private function getDefaultLogger(): LoggerInterface
    {
        return $this->container->get('log');
    }
}
