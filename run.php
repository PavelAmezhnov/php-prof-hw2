#!/usr/bin/php
<?php

use BracketCounter\BracketCounter;

require_once './vendor/autoload.php';

$startedAt = time();
$address = '0.0.0.0';
$port = isset($argv[1]) ? (int) $argv[1] : -1;
if ($port < 1024 || $port > 65535) {
    echo "Необходимо указать порт в диапазоне 1024-65535\n";
    exit();
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($socket, $address, $port);
socket_listen($socket, 2);
socket_set_nonblock($socket);
$clients = $inputs = $timestamps = [];

do {
    if (($msgSocket = socket_accept($socket)) !== false) {
        socket_set_nonblock($msgSocket);
        socket_write($msgSocket, "Введите строку для выполнения проверки.
Введите '!' при завершении ввода строки.
Введите 'quit' для выхода\n");
        $clients[] = $msgSocket;
        $timestamp = time();
        $timestamps[] = $timestamp;
        echo "Connection " . array_key_last($clients) . " opened at " . $timestamp . "\n";
    }

    foreach ($clients AS $k => $v) {
        if (!isset($inputs[$k])) {
            $inputs[$k] = '';
        }

        if ($buffer = socket_read($v, 1024)) {
            if (trim($buffer) === '!') {
                try {
                    $output = "Результат проверки - " . (BracketCounter::check($inputs[$k]) === true ? 'верно' : 'неверно') . "\n";
                } catch (Throwable $e) {
                    $output = $e->getMessage() . "\n";
                }

                socket_write($v, $output);
                unset($inputs[$k]);
            } elseif(trim($buffer) === 'quit') {
                socket_write($v, "Соединение закрыто по инициативе клиента\n");
                socket_close($v);
                unset($clients[$k], $inputs[$k], $timestamps[$k]);
                echo "Connection $k closed\n";
            } else {
                $inputs[$k] .= $buffer;
            }
        }

        if (isset($clients[$k]) && time() - $timestamps[$k] > 3600) {
            socket_write($v, "Соединение закрыто по таймауту (1 час)\n");
            socket_close($v);
            unset($clients[$k], $inputs[$k], $timestamps[$k]);
            echo "Connection $k closed\n";
        }
    }

    if (empty($clients) && time() - $startedAt > 86400) {
        break;
    }

    sleep(1);
} while (true);

socket_close($socket);
