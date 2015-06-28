<?php
namespace StreamSockets;

use StreamSockets\EventManager;

class Server extends EventManager {

    /**
     * @var integer $max_clients
     */
    public $max_clients = 10;

    /**
     * @var boolean $is_listening
     */
    public $is_listening = false;

    /**
     * @var StreamSockets\Socket $socket
     */
    public $socket;

    /**
     * @var array $clients
     */
    private $clients =  array();

    /**
     * @var boolean $read_sockets
     */
    private $read_sockets;

    /**
     * @var array $events
     */
    private $events = array(
        'listen',
        'connect',
        'input',
        'disconnect'
    );

    /**
     * @param StreamSockets\Socket $socket
     */
    public function __construct(\StreamSockets\Socket $socket) 
    {
        set_time_limit(0);
        if ($socket->sock !== false) {
            $this->socket = $socket;
        }
    }

    /**
     * Validates event and registers listener
     *
     * @see    StreamSockets\EventManager 
     * @param  string   $event    The event to add
     * @param  callable $listener The listener to register
     * @return StreamSockets\Server
     */
    public function on($event, callable $listener)
    {
        $event = strtolower(trim($event));
        if (in_array($event, $this->events)) {
            parent::on($event, $listener);
        }

        return $this;
    }

    /**
     * Runs events and collects errors from connected client sockets
     * 
     * @see    StreamSockets\EventManager
     * @param  string $event The event to run, or the entire event chain
     * @param  array  $args  The arguments passed to each listener
     */
    public function run($event = null, array $args = array())
    {
        parent::run($event, $args);

        $this->err = (
            !empty($this->err) ? array_merge($this->socket->err, $this->err) : 
            !empty($this->socket->err) ? $this->socket->err : array()
        );
        if (!empty($this->clients)) {
            for($i = 0; $i < $this->max_clients; $i++) {
                if (empty($this->clients[$i]->err)) continue;
                $this->err[] = $this->clients[$i]->err;
            }
        }
    }

    /**
     * Responsible for running the server's configurable event chain
     * 
     * @return boolean
     */
    public function runOnce()
    {
        $this->run('listen', array($this));
        $this->is_listening = true;

        if($this->socket->select($this->readSockets(), null, null, 5) < 1) {
            return true;
        }

        if(in_array($this->socket->sock, $this->readSockets())) {
            $this->handleConnect();
            $this->handleInput();
        }

        return true;
    }

    /**
     * The main event loop
     */
    public function keepAlive()
    {
        $b = true;
        do {
            $b = $this->runOnce();
        }
        while($b);
    }

    /**
     * Aggregates all sockets created with socket_create, socket_accept
     * 
     * @return array $read_sockets Socket resources
     */
    private function readSockets()
    {
        $this->read_sockets = array();
        $this->read_sockets[0] = $this->socket->sock;
        for($i = 0; $i < $this->max_clients; $i++) {
            if (isset($this->clients[$i])) {
              $this->read_sockets[$i+1] = $this->clients[$i]->sock;
            }
        }

        return $this->read_sockets;
    }

    /**
     * Creates new client socket resources
     */
    private function handleConnect()
    {
        for($i = 0; $i < $this->max_clients; $i++) {
            if(empty($this->clients[$i])) {
                $this->clients[$i] = new Socket();
                $this->clients[$i]->setSocket($this->socket->sock);
                $this->run('connect', array($this, $this->clients[$i], null));
                break;
            }
            else if ($i == ($this->max_clients - 1)) {
                $this->handleErrors(2, 'Client limit reached: max_clients');
            }
        }
    }

    /**
     * Reads data received from clients
     */
    private function handleInput()
    {
        for($i = 0; $i < $this->max_clients; $i++) {
            if(isset($this->clients[$i]) && in_array($this->clients[$i]->sock, $this->readSockets())) {
                $input = $this->clients[$i]->read();
                if ($input == null) {
                    $this->handleDisconnect($this->clients[$i]);
                } else {
                    $input = trim($input);
                    $this->run('input', array($this, $this->clients[$i], $input));
                }
            }
        }
    }

    /**
     * Closes a client connection
     * 
     * @param  socket $client Resource
     */
    public function handleDisconnect(&$client)
    {
        $this->run('disconnect', array($client));
        $client->destroy();
        unset($client);
    }

    /**
     * @param  string $str  The string to escape
     * @param  string $crlf The newline feed to use
     * @return string
     */
    public static function escape($str, $crlf = "\r\n")
    {
        $str = trim($str);
        if ($crlf) $str = $crlf.$str.$crlf;
        return $str;
    }
}