<?php

namespace majsterkoo\RabbitmqPhpFpmConsumerExample;

require_once __DIR__ . '/../vendor/autoload.php';

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use majsterkoo\RabbitmqPhpFpmConsumer\FallbackExchange;
use majsterkoo\RabbitmqPhpFpmConsumer\Message;
use PhpAmqpLib\Connection;
use majsterkoo\RabbitmqPhpFpmConsumer\Consumer;
use majsterkoo\RabbitmqPhpFpmConsumer\FastCGIContainer;
//use majsterkoo\RabbitmqPhpFpmConsumer\RabbitmqQueue;
use majsterkoo\RabbitmqPhpFpmConsumer\MessageRouter;

$connection = new Connection\AMQPStreamConnection('localhost', 5672, 'guest', 'guest', '/', false, 'AMQPLAIN', null, 'en_US', 10.0, 10.0, null, true);
$fast_cgi = new FastCGIContainer(
   socket_connection: new NetworkSocket('localhost',9000,5000,5000),
   script_folder: '/usr/src/myapp/example-php-fpm-worker/example-scripts',
);

$consumer = new Consumer('just_another_queue', $connection, $fast_cgi);

// FallbackExchange with null routing_key => routing_key will be set to queue name
$consumer->setDefaultFallbackExchange(new FallbackExchange(exchange_name: 'fallback', routing_key: null));

$consumer->bindExchange(exchange: 'amq.fanout', routing_key: '');
$consumer->bindExchange(exchange: 'fallback', routing_key: 'just_another_queue');

//declares which routing key starts which script
$consumer->setMessageRouter(new MessageRouter([
   [ 'script' => 'helloWorldWorker.php', 'exchange' => 'amq.fanout', 'retry_count' => 1 ],
   [ 'script' => 'failedMessagesWorker.php', 'exchange' => 'fallback', 'routing_key' => 'just_another_queue' ],
   //define which routing key starts which scripts
   /*[ 'script' => 'phpscript2', 'exchange' => 'amq.fanout' ],
   //define which routing keys starts which script
   [ 'routing_key' => '', 'script' => 'helloWorldWorker.php', 'exchange' => 'ss' ],*/
   //define called script by custom function
   /*[ 'routing_key' => '', 'exchange' => 'amq.fanout', 'script' => function(string $routing_key, string $exchange, Message $message): ?string{
     // echo PHP_EOL.PHP_EOL.PHP_EOL;
      //echo '## ' . $routing_key . ' ' . $exchange . ' ' . $message->getPayload() . PHP_EOL;
     // echo PHP_EOL.PHP_EOL.PHP_EOL;
      return 'helloWorldWorker.php';
      if($message->getPayload() === 'some data') return 'phpscript1';
      else if($routing_key !== 'routing_key') return 'phpscript2';
      //when return null the consumer just send acknowledge of message and not start any script
      else return null;
   }],
   [ 'routing_key' => '', 'script' => 'helloWorldWorker.php', 'exchange' => '' ],*/
]));

$consumer->run();
