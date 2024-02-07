<?php
 
namespace VM\Server\Event;

class HttpRequest 
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
        $this->container->request->setContent();
        $this->SwooleResponse($response,  $this->container->dispatch());
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

        /*
         * Cookies
         * This part maybe only supports of http-message component.
         */
        if (method_exists($response, 'getCookies')) {
            foreach ((array) $response->getCookies() as $domain => $paths) {
                foreach ($paths ?? [] as $path => $item) {
                    foreach ($item ?? [] as $name => $cookie) {
                        
                        if (get_class_methods($cookie) == ['isRaw', 'getValue', 'getName', 'getExpiresTime', 'getPath', 'getDomain', 'isSecure', 'isHttpOnly', 'getSameSite']) {
                            $value = $cookie->isRaw() ? $cookie->getValue() : rawurlencode($cookie->getValue());
                            $swooleResponse->rawcookie($cookie->getName(), $value, $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly(), (string) $cookie->getSameSite());
                        }
                    }
                }
            }
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
            $swooleResponse->write($response->getContent());
            $swooleResponse->status($response->getStatusCode(), $response->getReasonPhrase());
        }

        $swooleResponse->end();
    }
}
