<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace VM\Server;


use VM\Server\Command\StartServer;
use Swoole\Server as SwooleServer;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                SwooleServer::class => SwooleServerFactory::class,
            ],
            'listeners' => [
                // StoreServerNameListener::class,
                // AfterWorkerStartListener::class,
                // InitProcessTitleListener::class,
            ],
            'commands' => [
                StartServer::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for server.',
                    'source' => __DIR__ . '/../publish/server.php',
                    'destination' =>  './server.php',
                ],
            ],
        ];
    }
}
