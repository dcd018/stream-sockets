<?php
require dirname(__DIR__).'/vendor/autoload.php';

use StreamSockets\Socket;
use StreamSockets\Server;

$socket = new Socket('127.0.0.1', 1234, AF_INET, SOCK_STREAM, SOL_TCP);
// Maximum bytes to read from clients
$socket->max_read = 1024;

$server = new Server($socket);
// Maxiumum allowed client connections
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