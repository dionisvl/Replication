<?php

namespace Replication\CreateDestinationTable;

use PDO;


class Config
{
    private $sourceTableConnect;
    private $destTableConnect;

    private $source_table_name;
    private $dest_table_name;
    private $repl_history_table_name;

    private $config_file;
    private $config;

    public function __construct()
    {
        $this->config_file = 'config.ini';
        $this->config = $this->config_read($this->config_file);

        //Заполняем названия таблиц
        $this->source_table_name = $this->config['MAIN']['source_table'];
        $this->repl_history_table_name = $this->config['MAIN']['repl_history'];
        $this->dest_table_name = $this->config['MAIN']['dest_table'];

        $this->sourceTableConnect = $this->createSourceTableConnect('mysql','localhost', 'repl', 'root', '');
        $this->destTableConnect = $this->createDestTableConnect();
    }

    private function config_read($config_file)
    {
        return parse_ini_file($config_file, true);
    }

    private function createSourceTableConnect($dsn = 'mysql',$host = 'localhost', $name = 'repl', $user = 'root', $pass = '')
    {
        if (!empty($_REQUEST['source_config'])) {
            $dsn = $_REQUEST['source_dsn'];
            $db_host = $_REQUEST['source_host'];
            $db_name = $_REQUEST['source_name'];
            $db_user = $_REQUEST['source_user'];
            $db_pass = $_REQUEST['source_pass'];
            $connect = new PDO("$dsn:host=$db_host;dbname=$db_name", $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
            $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            dump('Connection to PARENT database created with custom params:');
            dump($_REQUEST);
        } else {
            $connect = new PDO("$dsn:host=$host;dbname=$name", $user, $pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
            $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return $connect;
    }

    private function createDestTableConnect()
    {
        if (!empty($_REQUEST['dest_config'])) {
            $dsn = $_REQUEST['dest_dsn'];
            $db_host = $_REQUEST['dest_host'];
            $db_name = $_REQUEST['dest_name'];
            $db_user = $_REQUEST['dest_user'];
            $db_pass = $_REQUEST['dest_pass'];
            $connect = new PDO("$dsn:host=$db_host;dbname=$db_name", $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
            $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->dest_table_name = $_REQUEST['dest_table'];
            $this->repl_history_table_name = $_REQUEST['repl_history'];
            dump('Connection to CHILD database created with custom params:');
            dump($_REQUEST);
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
     * @return string
     */
    public function getSourceTableName()
    {
        return $this->source_table_name;
    }

    /**
     * @return string
     */
    public function getReplHistoryTableName()
    {
        return $this->repl_history_table_name;
    }

    /**
     * @return string
     */
    public function getDestTableName()
    {
        return $this->dest_table_name;
    }
}