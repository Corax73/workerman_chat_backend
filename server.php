<?php

use Wrap\WorkermanWrap;

require './vendor/autoload.php';

$countConnections = 1;
$messageDisplayTime = 5;
$rooms = [];
$socketName = 'websocket://0.0.0.0:2346';

$worker = new WorkermanWrap($countConnections, $messageDisplayTime, $rooms, $socketName);
$worker->start();
