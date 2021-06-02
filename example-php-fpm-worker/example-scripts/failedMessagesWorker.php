<?php

require_once '/usr/src/myapp/vendor/autoload.php';

use majsterkoo\RabbitmqPhpFpmConsumer\Message;

$message = (new Message())->parseFromArray($_POST);

$message->setPayload('deleting failed message');

echo $message->fromWorker();