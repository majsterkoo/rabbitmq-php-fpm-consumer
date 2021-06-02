<?php

namespace majsterkoo\RabbitmqPhpFpmConsumer;

use JetBrains\PhpStorm\ArrayShape;
use Stringable;

/**
 * Class Message
 * @package majsterkoo\RabbitmqPhpFpmConsumer
 */
class Message implements Stringable{

   /**
    * @var array
    */
   private array $error = [
      // how many times we try to process the message when it previously failed (when processing failed, we resend message back to the queue)
      // if set to 0, then we throw fail on first message failed
      // if set to -1, then we set retry_count based on consumer value and when this value was not set or will be -1 too, then we send acknowledge even if message process fails
      'retry_count' => Constants::DEFAULT_RETRY_COUNT,
      // how many times the message failed to process
      'failed_count' => 0,
      // message of last fail
      'last_failed_message' => null,
   ];

   /**
    * @var mixed payload Message payload (or user/programmer defined data)
    */
   private mixed $payload = null;

   private ?string $routing_key = null;
   private ?string $exchange = null;

   private ?FallbackExchange $fallback_exchange = null;

   /**
    * @var array Here we store result from worker for rabbitmq consumer to decide what to do with message after processed by worker
    */
   #[ArrayShape(['success' => 'bool', 'MessageClass' => 'majsterkoo\RabbitmqPhpFpmConsumer\Message|null'])]
   private array $worker_result = [
      'success' => true,
      //if we return message class, then we send it back to the rabbitmq exchange
      'MessageClass' => null,
   ];

   /**
    * Message constructor.
    * @param ?string $payload
    * @param int $retry_count
    * @param FallbackExchange|null $fallback_exchange
    */
   public function __construct(mixed $payload = null, int $retry_count = 1, ?FallbackExchange $fallback_exchange = null){
      $this->payload = $payload;
      $this->setRetryCount($retry_count);
      $this->fallback_exchange = $fallback_exchange;
   }

   /**
    * @param int $retry_count
    * @return $this
    */
   public function setRetryCount(int $retry_count = 1): self{
      $this->error['retry_count'] = $retry_count;
      return $this;
   }

   /**
    *
    */
   public function getRetryCount(): int{
      return $this->error['retry_count'];
   }

   /**
    * @param string|null $message
    * @return $this
    */
   public function setFailed(?string $message = null): self{
      $this->error['failed_count'] = ++$this->error['failed_count'];
      $this->error['last_failed_message'] = $message;
      $this->worker_result['success'] = false;
      return $this;
   }

   /**
    * @param string $message
    * @return $this
    */
   public function setLastFailedMessage(string $message): self{
      $this->error['last_failed_message'] = $message;
      return $this;
   }

   /**
    * @return $this
    */
   public function incrementRetryCount(): self{
      $this->error['failed_count']++;
      return $this;
   }

   /**
    * @return string
    */
   public function getLastFailedMessage(): string{
      return $this->error['last_failed_message'];
   }

   /**
    * @param mixed|null $payload
    * @return $this
    */
   public function setPayload(mixed $payload = null): self{
      $this->payload = $payload;
      return $this;
   }

   /**
    * @return mixed
    */
   public function getPayload(): mixed{
      return $this->payload;
   }

   /**
    * @param string $routing_key
    * @return $this
    */
   public function setRoutingKey(string $routing_key): self{
      $this->routing_key = $routing_key;
      return $this;
   }

   /**
    * @param string $exchange
    * @return $this
    */
   public function setExchange(string $exchange): self{
      $this->exchange = $exchange;
      return $this;
   }

   /**
    * @return string|null
    */
   public function getRoutingKey(): ?string{
      return $this->routing_key;
   }

   /**
    * @return string|null
    */
   public function getExchange(): ?string{
      return $this->exchange;
   }

   /**
    * @param bool $success
    * @return $this
    */
   public function setWorkerResult(bool $success = true): self{
      $this->worker_result['success'] = $success;
      return $this;
   }

   /**
    * @param FallbackExchange $fallback_exchange
    * @return $this
    */
   public function setFallbackExchange(FallbackExchange $fallback_exchange): self{
      $this->fallback_exchange = $fallback_exchange;
      return $this;
   }

