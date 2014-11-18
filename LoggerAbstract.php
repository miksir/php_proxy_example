<?php


namespace MiksIr\proxy;


abstract class LoggerAbstract {

    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    abstract public function log($level, $message, $context=[]);
}