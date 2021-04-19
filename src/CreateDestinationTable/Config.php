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

        $source_db_name = $this->config['MAIN']['source_db_name'] ?? 'repl';
        $source_db_user = $this->config['MAIN']['source_db_user'] ?? 'root';
        $source_db_pass = $this->config['MAIN']['source_db_pass'] ?? 'root';

        $this->sourceTableConnect = $this->createSourceTableConnect("mysql:host=localhost;dbname=$source_db_name;",$source_db_user, $source_db_pass);
        $this->destTableConnect = $this->createDestTableConnect();
    }

    private function config_read($config_file)
    {
        return parse_ini_file($config_file, true);
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
     * @return string
     */
    public function getSourceTableName(): string
    {
        return $this->source_table_name;
    }

    /**
     * @return string
     */
    public function getReplHistoryTableName(): string
    {
        return $this->repl_history_table_name;
    }

    /**
     * @return string
     */
    public function getDestTableName(): string
    {
        return $this->dest_table_name;
    }
}