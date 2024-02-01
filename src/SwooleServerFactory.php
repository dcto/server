<?php

namespace VM\Server;

use Psr\Container\ContainerInterface;

class SwooleServerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $factory = $container->get(ServerFactory::class);

        return $factory->getServer()->getServer();
    }
}
