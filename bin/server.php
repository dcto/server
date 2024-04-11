<?php
require 'vendor/autoload.php';

use VM\Application;
use VM\Server\Command\StartServer;

new StartServer(Application::getInstance());

//define _APP_ constants of app name
define('_APP_', $argc == 2 ? end($argv) : 'app');