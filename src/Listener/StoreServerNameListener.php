<?php
 
namespace VM\Server\Listener;

use VM\Server\Event\CoroutineServerStart;
use VM\Utils\Context;

class StoreServerNameListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            CoroutineServerStart::class,
        ];
    }

    /**
     * @param CoroutineServerStart $event
     */
    public function process(object $event)
    {
        $serverName = $event->name;
        if (! $serverName) {
            return;
        }
        Context::set('__vm__.server.name', $serverName);
    }
}
