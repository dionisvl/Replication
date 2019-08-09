<?php

namespace Replication\CreateDestinationTable;
/**
 * Создадим таблицу истории репликации в БД (рядом с реплицированной таблицей)
 * Class CreateDestinationHistoryTable
 */
class CreateDestinationHistoryTable extends CreateCommonClass
{
    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->is_table_exists = parent::isTableExists($config->getReplHistoryTableName(), $config->getDestTableConnect());
        $historyTableName = $config->getReplHistoryTableName();

        if ($this->is_table_exists) {
            echo "Table '$historyTableName' already exists! <br>" . PHP_EOL;
        } else {
            echo "Table  '$historyTableName'  does not exists.<br>" . PHP_EOL;
            echo "Start creating $historyTableName... <br>".PHP_EOL;
            $this->createReplHistory($historyTableName, $config->getDestTableConnect());
        }

        $this->end_time = microtime(true);
    }

    private function createReplHistory($repl_history, $dest_db)
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `$repl_history` (
          `id` int(11) NOT NULL,
          `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `batch_size` int(11) NOT NULL,
          `update_ts_first` timestamp NULL DEFAULT NULL,
          `update_ts_last` timestamp NULL DEFAULT NULL,
          `status` int(11) DEFAULT NULL,
          `info` text
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        
        ALTER TABLE `$repl_history`
          ADD PRIMARY KEY (`id`);
        
        ALTER TABLE `$repl_history`
          MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
    ";
        $stmt = $dest_db->prepare($sql);
        $stmt->execute();
        if (!$stmt) {
            echo "\nPDO::errorInfo():\n";
            print_r($dest_db->errorInfo());
        } else {
            dump($repl_history . ' successfully created');
        }
    }
}