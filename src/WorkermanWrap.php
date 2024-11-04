<?php

namespace Wrap;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WorkermanWrap extends Worker
{
    public function __construct(private readonly int $countConnections, private array $rooms, string $socket_name)
    {
        parent::__construct($socket_name);
        $this->count = $this->countConnections;
        $this->onMessage = function (TcpConnection $connection, string $data) {
            if (!empty($data) && $arData = json_decode($data, true)) {
                if (
                    isset($arData['room_id']) &&
                    $arData['room_id'] &&
                    isset($arData['msg']) &&
                    $arData['msg'] &&
                    !isset($arData['go_to_room']) &&
                    isset($this->rooms[$arData['room_id']]) &&
                    $this->rooms[$arData['room_id']]
                ) {
                    foreach ($this->rooms[$arData['room_id']] as $conn) {
                        $conn->send($arData['msg']);
                    }
                } elseif (isset($arData['go_to_room']) && $arData['go_to_room']) {
                    if (!isset($this->rooms[$arData['go_to_room']])) {
                        $this->rooms[$arData['go_to_room']] = [];
                    }
                    array_push($this->rooms[$arData['go_to_room']], $connection);
                    var_dump($this->rooms);
                    if (!property_exists($connection, 'rooms')) {
                        $connection->rooms = [];
                    }
                    $connection->rooms[] = $arData['go_to_room'];
                    $connection->send('your room is № ' . $arData['go_to_room']);
                    foreach ($this->rooms[$arData['go_to_room']] as $conn) {
                        $conn->send("User $connection->id joined \n");
                    }
                }
            }
        };
        $this->onConnect = function (TcpConnection $connection) {
            $this->connections[$connection->id] = $connection;
            $connection->send('Room list: ' . implode(',', array_keys($this->rooms)) . '\\n');
        };
    }

    public function start()
    {
        Worker::runAll();
    }
}
