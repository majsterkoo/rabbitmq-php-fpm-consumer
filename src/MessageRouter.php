<?php

namespace majsterkoo\RabbitmqPhpFpmConsumer;

use JetBrains\PhpStorm\ArrayShape;

class MessageRouter {

   private array $scripts = [ ];
   /**
    * TopicRouter constructor.
    * @param array $topics
    */
   #[ArrayShape([ 'routing_key' => 'string', 'script' => 'string', 'exchange' => 'string', 'retry_count' => 'int'])]
   public function __construct(array $topics) {
      foreach($topics as $topic){
         $this->addTopicScript($topic['script'], $topic['routing_key']??'', $topic['exchange']??'', $topic['retry_count']??null);
      }
   }

   /**
    * @param mixed $script_name Script name or function returning script name
    * @param string $routing_key
    * @param string $exchange_name Name of exchange from where message was send
    * @throws \Exception When script_name is not allowed type
    */
   public function addTopicScript(mixed $script_name, string $routing_key = '', string $exchange_name = '', ?int $retry_count = null): void{
      if(is_string($script_name) || is_callable($script_name)){

         // Duplicate definition detection
         if(isset($this->scripts[$exchange_name][$routing_key])) {
            throw new \Exception('Script for exchange: ' . $exchange_name . ' and routing_key: ' . $routing_key . ' already defined!');
         }
         $this->scripts[$exchange_name][$routing_key] = [ 'script_name' => $script_name, 'retry_count' => $retry_count ];
      }
      else{
         Throw new \Exception('Unknown type of script_name variable passed: ' . gettype($script_name) . '. Only allowed types are string or function.');
      }
   }

   /**
    * @param string $routing_key
    * @param string $exchange
    * @param Message|null $message
    * @return string|null
    */
   public function messageToScript(string $routing_key, string $exchange = '', ?Message $message = null): ?string{
      $script = null;
      //echo $routing_key . ' ' . $exchange . ' ' . $message . PHP_EOL;
      //print_r($this->scripts);
      //search in defined exchanges
      if(in_array($exchange, array_keys($this->scripts))){
         if(isset($this->scripts[$exchange][$routing_key])) $script = $this->scripts[$exchange][$routing_key]['script_name'];
         //if is defined "universal" routing_key
         else if(isset($this->scripts[$exchange][''])) $script = $this->scripts[$exchange]['']['script_name'];
      }
      //if definition was not found and we have defined "universal" exchange
      if($script === null && isset($this->scripts[''])){
         if(isset($this->scripts[''][$routing_key])) $script = $this->scripts[''][$routing_key]['script_name'];
      }

      if(is_callable($script)){
         return $script($routing_key, $exchange, $message);
      }
      else return $script;

   }

   /**
    * @param string $routing_key
    * @param string $exchange
    * @param Message|null $message
    * @return int|null
    */
   public function messageRetryCount(string $routing_key, string $exchange = '', ?Message $message = null): ?int{
      $retry_count = null;

      if(in_array($exchange, array_keys($this->scripts))){
         if(isset($this->scripts[$exchange][$routing_key])) $retry_count = $this->scripts[$exchange][$routing_key]['retry_count'];
         //if is defined "universal" routing_key
         else if(isset($this->scripts[$exchange][''])) $retry_count = $this->scripts[$exchange]['']['retry_count'];
      }
      //if definition was not found and we have defined "universal" exchange
      if($retry_count === null && isset($this->scripts[''])){
         if(isset($this->scripts[''][$routing_key])) $retry_count = $this->scripts[''][$routing_key]['retry_count'];
      }

      if(is_callable($retry_count)){
         return $retry_count($routing_key, $exchange, $message);
      }
      else return $retry_count;
   }

}