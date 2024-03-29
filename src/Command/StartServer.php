<?php

namespace VM\Server\Command;


use Psr\Container\ContainerInterface;


use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableSeparator;

use VM\Server\Server;
use VM\Server\ServerFactory;
use VM\Server\ServerInterface;
use VM\Server\Entry\EventDispatcher;

class StartServer extends Command
{
    /**
     * @var ContainerInterface|\VM\Application
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
                
            $config = array_replace_recursive($this->defaultConfig(),  $this->container->config->get('server', []) );
            $serverFactory->configure($config);

            /**
             * @var \Swoole\Server $server
             */
            $server = $serverFactory->getServer()->getServer();

            $io = new SymfonyStyle($input, $output);
           
            if (!$server->getCallback('start')){
                $server->on('start', function ($server) use($io) {
                    $io->definitionList(
                        "Varimax Server:",
                        ['listen_on'=>$server->host.':'.$server->port],['master_id'=>$server->master_pid],
                        new TableSeparator(),
                        "Server Infomation:",
                        ...array_chunk(array_filter($server->setting), 1, true)
                    );
                });
            }
            if ($server instanceof \Swoole\Http\Server && !$server->getCallback('request')){
                $server->on('request', [new \VM\Server\Callback\Request($this->container), 'onRequest']);
            }

            if ($server instanceof \Swoole\WebSocket\Server && !$server->getCallback('message')){
                $server->on('message', [new \VM\Server\Callback\Message($this->container), 'onMessage']);
            }
 
            foreach($this->container->config->get('crontab', []) as $crontab){
            //    $v = $this->container->make(ParserTime::class)->parse('* * 2 * * *');

            //    print_r($v);
            }

            // $server->tick(1000, function() use($output){
                // $output->writeln('<info>['.date('Y-m-d H:i:s.u', microtime()).'] Crontab Task [Test::class] Executed.</info>');
            //     echo  date_create()->format('Uv').PHP_EOL;
            // });
            
            //Support Coroutine
            \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);

            $serverFactory->start();
        }
        return 0;
    }


    
    private function defaultConfig(){
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
                    'callbacks' => [],
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
        // $useShortname = ini_get_all('swoole')['swoole.use_shortname']['local_value'];
        // $useShortname = strtolower(trim(str_replace('0', '', $useShortname)));
        // if (! in_array($useShortname, ['', 'off', 'false'], true)) {
        //     $output->writeln("<error>Error: Swoole short function names must be disabled before the server starts, please set swoole.use_shortname='Off' in your php.ini.</error>");
        //     // exit(SIGTERM);
        //     return false;
        // }
        return true;
    }
}
