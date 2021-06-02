<?php declare(strict_types=1);

/*
 * MIT License
 *
 * Copyright (c) 2021 Michal Procházka
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

namespace majsterkoo\RabbitmqPhpFpmConsumer;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\PostRequest;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use phpseclib3\Math\BigInteger\Engines\PHP;

class Consumer {

   private ?FallbackExchange $fallback_exchange = null;
   private array $exchange_bindings = [ ];


   /**
    * Consumer constructor.
    * @param string $queue_name
    * @param AMQPStreamConnection $connection
    * @param FastCGIContainer $fast_cgi_container
    * @param ?MessageRouter $message_router
    * @param ?int $retry_count
    */
   public function __construct(
      private string $queue_name,
      private AMQPStreamConnection $connection,
      private FastCGIContainer $fast_cgi_container,
      private ?MessageRouter $message_router = null,
      private ?int $retry_count = null,
   ){}

   public function setMessageRouter(MessageRouter $message_router){
      $this->message_router = $message_router;
   }

   public function bindExchange(string $exchange, string|array $routing_key = ''): void{
      array_push($this->exchange_bindings, [ 'exchange' => $exchange, 'routing_key' => $routing_key ]);
   }

   /**
    * Configure default exchange where messages with failed status will be sent.
    * @param FallbackExchange $fallback_exchange
    */
   public function setDefaultFallbackExchange(FallbackExchange $fallback_exchange){
      $this->fallback_exchange = $fallback_exchange;
   }

   public function run(){
      $channel = $this->connection->channel();

      $channel->queue_declare($this->queue_name, false, false, false, false);

      foreach($this->exchange_bindings as $exchange){
         if(is_array($exchange['routing_key'])){
            foreach($exchange['routing_key'] as $routing_key){
               $channel->queue_bind($this->queue_name, $exchange['exchange'], $routing_key);
            }
         }
         else $channel->queue_bind($this->queue_name,$exchange['exchange'], $exchange['routing_key']);
      }
      //$channel->queue_bind($this->queue_name,'amq.fanout');

      $fast_cgi_container = &$this->fast_cgi_container;


      $callback = function(AMQPMessage $message) use ($channel,/*$fpmClient, $fpm_socket, $dockerdir, $routing_to_script,*/ $fast_cgi_container){
         //$script = $routingToScript($message->getRoutingKey());
         echo 'dosla zprava' . PHP_EOL;
         //prepare message to pass it into fast cgi
         $message_class = (new Message())->parseFromString($message->getBody());

         //if message is resending from own queue, we detect routing_key and exchange from Message object
         if(empty($message->getExchange()) && $message->getRoutingKey() === $this->queue_name){
            //If message came from self queue and in message_class is not defined original routing_key, we used as fallback message routing key (which will be $this->queue_name)
            //We can use this situation to define MessageRouter with this routingKey (routing_key == queue_name) and handling this specific error situation
            $routing_key = $message_class->getRoutingKey();
            $exchange = $message_class->getExchange();
         }
         //else we used values from received message
         else{
            $routing_key = $message->getRoutingKey();
            $exchange = $message->getExchange();
            //and set this values to Message object for later use
            $message_class->setExchange($message->getExchange());
            $message_class->setRoutingKey($message->getRoutingKey());
         }

         //if retry count value is defined in messageRouter, we rewrite default value in message
         $retry_count_router = $this->message_router->messageRetryCount($routing_key, $exchange, $message_class);
         if($retry_count_router !== null) $message_class->setRetryCount($retry_count_router);

         //get script name to run
         //echo "exchange: " . $;
         $script = $this->message_router->messageToScript($routing_key, $exchange, $message_class);

         //script is not defined, we have not to do
         if($script === null) {
            $channel->basic_ack($message->getDeliveryTag());
            return;
         }
         //echo 'delivery tag: ' . $message->getDeliveryTag() . PHP_EOL;
         $body = http_build_query($message_class->toArray());
         /* PROCESS MESSAGE IN WORKER */
         $request = new PostRequest($this->fast_cgi_container->getScriptPath($script), $body);
         $request->addResponseCallbacks(function(ProvidesResponseData $response) use ($message){
            $channel = $message->getChannel();
            echo $response->getBody() . PHP_EOL;
            try{
               $message_class = (new Message())->parseFromWorker($response->getBody());
            } catch(\Exception $ex){
               echo 'The message thrown an error!!!!' . PHP_EOL;
               $channel->basic_nack($message->getDeliveryTag(), requeue: false);

               $message_class = (new Message())->parseFromString($response->getBody());
               //we must detect max retryLimit, because we can go to the loop (if listening on fallback exchange and error occur always, message stay looped...)
               //in this situation we can use local logging for resolve the problem that occurred
               if(!$message_class->retryLimitReachMaximum()) {
                  $message_class->setFailed($ex->getMessage());
                  echo $message_class . PHP_EOL;
                  $fallback_exchange = $message_class->getFallbackExchange() ?? $this->fallback_exchange;
                  if ($fallback_exchange !== null && $fallback_exchange->exchange_name !== null) {
                     //TODO maybe, we can add property bool $publish_only_payload in FallbackExchange to publishing only payload of Message class instead of whole message.
                     $channel->basic_publish(new AMQPMessage($message_class), $fallback_exchange->exchange_name, $fallback_exchange->routing_key ?? $this->queue_name);
                  }
               }
               return;
               //error message parsing
            }
            if($message_class->isSuccess()){
               $channel->basic_ack($message->getDeliveryTag());
               //if we want send message back to rabbit
               if($message_class->hasMessageResponse()){
                  $res_message = new AMQPMessage($message_class->getMessageResponse());
                  $channel->basic_publish($res_message, $message_class->getExchange(), $message_class->getRoutingKey());
               }
            }
            else{
               $channel->basic_nack($message->getDeliveryTag(), requeue: false);
               if($message_class->getRetryCount() === -1) return;
               //if we NOT reached the limit of retrying then resend message
               if(!$message_class->retryLimitReachMaximum()){
                  echo 'We not reach the maximum limit!' . PHP_EOL;
                  //here we used Serialize interface and send back to rabbit with exchange and routing key name, because in other way this information will be lost (sending
                  //directly to this queue, not original exchange - because we won't publish error message to original exchange!)
                  $message->setBody($message_class);
                  //publishing back to the queue, not exchange!
                  $channel->basic_publish($message, routing_key: $this->queue_name/*$routing_key*/);
               }
               else{
                  //when fallback exchange is set, then we publish message to this exchange
                  $fallback_exchange = $message_class->getFallbackExchange() ?? $this->fallback_exchange;
                  if($fallback_exchange !== null && $fallback_exchange->exchange_name !== null){
                     //TODO maybe, we can add property bool $publish_only_payload in FallbackExchange to publishing only payload of Message class instead of whole message.
                     $channel->basic_publish(new AMQPMessage($message_class), $fallback_exchange->exchange_name, $fallback_exchange->routing_key ?? $this->queue_name);
                  }
               }
            }
         });
         /* PROCESS MESSAGE IN WORKER */

         //$processId = $fpmClient->sendAsyncRequest($fpm_socket, $request);
         $process_id = $fast_cgi_container->client->sendAsyncRequest($fast_cgi_container->socket_connection, $request);
         echo 'spawned script with process id: ' . $process_id .  ' script name: ' . $script . PHP_EOL;
         //echo " [{$date}] Spawned script: {$script} with payload: {$message->getBody()}\n";// for message number {$messageArray['number']}\n";


         /*echo 'message payload: ' . $message->getBody() . PHP_EOL;
         echo 'origin exchange: ' . $message->getExchange() . PHP_EOL;
         echo 'script to run: ' . $script . PHP_EOL;*/


        // echo 'is redelivered: ' . $message->isRedelivered() . PHP_EOL;
         //$request = new PostRequest($fast_cgi_container->script_folder . $script, $body);
      };

      $channel->basic_qos(null, 5, null);

      $channel->basic_consume(
         queue: $this->queue_name,
         consumer_tag: '',
         no_local: false,
         no_ack: false,
         exclusive: false,
         nowait: false,
         callback: $callback,
      );

      while($channel->is_consuming()){
         //echo 'is_consuming' . PHP_EOL;
         try {
            $this->fast_cgi_container->client->handleReadyResponses();
            $channel->wait(null, false, 1);
         } catch(\PhpAmqpLib\Exception\AMQPTimeoutException $ex){
            //echo $ex->getMessage() . PHP_EOL;
         } catch(\Exception | \Throwable $ex){
            // TODO logger
            echo "crash" . PHP_EOL;
            echo '[' . date('d.m.y H:i:s') . '] Exception class: ' . get_class($ex) . "\n";
            echo '[' . date('d.m.y H:i:s') . '] Exception: ' .$ex->getMessage() . "\n";
            throw($ex);
         }
      }

      $channel->close();
   }

   private function createChannel(){

   }

}