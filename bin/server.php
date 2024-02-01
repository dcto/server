<?php
require 'vendor/autoload.php';

use VM\Application;
use VM\Server\Command\StartServer;

new StartServer(Application::getInstance());