   /**
    * @return FallbackExchange|null
    */
   public function getFallbackExchange(): ?FallbackExchange{
      return $this->fallback_exchange;
   }

   /**
    * @return bool Return if messages process by worker is success
    */
   public function isSuccess(): bool{
      return $this->worker_result['success'];
   }

   /**
    * When worker was success, we can add message which will be send back to the rabbitmq
    * @param Message $message
    * @return $this
    */
   public function setMessageResponse(Message $message): self{
      $this->worker_result['MessageClass'] = $message->toArray();
      return $this;
   }

   /**
    * @return Message|null
    */
   public function getMessageResponse(): null|Message{
      return $this->worker_result['MessageClass'];
   }

   /**
    * @return bool
    */
   public function hasMessageResponse(): bool{
      return $this->worker_result['MessageClass'] !== null;
   }

   /**
    * @return bool
    */
   public function retryLimitReachMaximum(): bool{
      return $this->error['failed_count'] > $this->error['retry_count'];
   }

   /**
    * @return bool If message failed
    */
   public function hasFailed(): bool{
      return $this->error['failed_count'] > 0;
   }

   public function getFailedCount(): int{
      return $this->error['failed_count'];
   }

   /**
    * Parsing message from (json) string. If message is not recognized as stringified Message object, the message string
    * will be passed to payload or throw exception (if STRICT_MESSAGE=true is set in .env file)
    * @param string $stringified_message
    * @return Message
    * @throws \Exception
    */
   public function parseFromString(string $stringified_message): Message{
      try{
         $message = json_decode(json: $stringified_message, associative: true, flags: JSON_THROW_ON_ERROR);
         if(is_array($message)) {
            $this->parseFromArray($message);
         }
         //if $stringified_message is not array, we pass it to payload
         else{
            $this->payload = $message;
         }

      }
      catch(\JsonException $ex){
         //echo "error"
         if(getenv('MESSAGE_STRICT') === true) throw $ex;
         //pass message from rabbit as payload
         else $this->payload = $stringified_message;
      }

      return $this;

   }

   /**
    * This function we using in Consumer to parsing messages from worker (with "right usage" detection if message from worker not get from $message->fromWorker)
    * @param string $stringified_worker_message
    * @return Message
    */
   public function parseFromWorker(string $stringified_worker_message): Message{
      $message = json_decode(json: $stringified_worker_message, associative: true);
      if(!is_array($message) || !array_key_exists( 'worker_result', $message) || !is_array($message['worker_result'])){
         throw new \Exception('To response from worker YOU HAVE TO USE fromWorker() procedure of Message class instance!!');
      }
      return $this->parseFromArray($message);
   }

   /**
    * @param array $message
    * @return Message
    */
   public function parseFromArray(array $message): Message{
      if(isset($message['payload'])) $this->payload = $message['payload'];
      if(isset($message['error'])) $this->error = $message['error'];
      if(isset($message['exchange'])) $this->exchange = $message['exchange'];
      if(isset($message['routing_key'])) $this->routing_key = $message['routing_key'];
      if(isset($message['worker_result'])) $this->worker_result = $message['worker_result'];
      return $this;
   }

   /**
    * @return string
    */
   public function __toString(): string{
      return json_encode($this->toArray());
   }

   /**
    * Message formatted to send back to rabbit (remove not needed items)
    * @return string
    */
   public function toRabbit(): string{
      return json_encode([
         'error' => $this->error,
         'payload' => $this->payload,
      ]);
   }

   /**
    * We used this special procedure to returning response from php fpm worker. Why? Because we adding special parameter which we detecting in consumer
    * and when this parameter is not return from worker response, we can throw Exception and warning user that using bad returning values!
    * @return string json encoded Message object properties
    */
   public function fromWorker(): string{
      $res = $this->toArray();
      $res['worker_result'] = $this->worker_result;
      return json_encode($res);
   }

   /**
    * Convert Message into array
    * @return array
    */
   //#[ArrayShape(['error' => "array", 'payload' => "mixed"])]
   public function toArray(): array{
      return [
         'error' => $this->error,
         'payload' => $this->payload,
         'exchange' => $this->exchange,
         'routing_key' => $this->routing_key,

      ];
   }
}