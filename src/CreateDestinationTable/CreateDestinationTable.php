<?php

namespace Replication\CreateDestinationTable;


/**
 * Создадим таблицу в БД назначения.
 *  - проверим существоание таблицы в БД назначения с таким именем (создадим если нет)
 *  - проверим есть ли у неё все колонки с таким же типом как у родительской (и создадим если нет)
 *
 * Class CreateDestinationTable
 */
class CreateDestinationTable extends CreateCommonClass
{
    private $source_db;
    private $source_table_name;

    private $dest_db;
    private $dest_table_name;

    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->source_db = $this->config->getSourceTableConnect();
        $this->source_table_name = $this->config->getSourceTableName();

        $this->dest_db = $this->config->getDestTableConnect();

        $this->dest_table_name = $config->getDestTableName();
        $this->is_table_exists = $this->isTableExists($this->dest_table_name, $this->dest_db);

        if ($this->is_table_exists) {
            echo "Table '$this->dest_table_name' already exists! <br>" . PHP_EOL;
        } else {
            echo "Table  '$this->dest_table_name'  does not exists.<br>" . PHP_EOL;
            echo "Start creating $this->dest_table_name... <br>".PHP_EOL;
            $this->createTable($this->dest_table_name, $this->dest_db);
            $this->checkAndCreateColumnsInChildTable($this->getParentColumnsInfo());
        }




        $this->end_time = microtime(true);
    }

    private function createTable($dest_table, $dest_db)
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `$dest_table` (
              `id` int(11) NOT NULL,
              `repl_create_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `repl_update_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `repl_proc_id` int(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        
        ALTER TABLE `$dest_table`
          ADD PRIMARY KEY (`id`);
        
        ALTER TABLE `$dest_table`
          MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
    ";
        $stmt = $dest_db->prepare($sql);
        $stmt->execute();
        if (!$stmt) {
            echo "\nPDO::errorInfo():\n";
            dump($dest_db->errorInfo());
        } else {
            dump($dest_table . ' successfully created');
        }

    }

    /**
     * Checking columns exist or not in new table.
     * And if some are missing, then we will create them.
     * @return string
     */
    private function checkAndCreateColumnsInChildTable($data)
    {
        $dest_table = $this->dest_table_name;
        $dest_db = $this->dest_db;
        foreach ($data as $column) {
            //dump($column);
            $col_name = $column['Field'];

            //Проверим существование этой колонки в дочерней таблице
            $sql = "SELECT $col_name FROM $dest_table limit 1";
            $sth = $dest_db->prepare($sql);
            $sth->execute();

            if ($sth->columnCount() > 0) {
                echo 'Column {' . $col_name . '} is already exists<br>' . PHP_EOL;
            } else {
                $NOT_NULL = '';
                if ($column['Null'] == 'NO') {
                    $NOT_NULL = 'NOT NULL';
                }
                $sql = "ALTER TABLE $dest_table ADD $col_name $column[Type] $NOT_NULL";

                $statement = $dest_db->query($sql);
                //dump('sql query: ' . $sql);
                //dump($statement);
                try {
                    if ($statement == false) {
                        dump('Error column add to db - No record found');
                    } else {
                        echo 'Column  {' . $col_name . '} has been added to the database<br>' . PHP_EOL;
                    }
                } catch (\PDOException $e) {
                    dump($e);
                }
            }
        }
        return;
    }

    /**
     * Get info about all columns it parent table
     * @return array
     */
    private function getParentColumnsInfo()
    {
        $db = $this->source_db;
        $stmt = $db->prepare("DESCRIBE $this->source_table_name");
        $stmt->execute();
        if (!$stmt) {
            echo "\nPDO::errorInfo():\n";
            print_r($db->errorInfo());
            die();
        } else {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
}