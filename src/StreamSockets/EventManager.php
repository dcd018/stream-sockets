<?php 
namespace StreamSockets;

class EventManager {

    /**
     * @var array $err E_ALL
     */
    public $err = array();

    /**
     * @var array $listeners
     */
    private $listeners = array();

    /**
     * Registers a listener on an event
     *
     * @param  string   $event
     * @param  callable $listener
     * @return StreamSockets\EventManager
     */
    public function on($event, callable $listener)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }

        $this->listeners[$event][] = $listener;

        return $this;
    }

    /**
     * Calls each listener registered on an event, or the entire event chain
     *  
     * @param  string $event
     * @param  array  $args
     */
    public function run($event = null, array $args = array())
    {
        if (is_null($event)) {
            foreach ($this->listeners as $event) {
                $this->run($event, $args);
            }
        } else {
            foreach ($this->listeners[$event] as $listener) {
                call_user_func_array($listener, $args);
            }
        }
    }

    /**
     * Removes a listener from an event
     * 
     * @param  string   $event
     * @param  callable $listener
     */
    public function removeListenter($event, callable $listener)
    {
        if (isset($this->listeners[$event])) {
            if (false !== $index = array_search($listener, $this->listeners[$event], true)) {
                unset($this->listeners[$event][$index]);
            }
        }
    }

    /**
     * Removes all listeners from an event, or the entire event chain
     * 
     * @param  string $event
     */
    public function resetListeners($event = null)
    {
        if ($event !== null) {
            unset($this->listeners[$event]);
        } else {
            $this->listeners = array();
        }
    }

    /**
     * Error handler
     *
     * @param  integer  $errno       Contains the level of the error raised
     * @param  string   $errstr      Contains the error message
     * @param  string   $errfile     Contains the filename that the error was raised in
     * @param  integer  $errline     Contains the line number the error was raised at
     * @param  array    $errcontext  Points to the active symbol table at the point the error occurred
     */
    public function handleErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = array())
    {
        if (!is_array($this->err)) $this->err = array();
        $err = new \stdClass();
        $err->code = $errno;
        $err->message = $errstr;
        $err->file = $errfile;
        $err->line = $errline;
        $err->context = $errcontext;
        $this->err[] = $err;
    }
}