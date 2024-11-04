<?php

declare(strict_types=1);

namespace Wrap;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class WorkermanWrap extends Worker
{
    protected string $joinedMsg = "User %s joined";
    public function __construct(private readonly int $countConnections, private array $rooms, string $socketName)
    {
        parent::__construct($socketName);
        $this->count = $this->countConnections;
        $this->onMessage = $this->getOnMessageCallback();
        $this->onConnect = $this->getOnConnectCallback();
    }

    protected function getOnMessageCallback(): callable
    {
        return  function (TcpConnection $connection, string $data) {
            if (!empty($data) && $arData = json_decode($data, true)) {
                if (isset($arData['room_id']) && $arData['room_id'] && isset($arData['msg']) && !isset($arData['go_to_room'])) {
                    $this->sendToRoom($arData['room_id'], $arData['msg']);
                } elseif (isset($arData['go_to_room']) && $arData['go_to_room']) {
                    $this->addToRoom($arData['go_to_room'], $connection->id);
                } elseif (!isset($arData['room_id']) && !isset($arData['go_to_room']) && isset($arData['go_rooms_list'])) {
                    $connection->send($this->getRoomsList());
                }
            }
        };
    }

    protected function getOnConnectCallback(): callable
    {
        return function (TcpConnection $connection) {
            $this->connections[$connection->id] = $connection;
            $connection->send($this->getRoomsList());
        };
    }

    protected function createResponse(string|int $key, mixed $val): string
    {
        return json_encode([$key => $val], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function sendToRoom(string|int $roomCode, string|int $msg): void
    {
        if (!empty($roomCode) && !empty($msg) && isset($this->rooms[$roomCode]) && $this->rooms[$roomCode]) {
            foreach ($this->rooms[$roomCode]['connection_ids'] as $id) {
                $this->connections[$id]->send($this->createResponse($roomCode, $msg));
            }
            if (!isset($this->rooms[$roomCode]['history'])) {
                $this->rooms[$roomCode]['history'] = [];
            }
            $this->rooms[$roomCode]['history'][] = $msg;
        }
    }

    protected function addToRoom(string|int $roomCode, int $connectionId): void
    {
        if (!isset($this->rooms[$roomCode])) {
            $this->rooms[$roomCode] = [];
            $this->rooms[$roomCode]['connection_ids'] = [];
        }
        array_push($this->rooms[$roomCode]['connection_ids'], $connectionId);
        if (!property_exists($this->connections[$connectionId], 'rooms')) {
            $this->connections[$connectionId]->rooms = [];
        }
        $this->connections[$connectionId]->rooms[] = $roomCode;
        $this->connections[$connectionId]->send('your room is № ' . $roomCode);
        if (isset($this->rooms[$roomCode]['history']) && $this->rooms[$roomCode]['history']) {
            $this->connections[$connectionId]->send($this->createResponse($roomCode, $this->rooms[$roomCode]['history']));
        }
        foreach ($this->rooms[$roomCode]['connection_ids'] as $id) {
            $joinedMsg = sprintf($this->joinedMsg, $connectionId);
            $this->rooms[$roomCode]['history'][] = $joinedMsg;
            if (isset($this->connections[$id]) && $this->connections[$id]) {
                $this->connections[$id]->send($this->createResponse($roomCode, $joinedMsg));
            }
        }
    }

    protected function getRoomsList(): string
    {
        return $this->createResponse('rooms_list', array_keys($this->rooms));
    }

    public function start()
    {
        Worker::runAll();
    }
}
