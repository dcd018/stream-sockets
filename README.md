This package impliments an interface to PHP's socket communication functions based on the popular BSD sockets.  The main goal of this project is to provide an event driven abstraction layer which attempts to automate several low-level tasks such as dealing with switching between many different connected sockets and approaching runtime exceptions with non-blocking routines.

######Listeners
```
listen
connect
input
disconnect
```

##Example server configuration

Start the server by running:
```
$ php stream-sockets/tests/server.php
```

```php
require dirname(__DIR__).'/vendor/autoload.php';

use StreamSockets\Socket;
use StreamSockets\Server;

$socket = new Socket('127.0.0.1', 1234, AF_INET, SOCK_STREAM, SOL_TCP);
$socket->max_read = 1024;
$socket->read_type = PHP_BINARY_READ;

$server = new Server($socket);
$server->max_clients = 10;

// Display a message when the server starts listening
$server->on('listen', function($server){
    if (!$server->is_listening)
        echo Server::escape('Congrats! A new socket is listening on '.$server->socket->ip.' binded to port: '.$server->socket->port);
    echo '.';
// Tell clients when the server accepts their connections
})->on('connect', function($server, $client, $input){
    echo Server::escape('New client connected '.$client->ip.':'.$client->port);
    $client->write(Server::escape('You are now connected to: '.$server->socket->ip.':'.$server->socket->port));
// Display client input and write a success message
})->on('input', function($server, $client, $input){
    echo Server::escape('Receiving input from '.$client->ip.':'.$client->port);
    echo Server::escape($input);
    $client->write(Server::escape('Success, input received by '.$server->socket->ip.':'.$server->socket->port));
// Inform the server when a client disconnects
})->on('disconnect', function($client){
    echo Server::escape('Client '.$client->ip.':'.$client->port.' is disconnecting.');
// Run indefinitely
})->keepAlive();
```

##Connecting and sending data to the server
Be sure your local machine allows connections from your remote host's IP address if testing remotely.  Otherwise, in a different session, or from another machine on your local subnet run:
```
$ php stream-sockets/tests/client.php
```

```php
$ip = "127.0.0.1";
$port = "1234";
$data = "ping!";

// Create a TCP Stream Socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Connect to the server
$result = socket_connect($socket, $ip, $port);

// Write to the socket
socket_write($socket, $data, strlen($data));

// Read from server
do 
{
  $line =@socket_read($socket,2048);
  echo $line. "\n";
} 
while ($line);

// Close the connection
socket_close($socket);
```

The client's session should have recieved a buffer similar to:
```
$ php stream-sockets/tests/client.php

You are now connected to: 127.0.0.1:1234

Success, input received by 127.0.0.1:1234
```

##Stability

By default, sockets will use nonblocking I/O.  If there's not a client connecting, the accept() system call will detect if the operation would result in a block, caching the error describing why it can't complete the call without waiting.  These errors can be useful for gathering an idea of what's happening on the server.  To view these errors, add the following to either `connect` or `input` listeners.

```php
$server->on('connect', function($server, $client, $input){
    if (!empty($server->err))
        print_r($server->err);
});
```
