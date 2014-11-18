<?php


namespace MiksIr\proxy;

/**
 * Class Client
 * @package MiksIr\proxy
 * Входящий запрос
 */
class Client extends Connection {
    protected $connection_state;
    /** @var Server */
    protected $server_connection;
    protected static $count = 1;

    public function create()
    {
        if (is_null($this->socket)) {
            throw new Exception('You need to set socket to client using Client::setSocket');
        }

        socket_set_nonblock($this->socket);
        $this->connection_state = self::READ;

        if (socket_getpeername($this->socket, $address, $port) === false) {
            $this->logger->log(LoggerAbstract::WARNING, "Can't find peer address: ".socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__.":".__LINE__]);
        } else {
            $this->name = "client ".(self::$count++)." @{$address}:{$port}";
            $this->logger->log(LoggerAbstract::INFO, "#{$this->name}: new connection", ['caller' => __CLASS__.":".__LINE__]);
        }

        parent::create();
    }

    public function close()
    {
        parent::close();
        if ($this->server_connection) {
            $this->server_connection->close();
        }
    }

    /**
     * Сервер закрыл соединение
     */
    public function server_closed()
    {
        $this->server_connection = null;
        $this->close();
    }

    public function readSocket()
    {
        parent::readSocket();

        if ($this->validate_request()) {
            $this->call_server();

        } elseif (count($this->buffer) >= self::READ_BUFFER) {
            $this->logger->log(LoggerAbstract::WARNING, "#{$this->name}: bad request or buffer too small, request body: {$this->buffer}", ['caller' => __CLASS__.":".__LINE__]);
            // Мы так и не приняли запрос, а буфер уже полный. Можно его увеличить.
            $this->close();
        }
    }

    /**
     * Тут мы проверим - приняли мы весь запрос
     * Для HTTP это CRLFCRLF (или просто LFLF - rfc2616 19.3)
     */
    protected function validate_request()
    {
        return preg_match('/\n\r?\n\r?$/', $this->buffer) && substr($this->buffer, 0, 4) == 'GET ';
    }

    /**
     * Создаем соединение к удаленному серверу и передаем данные
     */
    protected function call_server()
    {
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::call_server #{$this->name}", ['caller' => __CLASS__.":".__LINE__]);

        $this->server_connection = new Server($this->collection, $this->logger);
        $this->server_connection->create();
        $this->server_connection->linkClient($this);

        $this->server_connection->putData($this->getData());
    }

    /**
     * Вызывается, когда удаленная сторона готова получать данные
     */
    public function writeSocket()
    {
        parent::writeSocket();

        if (strlen($this->write_buffer) === 0) {
            $this->connection_state = null;
            return;
        }
    }

    public function mode()
    {
        return $this->connection_state;
    }

    /**
     * Передаем данные для отправки
     * @param string $buffer
     * @return mixed
     */
    public function putData($buffer)
    {
        parent::putData($buffer);
        $this->connection_state = self::WRITE;
    }
}