<?php

use Wrap\WorkermanWrap;

require './vendor/autoload.php';

$worker = new WorkermanWrap(1, 5, [], 'websocket://0.0.0.0:2346');
$worker->start();
