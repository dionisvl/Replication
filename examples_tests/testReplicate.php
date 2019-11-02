<?php
/**
 * test replicate table from one to another table (other db connection also supported)
 * Реплицирования таблицы из оригинала в репликанта
 */
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

use Replication\ReplicateTable\ReplicateTable;
use Replication\ReplicateTable\ReplicateTableConfig;

$replicate = new ReplicateTable(new ReplicateTableConfig());

dump(' Время исполнения: ' . $replicate->getExecutionTime() . ' сек.');