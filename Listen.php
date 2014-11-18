<?php


namespace MiksIr\proxy;

/**
 * Class Listen
 * @package MiksIr\proxy
 * Слушаем входящие соединения
 */
class Listen extends Connection {
    public $address = '127.0.0.1';
    public $port = 8181;

    /**
     * @throws Exception
     * Создаем сокет для входящих соединений и добавляем его в коллекцию
     */
    public function create()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket == false) {
            $this->logger->log(LoggerAbstract::CRITICAL, $error = "Can't create socket", ['caller' => __CLASS__.":".__LINE__]);
            throw new Exception($error);
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (socket_bind($this->socket, $this->address, $this->port) === false) {
            $this->logger->log(LoggerAbstract::CRITICAL, $error = "Can't bind socket: ".socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__.":".__LINE__]);
            throw new Exception($error);
        }

        if (socket_listen($this->socket) === false) {
            $this->logger->log(LoggerAbstract::CRITICAL, $error = "Can't listen socket: ".socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__.":".__LINE__]);
            throw new Exception($error);
        }

        $this->logger->log(LoggerAbstract::INFO, "Listen socket created {$this->address}:{$this->port}", ['caller' => __CLASS__.":".__LINE__]);

        parent::create();
    }

    /**
     * Если есть доступные данные для чтения - значит пришло новое соединение. Получаем его и создаем класс клиента
     */
    public function readSocket()
    {
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::readSocket", ['caller' => __CLASS__.":".__LINE__]);

        $new_socket = socket_accept($this->socket);
        if ($new_socket !== false) {
            $client = new Client($this->collection, $this->logger);
            $client->setSocket($new_socket);
            $client->create();
        }
    }

    public function writeSocket()
    {
        throw new Exception('Только чтение');
    }

    public function mode()
    {
        return self::READ;
    }

    /**
     * Забрать уже полученные данные (накапливаются в классе)
     * @param bool $clean
     * @return mixed
     */
    public function getData($clean = true)
    {
        return;
    }

    /**
     * Отправить
     * @param $buffer
     * @return mixed
     */
    public function putData($buffer)
    {
        return;
    }
}