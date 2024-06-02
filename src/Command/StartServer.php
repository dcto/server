<?php

namespace VM\Server\Command;

use VM\Server\ServerFactory;
use VM\Server\Entry\EventDispatcher;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableSeparator;
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
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName(_APP_);
        $this->setDescription(sprintf('Start varimax [%s] server.', _APP_));
        $application = new Application('Varimax Server', 'v1.0');
        $application->add($this);
        $application->run(new ArrayInput([_APP_]));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkEnvironment($output);
        $io = new SymfonyStyle($input, $output);

        $serverFactory = new ServerFactory($this->container);
        $serverFactory->setEventDispatcher($this->container->get(EventDispatcher::class));
        $serverFactory->setLogger($this->container->get('log'));
        
        foreach($this->container->config->get('server', []) as $name => $config){
            $server = $serverFactory->getServer(is_string($name) ? $name : 'swoole');
            
            $config['callback']['start'] ??= function (\Swoole\Server $server) use($io) {
                $io->definitionList(
                    "Varimax Server:",
                    ['application'=>_APP_],
                    ['listen_on'=>$server->host.':'.$server->port],['master_id'=>$server->master_pid], new TableSeparator(),
                    ...array_chunk(array_filter($server->setting), 1, true)
                );

                if($this->container->config->get('crontab', [])) {
                    if (!class_exists(\VM\Crontab\CrontabDispatcher::class)) throw new \RuntimeException('Please composer require varimax/crontab first.');
                    call_user_func([new \VM\Crontab\CrontabDispatcher($this->container), 'handle']);
                }
            };
            
            if ($config['type'] == ServerInterface::SERVER_HTTP){
                $config['callback']['request'] ??= [new \VM\Server\Handler\Request($this->container), 'onRequest'];
            }

            \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

            $server->config($config)->start();
        }
        return 0;
    }
    
    private function checkEnvironment(OutputInterface $output)
    {
        if (! extension_loaded('swoole')) {
            $output->writeln("<error>Error: Unable to load Swoole extension.</error>");
            return false;
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
