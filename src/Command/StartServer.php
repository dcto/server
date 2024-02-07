<?php

namespace VM\Server\Command;

use VM\Server\ServerFactory;
use Psr\Container\ContainerInterface;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use VM\Server\Entry\EventDispatcher;
use VM\Server\Server;
use VM\Server\ServerInterface;

class StartServer extends Command
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->setDescription('Start varimax servers.');
        parent::__construct('start');
        $application = new Application('Varimax Server', 'v1.0');
        $application->add($this);
        $application->run();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->checkEnvironment($output) ){

            $serverFactory = $this->container->make(ServerFactory::class, ['container'=>$this->container])
                ->setEventDispatcher($this->container->make(EventDispatcher::class))
                ->setLogger($this->container->get('log'));
            $this->container->config->set('server', $this->defaultConfig(new SymfonyStyle($input, $output)));

            $serverFactory->configure($this->container->config->get('server'));

            // Coroutine::set(['hook_flags' => swoole_hook_flags()]);
            $serverFactory->start();
        }
        return 0;
    }


    
    private function defaultConfig(SymfonyStyle $io){
        return [
            'type' => Server::class,
            'mode' => SWOOLE_BASE,
            'servers' => [
                [
                    'name' => 'http',
                    'type' => ServerInterface::SERVER_HTTP,
                    'host' => '0.0.0.0',
                    'port' => 8620,
                    'sock_type' => SWOOLE_SOCK_TCP,
                    'callbacks' => [
                        'start' => function(\Swoole\Server $server) use( $io){
                            $io->horizontalTable(['varimax_server_listen'] + array_keys($server->setting), [[$server->host.':'.$server->port]+array_values($server->setting)]);
                        },
                        // 'workerStart' => function (\Swoole\Server $server, int $workerId) {
                        //     printf('varimax server worker[%d] started.' . PHP_EOL, $workerId);
                        // },
                        'request' => [\VM\Http\Server::class, 'onRequest'],
                    ],
                ],
            ],
            'processes' => [
            ],
            'settings' => [
                'enable_coroutine' => true,
                'worker_num' => (getenv('ENV') == 'DEV' || getenv('DEBUG')) ? 1 : swoole_cpu_num(),
                'pid_file' => './runtime/varimax.pid',
                'open_tcp_nodelay' => true,
                'max_coroutine' => 100000,
                'open_http2_protocol' => true,
                'max_request' => 0,
                'socket_buffer_size' => 2 * 1024 * 1024,
            ],
            'callbacks' => [
                // Event::ON_BEFORE_START => [ServerStartCallback::class, 'beforeStart'],
                // Event::ON_WORKER_START => [WorkerStartCallback::class, 'onWorkerStart'],
                // Event::ON_PIPE_MESSAGE => [PipeMessageCallback::class, 'onPipeMessage'],
                // Event::ON_WORKER_EXIT => [WorkerExitCallback::class, 'onWorkerExit'],
            ],
        ];
        
    }

    private function checkEnvironment(OutputInterface $output)
    {
        if (! extension_loaded('swoole')) {
            $output->writeln("<error>Error: Unable to load Swoole extension.</error>");
            return;
        }
        /**
         * swoole.use_shortname = true       => string(1) "1"     => enabled
         * swoole.use_shortname = "true"     => string(1) "1"     => enabled
         * swoole.use_shortname = on         => string(1) "1"     => enabled
         * swoole.use_shortname = On         => string(1) "1"     => enabled
         * swoole.use_shortname = "On"       => string(2) "On"    => enabled
         * swoole.use_shortname = "on"       => string(2) "on"    => enabled
         * swoole.use_shortname = 1          => string(1) "1"     => enabled
         * swoole.use_shortname = "1"        => string(1) "1"     => enabled
         * swoole.use_shortname = 2          => string(1) "1"     => enabled
         * swoole.use_shortname = false      => string(0) ""      => disabled
         * swoole.use_shortname = "false"    => string(5) "false" => disabled
         * swoole.use_shortname = off        => string(0) ""      => disabled
         * swoole.use_shortname = Off        => string(0) ""      => disabled
         * swoole.use_shortname = "off"      => string(3) "off"   => disabled
         * swoole.use_shortname = "Off"      => string(3) "Off"   => disabled
         * swoole.use_shortname = 0          => string(1) "0"     => disabled
         * swoole.use_shortname = "0"        => string(1) "0"     => disabled
         * swoole.use_shortname = 00         => string(2) "00"    => disabled
         * swoole.use_shortname = "00"       => string(2) "00"    => disabled
         * swoole.use_shortname = ""         => string(0) ""      => disabled
         * swoole.use_shortname = " "        => string(1) " "     => disabled.
         */
        $useShortname = ini_get_all('swoole')['swoole.use_shortname']['local_value'];
        $useShortname = strtolower(trim(str_replace('0', '', $useShortname)));
        if (! in_array($useShortname, ['', 'off', 'false'], true)) {
            $output->writeln("<error>Error: Swoole short function names must be disabled before the server starts, please set swoole.use_shortname='Off' in your php.ini.</error>");
            // exit(SIGTERM);
            return false;
        }
        return true;
    }
}
