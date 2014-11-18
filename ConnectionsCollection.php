<?php


namespace MiksIr\proxy;

/**
 * Class ConnectionsCollection
 * @package MiksIr\proxy
 * Набор соединений, с которыми работаем
 */
class ConnectionsCollection {
    const SELECT_TIMEOUT = 0;

    protected $sockets = [];
    /** @var Connection[] */
    protected $connections = [];

    public function add(Connection $connection)
    {
        $this->sockets[$connection->id] = $connection->getSocket();
        $this->connections[$connection->id] = $connection;
    }

    public function remove(Connection $connection)
    {
        unset($this->sockets[$connection->id]);
        unset($this->connections[$connection->id]);
        //gc_collect_cycles();
    }

    public function loop()
    {
        while($this->process()) {};
    }

    public function process()
    {
        $read = [];
        $write = [];
        $except = [];

        foreach ($this->connections as $connection) {
            $mode = $connection->mode();
            if ($mode & Connection::READ) {
                $read[] = $connection->getSocket();
            }
            if ($mode & Connection::WRITE) {
                $write[] = $connection->getSocket();
            }
        }

        $result = socket_select($read, $write, $except, self::SELECT_TIMEOUT);

        foreach ($read as $socket) {
            $id = array_search($socket, $this->sockets, true);
            if ($id !== false) {
                $this->connections[$id]->readSocket();
            }
        }

        foreach ($write as $socket) {
            $id = array_search($socket, $this->sockets, true);
            if ($id !== false) {
                $this->connections[$id]->writeSocket();
            }
        }

        return true;
    }

}