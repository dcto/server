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

    public function Connect(Server $server, int $fd, int $reactorId)
    {
        $this->container->get('log')->info(sprintf('Connect: Fd:[%s], ReactorId:[%s]', $fd, $reactorId));
    }

    public function Receive(Server $server, int $fd, int $reactor_id, $data)
    {
        $this->container->get('log')->info(sprintf('Received: Fd:[%s], ReactorId:[%s], Data: %s', $fd, $reactor_id, $data));
    }

    public function Close(Server $server, int $fd,  int $reactorId)
    {
        $this->container->get('log')->info(sprintf('Close: Fd:[%s], ReactorId:[%s]', $fd, $reactorId));
    }
}