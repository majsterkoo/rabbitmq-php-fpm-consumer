<?php

require_once '/usr/src/myapp/vendor/autoload.php';

use majsterkoo\RabbitmqPhpFpmConsumer\Message;


$message = (new Message())->parseFromArray($_POST);

$rand_val = rand(0,9);

if($message->hasFailed() && $message->getFailedCount() < 2){
   $message->setPayload('A hele, tady máme zprávu co selhala: ' . $message->getLastFailedMessage());
   //a procpeme ji do finalni dead phase
   $message->setFailed('A zase se to pokazilo');
}

else if($message->getPayload() === '0'){
   $message->setFailed('Nepovedlo se to provést');
}

//else $message->setPayload('**' . strval($message->getPayload()));

echo $message->fromWorker();