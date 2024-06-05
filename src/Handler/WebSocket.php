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
        $this->container->request->initialize(
            $request->get ?? [],
            $request->post ?? [],
            [],
            $request->cookie ?? [],
            $request->files ?? [],
            $request->server ?? [],
            $request->rawContent()
        );
        $this->container->request->headers->add($request->header ?? []);
        $this->container->request->setMethod($request->server['request_method'] ?? 'GET');
        $this->container->request->setPathInfo($request->server['path_info'] ?? '');
        
        $this->container->get('log')->info('WebSocket Connection: ['.$request->fd.']', (array) $request);
    }

    public function Message(Server $server, Frame $frame)
    {
        $context = (array) $frame;
        $this->container->get('log')->info('Received Message: ['.$frame->fd.']', $context);
        $server->push($frame->fd,  $this->container->dispatch(...$context)->getContent());
    }
    
    public function HandShake(Request $request, Response $response)
    {

    }
}