<?php
 
namespace VM\Server\Listener;


use VM\Framework\Event\AfterWorkerStart;
use VM\Framework\Event\OnManagerStart;
use VM\Framework\Event\OnStart;
use VM\Process\Event\BeforeProcessHandle;
use Psr\Container\ContainerInterface;

class InitProcessTitleListener implements ListenerInterface
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $dot = '.';

    public function __construct(ContainerInterface $container)
    {
        if ($container->has(ConfigInterface::class)) {
            if ($name = $container->get(ConfigInterface::class)->get('app_name')) {
                $this->name = $name;
            }
        }
    }

    public function listen(): array
    {
        return [
            OnStart::class,
            OnManagerStart::class,
            AfterWorkerStart::class,
            BeforeProcessHandle::class,
        ];
    }

    public function process(object $event)
    {
        $array = [];
        if ($this->name !== '') {
            $array[] = $this->name;
        }

        if ($event instanceof OnStart) {
            $array[] = 'Master';
        } elseif ($event instanceof OnManagerStart) {
            $array[] = 'Manager';
        } elseif ($event instanceof AfterWorkerStart) {
            if ($event->server->taskworker) {
                $array[] = 'TaskWorker';
                $array[] = $event->workerId;
            } else {
                $array[] = 'Worker';
                $array[] = $event->workerId;
            }
        } elseif ($event instanceof BeforeProcessHandle) {
            $array[] = $event->process->name;
            $array[] = $event->index;
        }

        if ($title = implode($this->dot, $array)) {
            $this->setTitle($title);
        }
    }

    protected function setTitle(string $title)
    {
        if ($this->isSupportedOS()) {
            @cli_set_process_title($title);
        }
    }

    protected function isSupportedOS(): bool
    {
        return ! in_array(PHP_OS, [
            'Darwin',
        ]);
    }
}
