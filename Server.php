<?php


namespace MiksIr\proxy;

/**
 * Class Server
 * @package MiksIr\proxy
 * Удаленный сервер
 */
class Server extends Connection {
    /** @var Client */
    protected $client_connection;
    protected $server_address = '127.0.0.1';
    protected $server_port = 80;

    protected $socket_connect_time = 0;
    protected $connection_state;
    const CONNECT_TIMEOUT = 30; // sec

    protected $buffer = '';
    protected static $count = 1;

    public function linkClient(Client $client)
    {
        $this->client_connection = $client;
    }

    public function create()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->socket);

        $this->name = "server ".(self::$count++);

        if (@socket_connect($this->socket, $this->server_address, $this->server_port) === false) {
            $error = socket_last_error();
            // Соединение в неблокирующем режиме
            if ($error != SOCKET_EINPROGRESS && $error != SOCKET_EALREADY) {
                $this->logger->log(LoggerAbstract::WARNING, "#{$this->name} can't connect to {$this->server_address}:{$this->server_port}: " . socket_strerror($error), ['caller' => __CLASS__ . ":" . __LINE__]);
                $this->close();
                return;
            }
            // Будем ожидать, пока select() не скажет, что можно писать в сокет - это будет значить, что соединение установлено
            // т.е. продолжение в writeSocket()
            socket_clear_error($this->socket);
            $this->socket_connect_time = time();
            $this->connection_state = self::WRITE;
        } else {
            $this->socket_connect_time = 0;
            $this->connection_state = self::WRITE | self::READ;
        }

        parent::create();
    }

    public function readSocket()
    {
        parent::readSocket();

        if ($this->client_connection) {
            $this->client_connection->putData($this->getData());
        }
    }

    /**
     * Сокет готов, что бы писать
     */
    public function writeSocket()
    {
        if ($this->socket_connect_time) {
            // Мы не завершили connect() еще, нужно проверить ошибки на сокете
            if ($error = socket_last_error($this->socket)) {
                if ($error == SOCKET_EINPROGRESS || $error == SOCKET_EALREADY) {
                    $this->logger->log(LoggerAbstract::DEBUG, "#{$this->name} connecting to {$this->server_address}:{$this->server_port}, " . socket_strerror($error), ['caller' => __CLASS__.":".__LINE__]);
                    return;
                }
                $this->logger->log(LoggerAbstract::WARNING, "#{$this->name} can't connect to {$this->server_address}:{$this->server_port}: " . socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__ . ":" . __LINE__]);
                $this->close();
                $this->socket_connect_time = 0;
                return;
            } else {
                // Если ошибок нет - идем дальше, у нас в буфере уже скорее всего есть, что записывать
                $this->socket_connect_time = 0;
                $this->connection_state = self::READ | self::WRITE;
                $this->logger->log(LoggerAbstract::INFO, "#{$this->name} connected to {$this->server_address}:{$this->server_port}", ['caller' => __CLASS__.":".__LINE__]);
            }
        }

        parent::writeSocket();

        if (strlen($this->write_buffer) === 0) {
            $this->connection_state = self::READ;
            return;
        }
    }

    public function mode()
    {
        return $this->connection_state;
    }

    /**
     * Отправить
     * @param $buffer
     * @return mixed
     */
    public function putData($buffer)
    {
        parent::putData($buffer);
        $this->connection_state = $this->socket_connect_time ? self::WRITE : (self::WRITE | self::READ);
    }

    /**
     * Закрыть соединение
     * @return mixed
     */
    public function close()
    {
        parent::close();
        if ($this->client_connection) {
            $this->client_connection->server_closed();
        }
    }
}