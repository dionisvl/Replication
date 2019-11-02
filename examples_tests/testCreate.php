<?php
/**
 * example creating table for replicate in destination database
 * Создания таблицы репликанта и таблицы истории репликации
 */
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use Replication\CreateDestinationTable\Config;
use Replication\CreateDestinationTable\CreateDestinationTable;
use Replication\CreateDestinationTable\CreateDestinationHistoryTable;

$createTableConfig = new Config();

$createDestinationTable = new CreateDestinationTable($createTableConfig);
dump('Таблица для реплицирования готова. Время исполнения: ' .
    $createDestinationTable->getExecutionTime() . ' сек.');


$createDestinationHistoryTable = new CreateDestinationHistoryTable($createTableConfig);

dump('Таблица для сохранения истории репликации готова. Время исполнения: ' .
    $createDestinationHistoryTable->getExecutionTime() . ' сек.');
