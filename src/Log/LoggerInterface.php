<?php

namespace Wallee\PluginCore\Log;

// If the official PSR-3 interface exists, our interface simply extends it.
if (interface_exists(\Psr\Log\LoggerInterface::class)) {
    interface LoggerInterface extends \Psr\Log\LoggerInterface
    {
    }
} else {
    // If it DOES NOT exist, we define our own fallback interface with the same methods.
    interface LoggerInterface extends \Stringable
    {
        public function emergency(string|\Stringable $message, array $context = []): void;
        public function alert(string|\Stringable $message, array $context = []): void;
        public function critical(string|\Stringable $message, array $context = []): void;
        public function error(string|\Stringable $message, array $context = []): void;
        public function warning(string|\Stringable $message, array $context = []): void;
        public function notice(string|\Stringable $message, array $context = []): void;
        public function info(string|\Stringable $message, array $context = []): void;
        public function debug(string|\Stringable $message, array $context = []): void;
        public function log($level, string|\Stringable $message, array $context = []): void;
    }
}
