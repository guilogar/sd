<?php

require_once "vendor/autoload.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();


// Te lo dejo a ti guillermito, para que le des tu amol del bueno.
// Recuerda, el json_decode son los padres....
/*
 *$callback = function ($msg) {
 *    echo ' [x] Received ', $msg->body, "\n";
 *    sleep(substr_count($msg->body, '.'));
 *    echo " [x] Done\n";
 *    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
 *};
 *
 *$channel->basic_consume('task_queue', '', false, false, false, false, $callback);
 */


$channel->close();
$connection->close();

