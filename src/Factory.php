<?php

namespace majsterkoo\RabbitmqPhpFpmConsumer;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\SocketConnections\Defaults;

class Factory {

   /**
    * @param string $address
    * @param int $port
    * @param string $user
    * @param string $vhost
    * @param bool $keepalive
    * @return AMQPStreamConnection
    */
   public static function AMQPStreamConnection(
      string $address = 'localhost',
      int $port = 5672,
      string $user = 'guest',
      string $vhost = '/',
      bool $keepalive = true): AMQPStreamConnection
   {
      return new AMQPStreamConnection(
         host: getenv('RABBITMQ_ADDRESS', 'localhost'),
         port: getenv('RABBITMQ_PORT', 5672),
         user: getenv('RABBITMQ_USER', 'guest'),
         password: getenv('RABBITMQ_PASSWORD', 'guest'),
         vhost: getenv('RABBITMQ_VHOST', '/'),
         keepalive: getenv('RABBITMQ_KEEPALIVE', true),
      );
   }

   /**
    * Create FastCGI Client using NetworkSocket
    * @param string $host
    * @param int $port
    * @param int $connectTimeout
    * @param int $readWriteTimeout
    * @return Client
    */
   public static function FastCGINetworkConnectionSocket(
      string $host = 'localhost',
      int $port = 9000,
      int $connectTimeout = Defaults::CONNECT_TIMEOUT,
      int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT
   ): Client{
      $socket = new NetworkSocket(host: $host, port: $port, connectTimeout: $connectTimeout, readWriteTimeout: $readWriteTimeout);
      return new Client($socket);
   }

   /**
    * Create FastCGI Client using UnixDomainSocket
    * @param string $socketPath
    * @param int $connectTimeout
    * @param int $readWriteTimeout
    * @return Client
    */
   public static function FastCGIUnixDomainSocketClient(
      string $socketPath,
      int $connectTimeout = Defaults::CONNECT_TIMEOUT,
      int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT
   ): Client{

   }

}