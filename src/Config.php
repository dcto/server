<?php

namespace VM\Server;


class Config implements \ArrayAccess
{
    /**
     * @var string
     */
    protected $name = 'http';


    /**
     * @var int
     */
    protected $mode = SWOOLE_PROCESS;

    /**
     * @var int
     */
    protected $type = ServerInterface::SERVER_HTTP;

    /**
     * @var string
     */
    protected $host = '0.0.0.0';

    /**
     * @var int
     */
    protected $port = 8620;

    /**
     * @var int
     */
    protected $socket = SWOOLE_SOCK_TCP;

    /**
     * @var array
     */
    protected $callback = [];

    /**
     * @var array
     */
    protected $setting = [
        'enable_coroutine' => true,
        'worker_num' => 1,
        'pid_file' => './runtime/varimax.pid',
        'open_tcp_nodelay' => true,
        'max_coroutine' => 100000,
        'open_http2_protocol' => true,
        'max_request' => 0,
        'socket_buffer_size' => 2 * 1024 * 1024,
    ];


    public function __construct(array $config = []) 
    {
        $config = self::filter($config);
        $this->name = $config['name'] ?? $this->name;
        $this->type = $config['type'] ?? $this->type;
        $this->host = $config['host'] ?? $this->host;
        $this->port = $config['port'] ?? $this->port;
        $this->socket = $config['socket'] ?? $this->socket;
        $this->callback = $config['callback'] ?? $this->callback;
        $this->setting = array_merge($this->setting, $config['setting'] ?? []);
    }

    public static function build(array $config)
    {
        return new static($config);
    }

    public function take(...$args)
    {
       return array_map(fn($arg)=>$this->$arg, $args);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode)
    {
        $this->mode = $mode;
        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type)
    {
        $this->type = $type;
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host)
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port)
    {
        $this->port = $port;
        return $this;
    }

    public function getSocket(): int
    {
        return $this->socket;
    }

    public function setSocket(int $socket)
    {
        $this->socket = $socket;
        return $this;
    }

    public function getCallback(): array
    {
        return $this->callback;
    }

    public function setCallback(array $callbacks)
    {
        $this->callback = array_merge($this->callback, $callbacks);
        return $this;
    }

    public function getSetting(): array
    {
        return $this->setting;
    }

    public function setSetting(array $settings)
    {
        $this->setting = array_merge($this->setting, $settings);
        return $this;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    function offsetGet($key): mixed
    {
        return $this->$key;
    }

    function offsetSet($key, $value) : void
    {
        $this->$key = $value;
    }

    function offsetExists($key) : bool
    {
        return isset($this->$key);
    }

    function offsetUnset($key) : void
    {
        $this->$key = null;
    }

    private static function filter(array $config): array
    {
        if (($config['type'] ??= 0) === ServerInterface::SERVER_BASE) {
            $default = ['open_http2_protocol' => false,'open_http_protocol' => false];
            $config['setting'] = array_merge($default, $config['setting'] ?? []);
        }

        if (!empty($config['setting']['document_root'])) {
            $config['setting']['enable_static_handler'] ??= true;
        }

        return $config;
    }
}
