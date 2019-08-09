<?php

namespace Replication\CreateDestinationTable;


class CreateCommonClass
{
    protected $is_table_exists = false;
    protected $config;

    private $start_time;
    protected $end_time;

    public function __construct(Config $config){
        $this->start_time = microtime(true);
        $this->config = $config;

    }

    protected function isTableExists($tableName, $db)
    {
        $sql = "SHOW TABLES LIKE '$tableName'";
        $sth = $db->prepare($sql);
        $sth->execute();
        if ($sth->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getExecutionTime(){
        return number_format($this->end_time-$this->start_time,3,'.',',');
    }
}