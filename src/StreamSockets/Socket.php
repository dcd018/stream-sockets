<?php
namespace StreamSockets;

use StreamSockets\EventManager;

class Socket extends EventManager{

    /**
     * @var integer $max_read
     */
    public $max_read;

    /**
     * @var integer $read_type
     */
    public $read_type;

    /**
     * @var resource $sock
     */
    public $sock;

    /**
     * @var string $ip
     */
    public $ip;

    /**
     * @var integer $port
     */
    public $port;

    /**
     * @var string $mode
     */
    private $mode;

    /**
     * @var integer $domain
     */
    private $domain;

    /**
     * @var integer $type
     */
    private $type;

    /**
     * @var integer $protocol
     */
    private $protocol;

    const ICMP = 0;

    /**
     * @var array $domains
     */
    private $domains = array(
        AF_INET => 'AF_INET',
        AF_INET6 => 'AF_INET6',
        AF_UNIX => 'AF_UNIX'
    );

    /**
     * @var array $types
     */
    private $types = array(
        SOCK_STREAM => 'SOCK_STREAM',
        SOCK_DGRAM => 'SOCK_DGRAM',
        SOCK_SEQPACKET => 'SOCK_SEQPACKET',
        SOCK_RAW => 'SOCK_RAW',
        SOCK_RDM => 'SOCK_RDM'
    );

    /**
     * @var array $protocols
     */
    private $protocols = array(
        self::ICMP => 'ICMP',
        SOL_TCP => 'SOL_TCP',
        SOL_UDP => 'SOL_UDP'
    );

    /**
     * Creates and binds a socket resource to any given port
     * 
     * @param string  $ip       The server's public IP address or subnet
     * @param integer $port     The port to bind the socket to
     * @param integer $domain   The protocol family to be used by the socket
     * @param integer $type     The type of communication to be used by the socket
     * @param integer $protocol The protocol to use when communicating on the returned socket
     */
    public function __construct($ip = null, $port = null, $domain = AF_INET, $type = SOCK_STREAM, $protocol = self::ICMP)
    {
        $this->max_read = 1024;
        $this->read_type = PHP_NORMAL_READ;
        $this->mode = ''; 

        if (!is_null($ip) && !is_null($port)) {
            $this->ip = $ip;
            $this->port = $port;
            $this->domain = $domain;
            $this->type = $type;
            $this->protocol = $protocol;

            try {
                $this->handleValidate();
                $this->handleCreate();
            } catch (Exception $e) {
                if ($this->mode == 'strict')
                    $this->err[] = $e;
            }
        } else {
            $this->sock = null;
        }
    }

    /**
     * Accepts a connection on a client scoket and sets nonblocking mode for file descriptor fd
     * 
     * @param resource $sock The client socket connection to accept 
     */
    public function setSocket(&$sock)
    {
        if (is_null($this->sock)) {
            
            $this->sock = $sock;
            $this->accept();
            $this->nonblock();
            
            // NAT
            if (socket_getpeername($this->sock, $ip, $port) !== false) {
                $this->ip = $ip;
                $this->port = $port;
            }

            // Options
            /*$tcp = socket_get_option($this->sock, SOL_TCP, SO_TYPE);
            $udp = socket_get_option($this->sock, SOL_UDP, SO_TYPE);
            $this->protocol = (!$tcp ? (!$udp ? self::ICMP : SOL_UDP) : SOL_TCP);
            $this->type = (!$tcp ? (!$udp ? '' : $udp) : $tcp);*/
        }
    }

    /**
     * Handles runtime exceptions
     * 
     * @throws Exception
     */
    private function handleValidate()
    {
        if (!in_array($this->domain, array_keys($this->domains))) {
            throw new Exception('Unknown Protocol Family!', 500);
        }
        if (!in_array($this->type, array_keys($this->types))) {
            throw new Exception('Unknown Connection Type!', 500);
        }
        if (!in_array($this->protocol, array_keys($this->protocols))) {
            if (is_string($this->protocol) && ($protocol = getprotobyname(strtolower($this->protocol))) !== false) {
                $this->protocol = $protocol;
            } else {
                throw new Exception('Unknown Protocol!', 500);
            }
        }
    }

