<?php
 
namespace VM\Server\Handler;

class Message 
{
    /**
     * @var \VM\Application
     */
    protected $container;

    public function __construct(\VM\Application $container)
    {
        $this->container = $container;
    }


    public function onMessage(\Swoole\Server $server, int $src_worker_id, mixed $message)
    {
        print_r($message);
    }
}