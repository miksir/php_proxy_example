<?php


namespace MiksIr\proxy;


class LoggerConsole extends LoggerAbstract {

    public function log($level, $message, $context=[])
    {
        $time = date('H:m:i');
        echo "{$time} [{$level}] {$message}";
        if (isset($context['caller'])) {
            echo " @".$context['caller'];
        }
        echo "\n";
    }
}