    /**
     * Creates, binds and listens for a connection on a socket endpoint
     */
    private function handleCreate()
    {   
        $events = array(
            'socket_create' => array($this->domain, $this->type, $this->protocol),
            'socket_bind' => array(&$this->sock, &$this->ip, &$this->port),
            'socket_getsockname' => array(&$this->sock, &$this->ip, &$this->port),
            'socket_listen' =>array(&$this->sock),
        );

        foreach ($events as $e => $args) {
            $this->exec($e, $args, false);
        }

        $this->resetListeners();
    }

    /**
     * Handles socket event level error caching, can be useful for debugging nonblocking mode
     * 
     * @param  string $e The listener to register
     * @throws Exception Throws an exception if $mode is set to "strict"
     */
    private function handleListener($e)
    {
        $this->on($e, function($res) {
            if ($res === false) {
                $code = socket_last_error();
                $msg = socket_strerror($code);

                // keep event chain running and cache last known errors 
                $this->handleErrors(2, '['.$code.']:'.$msg);

                if ($this->mode == 'strict') {
                    throw new Exception($msg, $code);
                }
            }
        });
    }

    /**
     * Sets error handler and runs a socket event
     * 
     * @param  string   $event           The event to run
     * @param  array    $args            The arguments to pass to each listener
     * @param  boolean  $reset_listeners Resets the event chain
     * @return resource The result of running a socket event
     */
    public function exec($event, array $args = array(), $reset_listeners = true)
    {
        set_error_handler(array($this, 'handleErrors'));
        
        $this->handleListener($event);
        $res = call_user_func_array($event, $args);
        
        $ref = array('socket_create', 'socket_accept');
        if (in_array($event, $ref)) {
            $this->sock = $res;
        }

        try {
            $this->run($event, array($res));
        } catch (Exception $e) {
            if ($this->mode == 'strict') {
                return false;
            }
        }

        if ($reset_listeners) {
            $this->resetListeners();
        }
        
        restore_error_handler();
        return $res;
    }

    /**
     * Accepts a connection on a socket
     * 
     * @see http://php.net/manual/en/function.socket-accept.php
     * @return resource The socket resource
     */
    public function accept()
    {
        return $this->exec('socket_accept', array(&$this->sock));
    }

    /**
     * Sets nonblocking mode for file descriptor fd
     *
     * @see http://php.net/manual/en/function.socket-set-nonblock.php
     * @return boolean
     */
    public function nonblock()
    {
        return $this->exec('socket_set_nonblock', array(&$this->sock));
    }

    /**
     * Sends a message to a socket, whether it is connected or not 
     *
     * @see http://php.net/manual/en/function.socket-sendto.php
     * @param  string  $buff  The buffer to send
     * @param  integer $flags Additional options
     * @return integer The number of bytes sent to the remote host   
     */
    public function sendto($buff, $flags = 0)
    {
        return $this->exec('socket_sendto', array(&$this->sock, $buff, strlen($buff), $flags, &$this->ip, &$this->port));
    }

    /**
     * Reads a maximum of length bytes from a socket
     *
     * @see http://php.net/manual/en/function.socket-read.php
     * @return string The data as a string
     */
    public function read()
    {
        return $this->exec('socket_read', array(&$this->sock, $this->max_read, $this->read_type));
    }

    /**
     * Writes to a socket
     *
     * @see php.net/manual/en/function.socket-write.php
     * @param  string  $buff The buffer to write
     * @return integer The number of bytes successfully written to the socket
     */
    public function write($buff)
    {
        return $this->exec('socket_write', array(&$this->sock, $buff, strlen($buff)));
    }

    /**
     * Runs the select() system call on the given arrays of sockets with a specified timeout
     *
     * @see http://php.net/manual/en/function.socket-select.php
     * @param  array   $read    The sockets watched to see if characters become available for reading
     * @param  array   $write   The sockets watched to see if a write will not block
     * @param  array   $except  The sockets watched for exceptions
     * @param  integer $tv_sec  The tv_sec and tv_usec together form the timeout parameter
     * @param  integer $tv_usec The tv_sec and tv_usec together form the timeout parameter
     * @return integer The number of socket resources contained in the modified arrays
     */
    public function select($read, $write = null, $except = null, $tv_sec = 0, $tv_usec = 0)
    {
        return $this->exec('socket_select', array(&$read, &$write, &$except, &$tv_sec, &$tv_usec));
    }

    /**
     * Closes a socket resource
     *
     * @see http://php.net/manual/en/function.socket-close.php
     * @return null No value is returned
     */
    public function close()
    {
        return $this->exec('socket_close', array(&$this->sock));
    }
}

