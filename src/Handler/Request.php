<?php
 
namespace VM\Server\Handler;

class Request 
{
    /**
     * @var \VM\Application
     */
    protected $container;

    /**
     * @var HttpDispatcher
     */
    protected $dispatcher;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var string
     */
    protected $serverName;

    public function __construct($container)
    {
        $this->container = $container;
        $this->response = new Response();
    }


    /**
     * Handle Request Handle
     */
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void
    {
        try{
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
            
            $psr7Response = $this->container->dispatch();

        }catch(\ErrorException $e){
            $this->container->log->emergency($e);
        
        }catch(\Exception $e){
            $this->container->log->critical($e);

        }catch(\Throwable $e){
            $this->container->log->error($e);
        
        }finally{
            if (!isset($psr7Response)) {
                return;
            }

            if ($request->getMethod() === 'HEAD') {
                $this->response->emit($psr7Response, $response, false);
            } else {
                $this->response->emit($psr7Response, $response, true);
            }
        }
    }

    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @return $this
     */
    public function setServerName(string $serverName)
    {
        $this->serverName = $serverName;
        return $this;
    }
}
