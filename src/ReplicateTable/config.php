<?php

namespace Replication\ReplicateTable;

class Config
{
    private $sourceTableConnect;
    private $destTableConnect;

    private $batch_size;
    private $last_processed_ts;
    private $last_processed_id;


    private $source_table_name;
    private $repl_history_table_name;
    private $dest_table_name;

    private $config_file;
    private $config;

    /**
     * Мапа соотношения полей реплицированной таблицы и полей лида в ядре кастомера brainysoft
     */
    private $dataSchema = [
        "id" => "id",
        "channel" => "",
        "lastName" => "lastName",//Проверка совпадения с уже существующим клиентом
        "firstName" => "name",//string[50] - Минимально необходимое поле
        "patronymic" => "",//Проверка совпадения с уже существующим клиентом
        "sexId" => "",
        "birthDate" => "",
        "birthPlace" => "",
        "passport" => [
            "seria" => "passport_seria",//[string][25] Проверка совпадения с уже существующим клиентом
            "no" => "passport_no",//[string][25] Проверка совпадения с уже существующим клиентом
            "issueDate" => "",
            "closeDate" => "",
            "manager" => "",
            "subdivisionCode" => "",
            "complementaryDocTypeId" => null
        ],
        "inn" => "",
        "snils" => "",
        "mobilePhone" => "phone",//string[50] - Минимально необходимое поле
        "amount" => "",
        "period" => 0,
        "periodUnit" => "",
        "storeTypeId" => null,
        "cardNumber" => "",
        "cardHolder" => "",
        "validThruMonth" => "",
        "validThruYear" => "",
        "cardCvc" => "",
        "childrenCount" => 0,
        "adultChildrenCount" => 0,
        "dependentsCount" => 0,
        "meanIncome" => 0,
        "averageMonthlyCost" => 0,
        "monthlyCreditPayment" => 0,
        "closedCreditsCount" => 0,
        "delinquencyCount" => 0,
        "payedDelinquencyCount" => 0,
        "writtenDelinquencyCount" => 0,
        "activeCreditsCount" => 0,
        "activeCreditsAmount" => 0,
        "activeDelinquencyAmount" => 0,
        "mobilePhoneCheck" => false,
        "ipAndRegionMatch" => false,
        "rosfinmonitoringCheck" => null,
        "ufmsCheck" => false,
        "approvedByScorista" => null,
        "storeCode" => "",
        "orderCode" => ""
    ];

    public function __construct()
    {
        $this->config_file = '.env';
        $this->config = $this->config_read($this->config_file);
//        dump($this->config);die();
        $this->setParamsFromIniFile($this->config);
        $this->sourceTableConnect = $this->createSourceTableConnect('localhost', 'repl', 'root', '');
        $this->destTableConnect = $this->createDestTableConnect();
    }

    /**
     * @return mixed
     */
    public function getDataSchema()
    {
        return $this->dataSchema;
    }

    /**
     * @param mixed $last_processed_ts
     */
    public function setLastProcessedTs($last_processed_ts): void
    {
        $this->last_processed_ts = $last_processed_ts;
        $this->config_set($this->config, 'MAIN', 'last_processed_ts', $last_processed_ts);
        $this->config_write($this->config, $this->config_file);
    }

    /**
     * @param mixed $last_processed_id
     */
    public function setLastProcessedId($last_processed_id): void
    {
        $this->last_processed_id = $last_processed_id;
        $this->config_set($this->config, 'MAIN', 'last_processed_id', $last_processed_id);
        $this->config_write($this->config, $this->config_file);
    }

    //0 - в процессе. 1 - процесс копирования завершен
    public function getReplicateProcessStatus()
    {
        return $this->config['MAIN']['replicate_process_status'];
    }

    public function setReplicateProcessStatus($status)//0 - в процессе. 1 - процесс копирования завершен
    {
        $this->config_set($this->config, 'MAIN', 'replicate_process_status', $status);
        $this->config_write($this->config, $this->config_file);
    }

    private function config_read($config_file)
    {
        return parse_ini_file($config_file, true);
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
        file_put_contents($config_file, $new_content);
    }

    private function setParamsFromIniFile($ini)
    {
        $this->batch_size = $ini['MAIN']['batch_size'];
        $this->last_processed_ts = $ini['MAIN']['last_processed_ts'];//например '2019-07-19 17:46:36';
        $this->last_processed_id = $ini['MAIN']['last_processed_id'];//например 4;

        //Заполняем названия таблиц
        $this->source_table_name = $ini['MAIN']['source_table'];
        $this->repl_history_table_name = $ini['MAIN']['repl_history'];
        $this->dest_table_name = $ini['MAIN']['dest_table'];
    }

    private function createSourceTableConnect($db_host = 'localhost', $db_name = 'repl', $db_user = 'root', $db_pass = '')
    {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    }

    private function createDestTableConnect()
    {
        if (!empty($_REQUEST['new_config'])) {
            $db_host = $_REQUEST['db_host'];
            $db_name = $_REQUEST['db_name'];
            $db_user = $_REQUEST['db_user'];
            $db_pass = $_REQUEST['db_pass'];
            $dest_db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
            $dest_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->dest_table_name = $_REQUEST['dest_table'];
            $this->repl_history_table_name = $_REQUEST['repl_history'];
            dump('Заданны кастомные настройки:');
            dump($_REQUEST);
        } else {
            $dest_db = $this->sourceTableConnect;
        }
        return $dest_db;
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
        return $this->batch_size;
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

    public function getParam($param)
    {
        return $this->config['MAIN'][$param];
    }
    public function setParam($paramName,$paramValue)
    {
        $this->config_set($this->config, 'MAIN', $paramName, $paramValue);
        $this->config_write($this->config, $this->config_file);
    }
}