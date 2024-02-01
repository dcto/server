<?php
 
namespace VM\Server\Listener;

use VM\Contract\StdoutLoggerInterface;
use VM\Event\Contract\ListenerInterface;
use VM\Framework\Event\AfterWorkerStart;
use VM\Server\Event\MainCoroutineServerStart;
use VM\Server\Server;
use VM\Server\ServerManager;
use Swoole\Server\Port;

class AfterWorkerStartListener implements ListenerInterface
{
    /**
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            AfterWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event)
    {
        /** @var AfterWorkerStart|MainCoroutineServerStart $event */
        $isCoroutineServer = $event instanceof MainCoroutineServerStart;
        if ($isCoroutineServer || $event->workerId === 0) {
            /** @var Port|\Swoole\Coroutine\Server $server */
            foreach (ServerManager::list() as $name => [$type, $server]) {
                $listen = $server->host . ':' . $server->port;
                $type = value(function () use ($type, $server) {
                    switch ($type) {
                        case Server::SERVER_BASE:
                            $sockType = $server->type;
                            // type of Swoole\Coroutine\Server is equal to SWOOLE_SOCK_UDP
                            if ($server instanceof \Swoole\Coroutine\Server || in_array($sockType, [SWOOLE_SOCK_TCP, SWOOLE_SOCK_TCP6])) {
                                return 'TCP';
                            }
                            if (in_array($sockType, [SWOOLE_SOCK_UDP, SWOOLE_SOCK_UDP6])) {
                                return 'UDP';
                            }
                            return 'UNKNOWN';
                        case Server::SERVER_WEBSOCKET:
                            return 'WebSocket';
                        case Server::SERVER_HTTP:
                        default:
                            return 'HTTP';
                    }
                });
                $serverType = $isCoroutineServer ? ' Coroutine' : '';
                $this->logger->info(sprintf('%s%s Server listening at %s', $type, $serverType, $listen));
            }
        }
    }
}
