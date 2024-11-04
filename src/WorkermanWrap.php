<?php

declare(strict_types=1);

namespace Wrap;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WorkermanWrap extends Worker
{
    public function __construct(private readonly int $countConnections, private array $rooms, string $socket_name)
    {
        parent::__construct($socket_name);
        $this->count = $this->countConnections;
        $this->onMessage = $this->getOnMessageCallback();
        $this->onConnect = $this->getOnConnectCallback();
    }

    protected function getOnMessageCallback(): callable
    {
        return  function (TcpConnection $connection, string $data) {

            if (!empty($data) && $arData = json_decode($data, true)) {
                if (
                    isset($arData['room_id']) &&
                    $arData['room_id'] &&
                    isset($arData['msg']) &&
                    !isset($arData['go_to_room'])
                ) {
                    $this->sendToRoom($arData['room_id'], $arData['msg']);
                } elseif (isset($arData['go_to_room']) && $arData['go_to_room']) {
                    if (!isset($this->rooms[$arData['go_to_room']])) {
                        $this->rooms[$arData['go_to_room']] = [];
                        $this->rooms[$arData['go_to_room']]['connection_ids'] = [];
                    }
                    array_push($this->rooms[$arData['go_to_room']]['connection_ids'], $connection->id);
                    if (!property_exists($connection, 'rooms')) {
                        $connection->rooms = [];
                    }
                    $connection->rooms[] = $arData['go_to_room'];
                    $connection->send('your room is № ' . $arData['go_to_room']);
                    if (isset($this->rooms[$arData['go_to_room']]['history']) && $this->rooms[$arData['go_to_room']]['history']) {
                        $connection->send(implode("\n", $this->rooms[$arData['go_to_room']]['history']));
                    }
                    foreach ($this->rooms[$arData['go_to_room']]['connection_ids'] as $id) {
                        $joinedMsg = "User $connection->id joined \n";
                        $this->rooms[$arData['go_to_room']]['history'][] = $joinedMsg;
                        if (isset($this->connections[$id]) && $this->connections[$id]) {
                            $this->connections[$id]->send($joinedMsg);
                        }
                    }
                }
            }
        };
    }

    protected function getOnConnectCallback(): callable
    {
        return function (TcpConnection $connection) {
            $this->connections[$connection->id] = $connection;
            $connection->send('Room list: ' . implode(',', array_keys($this->rooms)));
        };
    }

    protected function sendToRoom(string|int $roomCode, string|int $msg): void
    {
        if (
            !empty($roomCode) &&
            !empty($msg) &&
            isset($this->rooms[$roomCode]) &&
            $this->rooms[$roomCode]
        ) {
            foreach ($this->rooms[$roomCode]['connection_ids'] as $id) {
                $this->connections[$id]->send($msg);
            }
            if (!isset($this->rooms[$roomCode]['history'])) {
                $this->rooms[$roomCode]['history'] = [];
            }
            $this->rooms[$roomCode]['history'][] = $msg;
        }
    }

    public function start()
    {
        Worker::runAll();
    }
}
