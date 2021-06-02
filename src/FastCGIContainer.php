<?php


namespace majsterkoo\RabbitmqPhpFpmConsumer;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

class FastCGIContainer {

   public Client $client;

   /**
    * FastCGIContainer constructor.
    * @param ConfiguresSocketConnection $socket_connection
    * @param string $script_folder
    */
   public function __construct(public ConfiguresSocketConnection $socket_connection, public string $script_folder = '/'){
      // add slash at the end of the path (first remove slash if already present)
      $this->script_folder = rtrim($this->script_folder, '/') . '/';
      $this->client = new Client();
   }

   public function getScriptPath(string $script_name): string{
      return $this->script_folder . $script_name;
   }

}