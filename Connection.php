<?php


namespace MiksIr\proxy;


abstract class Connection {
    const READ = 1;
    const WRITE = 2;

    const READ_BUFFER = 4096;

    /** @var ConnectionsCollection */
    protected $collection;
    protected $socket;
    protected $logger;

    protected $write_buffer = '';
    protected $buffer = '';

    protected $name;
    public $id;
    private static $idcount = 1;

    public function __construct(ConnectionsCollection $collection, LoggerAbstract $logger)
    {
        $this->collection = $collection;
        $this->logger = $logger;
        $this->id = self::$idcount++;
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::__construct ID#{$this->id}");
    }

    /**
     * @return mixed
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param mixed $socket
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;
    }

    /**
     * Создание сокета
     * @return mixed
     */
    public function create() {
        $this->collection->add($this);
    }

    /**
     * Операция чтения сокета, вызывается после select()
     * @return mixed
     */
    public function readSocket()
    {
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::readSocket #{$this->name}");

        $new_data = socket_read($this->socket, self::READ_BUFFER - count($this->buffer), PHP_BINARY_READ);
        if ($new_data === '') {
            $this->logger->log(LoggerAbstract::INFO, "#{$this->name} connection closed: ".socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__.":".__LINE__]);
            $this->close();
            return;
        }
        if ($new_data === false) {
            $this->logger->log(LoggerAbstract::INFO, "#{$this->name} socket_read error: ".socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__.":".__LINE__]);
            $this->close();
            return;
        }

        $this->buffer .= $new_data;
        $this->logger->log(LoggerAbstract::DEBUG, "#{$this->name} read {$new_data}", ['caller' => __CLASS__.":".__LINE__]);
    }

    /**
     * Операция записи в сокет, вызывается после select()
     * @return mixed
     */
    public function writeSocket()
    {
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::writeSocket #{$this->name}");

        if (strlen($this->write_buffer) !== 0) {
            $size = socket_write($this->socket, $this->write_buffer);
            if ($size === false || socket_last_error($this->socket)) {
                $this->logger->log(LoggerAbstract::WARNING, "#{$this->name} write fail: " . socket_strerror(socket_last_error($this->socket)), ['caller' => __CLASS__ . ":" . __LINE__]);
                $this->close();
                return;
            }

            $this->write_buffer = substr($this->write_buffer, $size);
            $this->logger->log(LoggerAbstract::DEBUG, "#{$this->name} write {$size} bytes", ['caller' => __CLASS__.":".__LINE__]);
        }
    }

    /**
     * Забрать уже полученные данные (накапливаются в классе)
     * @param bool $clean
     * @return mixed
     */
    public function getData($clean = true)
    {
        $buffer = $this->buffer;
        if ($clean) {
            $this->buffer = '';
        }
        return $buffer;
    }

    /**
     * Отправить
     * @param $buffer
     * @return mixed
     */
    public function putData($buffer)
    {
        $this->write_buffer .= $buffer;
    }

    /**
     * Закрыть соединение
     * @return mixed
     */
    public function close()
    {
        $this->logger->log(LoggerAbstract::DEBUG, __CLASS__."::close #{$this->name}");

        $this->buffer = '';
        $this->write_buffer = '';
        $this->collection->remove($this);
        if ($this->socket) {
            socket_close($this->socket);
        }
    }

    abstract public function mode();
}