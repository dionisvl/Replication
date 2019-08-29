<?php

namespace Replication\ReplicateTableToApi;

use PDO;

class ReplicateTableToApiConfig
{
    private $sourceSqlTableConnect;

    private $source_sql_table_name;

    private $config_file;
    private $config;

    private $token;
    /**
     * Мапа соотношения полей реплицированной таблицы и полей в API (лида в ядре кастомера brainysoft)
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
        $this->config_file = 'config.ini';
        $this->config = $this->config_read($this->config_file);
//        dump($this->config);die();
        //Заполняем названия таблиц
        $this->source_sql_table_name = $this->config['MAIN']['source_sql_table_name'];
        $this->token = $this->config['MAIN']['bsauth'];

        $this->sourceSqlTableConnect = $this->setSourceSqlTableConnect('mysql:host=localhost;dbname=repl;','root', '');
    }

    /**
     * @return mixed
     */
    public function getDataSchema()
    {
        return $this->dataSchema;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    //IN_PROCESS - в процессе. FREE - процесс копирования завершен
    public function getReplicateToApiProcessStatus()
    {
        return $this->config['MAIN']['replicate_to_core_process_status'];
    }
    public function setReplicateToApiProcessStatus($status)
    {
        $this->config['MAIN']['replicate_to_core_process_status'] = $status;
        $this->config_write($this->config, $this->config_file);
    }

    private function config_read($config_file)
    {
        return parse_ini_file(__DIR__.'/'.$config_file, true);
    }

    private function config_write($config_data, $config_file)
    {
        $new_content = '';
        foreach ($config_data as $section => $section_content) {
            $section_content = array_map(function ($value, $key) {
                if ($key=='bsauth'){
                    return "$key='$value'";
                }
                return "$key=$value";
            }, array_values($section_content), array_keys($section_content));
            $section_content = implode("\n", $section_content);
            $new_content .= "[$section]\n$section_content\n";
        }
        file_put_contents(__DIR__.'/'.$config_file, $new_content);
    }
    
    private function setSourceSqlTableConnect($dsn, $db_user, $db_pass)
    {
        if (!empty($_REQUEST['dest_DB_CONNECTION'])) {
            $db_conn = $_REQUEST['dest_DB_CONNECTION'];
            $db_host = $_REQUEST['dest_DB_HOST'];
            $db_host = (empty($_REQUEST['dest_DB_PORT'])) ? $db_host : $db_host . ':' . $_REQUEST['dest_DB_PORT'];
            $db_name = $_REQUEST['dest_DB_DATABASE'];
            $dsn = "$db_conn:host=$db_host;dbname=$db_name;";
            $db_user = $_REQUEST['dest_DB_USERNAME'];
            $db_pass = $_REQUEST['dest_DB_PASSWORD'];

            $this->source_sql_table_name = $_REQUEST['dest_DB_TABLE'];
        }
        $connect = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
        $connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $connect;
    }

    /**
     * @return PDO
     */
    public function getSourceSqlTableConnect(): PDO
    {
        return $this->sourceSqlTableConnect;
    }

    /**
     * @return string
     */
    public function getSourceSqlTableName()
    {
        return $this->source_sql_table_name;
    }
}