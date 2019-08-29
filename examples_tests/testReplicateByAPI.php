<?php
/**
 * test replicate rows from one table to any API
 * Реплицирования определенных строк таблицы из оригинала в API
 */
require 'vendor/autoload.php';

use Replication\ReplicateTableToApi\ReplicateTableToApi;
use Replication\ReplicateTableToApi\ReplicateTableToApiConfig;
use Replication\ReplicateTableToApi\Request;

dump('Старт репликации по API');
$config = new ReplicateTableToApiConfig();

$token = $config->getToken();
$token = '***';//fastmoney
$fullUrl = 'https://***/**core/main/leads/';
$repl = new ReplicateTableToApi($config);

/* тест изменения статуса у лида в таблице донора
$repl->setStatusToItem(
    $config->getSourceSqlTableConnect(),
    $config->getSourceSqlTableName(),
    'user_status',
    1,
    'ALREADY_REPLICATED'
);
*/

//get new items who ready to replicate
$newItems = $repl->getNewItems(
    $config->getSourceSqlTableConnect(),//(pdo)
    $config->getSourceSqlTableName(),
    'user_status',
    'READY_TO_REPLICATE'
);

if (empty($newItems)) {
    dump('Новых элементов для репликации не найдено');
    $config->setReplicateToApiProcessStatus('FREE');//Установим флаг о том что процесс завершился
    die();
} else {
    dump('Получены и подготовлены к передаче по API следующие элементы:');
    dump($newItems);

    $result = $repl->sendItemsToApiTable($fullUrl, $newItems, $token);
    dump($result);
    $config->setReplicateToApiProcessStatus('FREE');
    dump(' Время исполнения: ' . $repl->getExecutionTime() . ' сек.');
    die();
}