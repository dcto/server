<?php


namespace VM\Server\Callback;


use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;
use VM\Http\Response\StreamFile;

class Response {

    /**
     * @param \VM\Http\Response $response
     * @param \Swoole\Http\Response $swooleResponse
     * @param bool $withContent
     */
    public function emit(ResponseInterface $response, SwooleResponse $swooleResponse, $withContent = true)
    {
        try {
            if (strtolower($swooleResponse->header['Upgrade'] ?? '') === 'websocket') {
                return;
            }
            $this->swooleResponse($swooleResponse, $response);
            $content = $response->getBody();
            if ($content instanceof StreamFile) {
                $swooleResponse->sendfile($content->getFilename());
                return;
            }

            if ($withContent) {
                $swooleResponse->end((string) $content);
            } else {
                $swooleResponse->end();
            }
        } catch (\Throwable $e) {
            error_log($e, 4);
        }
    }

    /**
    * build swoole response object
    * @version 20240511
    */
    protected function swooleResponse(SwooleResponse $swooleResponse, ResponseInterface $response): void
    {
        /*
         * Headers
         */
        foreach ($response->getHeaders() as $key => $value) {
            $swooleResponse->header($key, implode(';', $value));
        }

        /*
         * Cookies
         * This part maybe only supports of ResponseInterface component.
         */
        /** @var \VM\Http\Response $response */
        if (method_exists($response, 'getCookies')) {
            foreach ((array) $response->getCookies() as $cookie) {
                /**
                 * @var \Symfony\Component\HttpFoundation\Cookie $cookie 
                 */ 
                $swooleResponse->setcookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly(), (string) $cookie->getSameSite());
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

        /*
         * Status code
         */
        $swooleResponse->status($response->getStatusCode(), $response->getReasonPhrase());
    }


    /**
    * check if object has all methods
    * @author  dc.To
    * @version 20240511
    */
    protected function methodsExists(object $object, array $methods): bool
    {
        foreach ($methods as $method) {
            if (! method_exists($object, $method)) {
                return false;
            }
        }
        return true;
    }
}