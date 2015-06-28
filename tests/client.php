<?php 
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
?>