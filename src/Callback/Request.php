<?php
 
namespace VM\Server\Callback;

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
     * @var string
     */
    protected $serverName;

    public function __construct($container)
    {
        $this->container = $container;
    }


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
            $this->SwooleResponse($response,  $this->container->dispatch());
        }catch(\Throwable $e){
            $this->container->log->error($e);
            if ($e->getCode() < 600) {
                $response->status($e->getCode());
                $response->end($e->getMessage());
            }else{
                $response->status(500);
                $response->end('Internal Server Error');
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
    
    /**
    * Swoole Response
    * @param \Swoole\Http\Response $swooleResponse
    * @param \VM\Http\Response $response  
    * @return void 
    */
    protected function SwooleResponse(\Swoole\Http\Response $swooleResponse, \VM\Http\Response $response): void
    {
        /*
         * Headers
         */
        foreach ($response->headers() as $key => $value) {
            $swooleResponse->header($key, implode(';', $value));
        }

        /**
         * Cookies
         */
        foreach ($response->getCookies() as $cookie) {
            /**
             * @var \Symfony\Component\HttpFoundation\Cookie $cookie 
             */ 
             $swooleResponse->setcookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly(), (string) $cookie->getSameSite());
        }
        
        /*
         * Trailers
         */
        if (method_exists($response, 'getTrailers') && method_exists($swooleResponse, 'trailer')) {
            foreach ($response->getTrailers() ?? [] as $key => $value) {
                $swooleResponse->trailer($key, $value);
            }
        }

        /**
         * Response with swoole
         */
        if ($response->getResponse()){
            $response->getContent() && $swooleResponse->write($response->getContent());
            $swooleResponse->status($response->getStatusCode(), $response->getReasonPhrase());
        }

        $swooleResponse->end();
    }
}
