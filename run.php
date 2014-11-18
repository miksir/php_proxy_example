<?php

spl_autoload_register(function($name) {
    include str_replace("MiksIr\\proxy\\", '', $name).'.php';
});

gc_enable();

$pool = new \MiksIr\proxy\ConnectionsCollection();
$logger = new \MiksIr\proxy\LoggerConsole();

(new \MiksIr\proxy\Listen($pool, $logger))->create();
$pool->loop();
