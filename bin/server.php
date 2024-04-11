<?php
/**
 * Varimax The Slim PHP Frameworks.
 */
define('_APP_', $argc == 2 ? end($argv) : 'app');

require 'vendor/autoload.php';

use VM\Application;
use VM\Server\Command\StartServer;

new StartServer(Application::getInstance());

