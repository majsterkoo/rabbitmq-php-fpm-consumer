<?php

namespace majsterkoo\RabbitmqPhpFpmConsumer;

class FallbackExchange {

   public function __construct(
      public ?string $exchange_name = null,
      /**
       * @var bool Have bigger weight then routing_key (if this is true, than topic_name will be ignored)
       */
      public bool $routing_key_from_origin = false,
      /**
       * @var string|null
       */
      public ?string $routing_key = null,
   ){}

}