<?php

namespace Replication\ReplicateTable;

use PDO;

class ReplicateTableConfig
{
    private $sourceTableConnect;
    private $destTableConnect;

    private $batch_size;
    private $last_processed_ts;
    private $last_processed_id;


    private $source_table_name;
    private $repl_history_table_name;
    private $dest_table_name;

    private $config_dynamic;
    private $config_dynamic_file;

    private $config_static_file;
    private $config_static;


    public function __construct()
    {
        $this->config_dynamic_file = 'replicate_table_config_dynamic.ini';
        $this->config_static_file = 'replicate_table_config_static.ini';

        $this->config_dynamic = $this->config_read($this->config_dynamic_file);
        $this->config_static = $this->config_read($this->config_static_file);
//        dump($this->config);die();
        //Заполняем названия таблиц
        $this->source_table_name = $this->config_static['MAIN']['source_table'];
        $this->repl_history_table_name = $this->config_static['MAIN']['repl_history'];
        $this->dest_table_name = $this->config_static['MAIN']['dest_table'];

        $this->batch_size = (int) $this->config_static['MAIN']['batch_size'];

        //Заполняем статусы динамических параметров
        $this->last_processed_ts = $this->config_dynamic['MAIN']['last_processed_ts'];//например '2019-07-19 17:46:36';
        $this->last_processed_id = $this->config_dynamic['MAIN']['last_processed_id'];//например 4;

        $this->sourceTableConnect = $this->createSourceTableConnect('mysql:host=localhost;dbname=repl;','root', '');
        $this->destTableConnect = $this->createDestTableConnect();
    }

    /**
     * @param mixed $last_processed_id
     */
    public function setLastProcessedId($last_processed_id): void
    {
        $this->last_processed_id = $last_processed_id;
        $this->config_set($this->config_dynamic, 'MAIN', 'last_processed_id', $last_processed_id);
        $this->config_write($this->config_dynamic, $this->config_dynamic_file);
    }

    /**
     * @param mixed $last_processed_ts
     */
    public function setLastProcessedTs($last_processed_ts): void
    {
        $this->last_processed_ts = $last_processed_ts;
        $this->config_set($this->config_dynamic, 'MAIN', 'last_processed_ts', $last_processed_ts);
        $this->config_write($this->config_dynamic, $this->config_dynamic_file);
    }

    //IN_PROCESS - в процессе. FREE - процесс копирования завершен
    public function getReplicateProcessStatus()
    {
        return $this->config_dynamic['MAIN']['replicate_process_status'];
    }
    public function setReplicateProcessStatus($status)
    {
        $this->config_set($this->config_dynamic, 'MAIN', 'replicate_process_status', $status);
        $this->config_write($this->config_dynamic, $this->config_dynamic_file);
    }

    private function config_read($config_file)
    {
        return parse_ini_file(__DIR__.'/'.$config_file, true);
    }

    private function config_set(&$config_data, $section, $key, $value)
    {
        $config_data[$section][$key] = $value;
    }

    private function config_write($config_data, $config_file)
    {
        $new_content = '';
        foreach ($config_data as $section => $section_content) {
            $section_content = array_map(function ($value, $key) {
                return "$key=$value";
            }, array_values($section_content), array_keys($section_content));
            $section_content = implode("\n", $section_content);
            $new_content .= "[$section]\n$section_content\n";
        }
        file_put_contents(__DIR__.'/'.$config_file, $new_content);
    }

    private function createSourceTableConnect($dsn, $db_user, $db_pass)
    {
        if (!empty($_REQUEST['source_DB_CONNECTION'])) {
            $db_conn = $_REQUEST['source_DB_CONNECTION'];
            $db_host = $_REQUEST['source_DB_HOST'];
            $db_host = (empty($_REQUEST['source_DB_PORT'])) ? $db_host : $db_host . ':' . $_REQUEST['source_DB_PORT'];
            $db_name = $_REQUEST['source_DB_DATABASE'];
            $dsn = "$db_conn:host=$db_host;dbname=$db_name;";
            $db_user = $_REQUEST['source_DB_USERNAME'];
            $db_pass = $_REQUEST['source_DB_PASSWORD'];

            $this->source_table_name = $_REQUEST['source_DB_TABLE'];
            dump('Connection DB created with custom params:');
            dump($_REQUEST);
        }
        $connect = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
        $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $connect;
    }

    private function createDestTableConnect()
    {
        if (!empty($_REQUEST['dest_DB_CONNECTION'])) {
            $db_conn = $_REQUEST['dest_DB_CONNECTION'];
            $db_host = $_REQUEST['dest_DB_HOST'];
            $db_host = (empty($_REQUEST['dest_DB_PORT'])) ? $db_host : $db_host . ':' . $_REQUEST['dest_DB_PORT'];
            $db_name = $_REQUEST['dest_DB_DATABASE'];
            $dsn = "$db_conn:host=$db_host;dbname=$db_name;";
            $db_user = $_REQUEST['dest_DB_USERNAME'];
            $db_pass = $_REQUEST['dest_DB_PASSWORD'];

            $connect = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
            $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->dest_table_name = $_REQUEST['dest_DB_TABLE'];
            $this->repl_history_table_name = $_REQUEST['repl_history'];
        } else {
            $connect = $this->sourceTableConnect;
        }
        return $connect;
    }

    /**
     * @return PDO
     */
    public function getSourceTableConnect(): PDO
    {
        return $this->sourceTableConnect;
    }

    /**
     * @return PDO
     */
    public function getDestTableConnect(): PDO
    {
        return $this->destTableConnect;
    }

    /**
     * @return mixed
     */
    public function getBatchSize()
    {
        return (int) $this->batch_size;
    }

    /**
     * @return mixed
     */
    public function getLastProcessedTs()
    {
        return $this->last_processed_ts;
    }

    /**
     * @return mixed
     */
    public function getLastProcessedId()
    {
        return $this->last_processed_id;
    }

    /**
     * @return mixed
     */
    public function getSourceTableName()
    {
        return $this->source_table_name;
    }

    /**
     * @return mixed
     */
    public function getReplHistoryTableName()
    {
        return $this->repl_history_table_name;
    }

    /**
     * @return mixed
     */
    public function getDestTableName()
    {
        return $this->dest_table_name;
    }


    public function setParam($paramName,$paramValue)
    {
        $this->config_set($this->config, 'MAIN', $paramName, $paramValue);
        $this->config_write($this->config, $this->config_file);
    }
}