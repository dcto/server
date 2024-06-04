<?php
 
namespace VM\Server\Handler;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Http\Request;
use Swoole\Http\Response;

class WebSocket 
{
    /**
     * @var \VM\Application
     */
    protected $container;

    public function __construct(\VM\Application $container)
    {
        $this->container = $container;
    }

    public function Open(Server $server, Request $request)
    {
        $this->container->get('log')->info('WebSocket Connection: ['.$request->fd.']', (array) $request);
    }

    public function Message(Server $server, Frame $frame)
    {
        $this->container->get('log')->info('Received Message: '.$frame->data);
        $server->push($frame->fd, json_encode(['hello', $frame->data]));
    }
    
    public function HandShake(Request $request, Response $response)
    {

    }
}