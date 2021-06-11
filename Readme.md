- working example in example folder. Docs in progress!

## Sending stringified Message class throught rabbitmq 
- if you want to send Message class throught rabbitmq, you must specify Content-Type in header to 
  'application/vnd.rabbitmq-fpm-consumer.Message'.
- if this header is not set, then whole message will be pass into $payload property of Message class.

## env file
| name | default_value | definition |
| ---- | ------------- | ---------- |
| RABBITMQ_ADDRESS | |
| RABBITMQ_PORT | |
| RABBITMQ_USER | |
| RABBITMQ_PASSWORD | |
| RABBITMQ_VHOST | |
| RABBITMQ_KEEPALIVE | |
| MESSAGE_STRICT | false | |

## message definition
```typescript
type FailedType = {
   //date of last failed
   last_failed: string, // date
   //last (?) reason message
   reason: string,
   //num of failed
   failed_count: number,   
}

type MessageType = {
   data: object,
   failed: null|FailedType,
}
```

# Classes

## MessageRouter

```php
new \majsterkoo\RabbitmqPhpFpmConsumer\MessageRouter([
   [ 'routing_key' => 'foo.key', 'script' => 'phpscript', 'exchange' => 'amq.fanout' ]
]);
```
- In example above, when consumer receive message:
    1) Check if exists exchange from message is (amq.fanout).
    2) If exchange is defined, we start searching for routing_key:
        1) If routing key is found, phpscript is returned as script to run.
        2) If routing key is '' (empty string), phpscript is returned as script to run.
        3) If routing key is not found, searching for next exchange start
    3) If we have defined exchange '' (empty string), we start search for routing_key:
        - same as ii.
    4) If there is any no match, the message is nacked.


- If using empty strings, pay attention to the order of the definitions in the array(searching 
  is in the same direction as array is defined). If you define:
  ```php
  new \majsterkoo\RabbitmqPhpFpmConsumer\MessageRouter([
     [ 'routing_key' => '', 'script' => 'phpscript.php', 'exchange' => 'amq.fanout' ],
     [ 'routing_key' => 'foo.key', 'script' => 'phpscript.php', 'exchange' => 'amq.fanout' ]
  ]);
  ```
  then the 'foo.key' will never be used because '' suppressed him!
 
- **hint** - if you want get notification when the script is not found for the message you can use
  **at the end(!!)** of the MessageRouter definitions:
  ```php
  [ 'routing_key' => '', 'script' => 'fallback.php', 'exchange' => '' ];
  ```
  After this when none of the definitions will match the message, then every message is send to fallback script, where
  you can put own handling logic for this message types. 

## FallbackExchange
- Is used for handling definition of failed messages.


- Can be defined as global in Consumer or locally in Message object.
- When defined globally and locally, then locally definition is greater weight.
- More detail in Error handling section

# Error handling
- In Message you can define retry_count in constructor or setRetryCount procedure. When message
failed and retry_count is less then failed_count (incremented on every fail), then the message can be send back to the queue.
- If failed_count reach the retry_count limit, then:
    - If FallbackExchange (described in Class section) is null, the message will be "forgotten" (not resend back to rabbit).
    - If FallbackExchange is set, the message will be sent back to rabbit using FallbackExchange definition.
    - If FallbackExchange $routing_key value is null, then routing_key will be set to queue_name of consumer
    
- For sending failed message back to the queue to retry, rabbitmq using special (AMQP default) Exchange.
  Because we must send message without exchange and with routing_key = queue_name, we lost 
  information about previous (original) exchange and routing_key. So this information is passed
  using Message class. If from same reason we receive message from this exchange but original
  exchange and routing_key was not defined in Message class, then exchange = null and routing_key = queue_name will be used.

# TODO

- [ ] fix grammar in comments & readme :)
- [ ] Logger
- [ ] Remove code duplicity in addResponseCallbacks (duplicity in code when 
  parseFromWorker thrown an exception VS "fallbacking" message)

## meybe?
- [ ] replace array properties in Message with classes?
- [ ] Message compression/decompression
