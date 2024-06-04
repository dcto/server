<?php
 
namespace VM\Server\Handler;

use Swoole\Server;


class Base 
{
    /**
     * @var \VM\Application
     */
    protected $container;

    public function __construct(\VM\Application $container)
    {
        $this->container = $container;
    }

    public function Connect(Server $server, int $fd)
    {
        $this->container->get('log')->info('Connected: ['.$fd.']');
    }

    public function Receive(Server $server, int $fd, int $reactor_id, $data)
    {
        $this->container->get('log')->info(sprintf('Received:[%s] [Reactor: %s]:%s', $fd, $reactor_id, json_encode($data)));
    }

    public function Close(Server $server, int $fd)
    {
        $this->container->get('log')->info('Close: ['.$fd.']');
    }
}