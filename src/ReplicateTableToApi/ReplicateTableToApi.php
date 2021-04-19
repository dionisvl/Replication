<?php

namespace Replication\ReplicateTableToApi;

use PDO;
use PDOException;

class ReplicateTableToApi
{
    private $config;
    private $dataSchema;
    private $newItems;

    private $start_time;
    private $end_time;

    public function __construct(ReplicateTableToApiConfig $config)
    {
        $this->start_time = microtime(true);

        $this->config = $config;

        /**Если процесс копирования завершен, тогда установим статус о том что он начался
         * IN_PROCESS - в процессе. FREE - процесс копирования завершен и готов начаться снова.
         */
        if ($this->config->getReplicateToApiProcessStatus() == 'FREE') {
            $this->config->setReplicateToApiProcessStatus('IN_PROCESS');
        } else {
            print_r(
                'Процесс репликации в API уже запущен. Поэтому ваш запрос отклонен и программа завершает работу.' . PHP_EOL
            );
            die();
        }

        $this->dataSchema = $this->config->getDataSchema();
    }

    /**
     * Получение новых строк из источника, по заданной колонке и её статусу
     * По умолчанию подразумевается получение новых готовых лидов у которых колонка "user_status" = 1
     */
    public function getNewItems($db, $source_table, $column = 'user_status', $status = 1)
    {
        try {
            $sql = "SELECT * from $source_table WHERE $column = '$status'";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $this->check_pdo($stmt);
            $data = $stmt->fetchAll();

            $newItems = [];
            foreach ($data as $index => $row) {
                $newItems[$index] = $this->parseRow($row, $this->dataSchema);
            }
            $this->newItems = $newItems;
            return $newItems;
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setReplicateToApiProcessStatus('FREE');
            die();
        }
    }

    private function parseRow($row, $schema)
    {
        $outputRow = [];
        foreach ($schema as $returnKey => $childKey) {
            if (!empty($childKey)) {
                if (is_array($childKey)) {
                    foreach ($childKey as $sub_ReturnKey => $sub_ChildKey) {
                        if (array_key_exists($sub_ChildKey, $row)) {
                            $outputRow[$returnKey][$sub_ReturnKey] = $row[$sub_ChildKey];
                        }
                    }
                } else {
                    if (array_key_exists($childKey, $row)) {
                        $outputRow[$returnKey] = $row[$childKey];
                    }
                }
            }
        }
//        dump($outputRow);
        return $outputRow;
    }

    /**
     * @param $url
     * @param $items
     * @param $token
     * @return string
     */
    public function sendItemsToApiTable($url, $items, $token)
    {
        $table = $this->config->getSourceSqlTableName();
        $db = $this->config->getSourceSqlTableConnect();
        foreach ($items as $item) {
            $result = $this->sendItemToApi($url, $item, $token);//Запрос на создание лида
            switch ($result) {
                case ($result['status'] === 'ok'):
                    /**
                     * NOT_READY - не готов к копированию.
                     * READY_TO_REPLICATE - готов к репликации.
                     * ALREADY_REPLICATED - реплицирован больше не трогать.
                     **/
                    $this->setStatusToItem($db, $table, 'user_status', $item['id'], 'ALREADY_REPLICATED');
                    continue 2;
                case (empty($result)):
                    return 'ERROR - empty response of item id = ' . $item['id'];
                case ($result['status'] === 'error'):
                    return $result;
                default:
                    return 'UNKNOWN ERROR of item id = ' . $item['id'];
            }
        }
        return 'ok';
    }

    private function sendItemToApi($fullUrl, $itemValues, string $token = '')
    {
        $bodyFields = $itemValues;

        $headers = [
            "Content-Type" => "application/json",
            "token" => $token,
            "cache-control" => "no-cache"
        ];
        $request = new Request('curl');
        $response = $request->run('POST', $fullUrl, $bodyFields, $headers);
        return json_decode($response, true);
    }

    /**
     * Установить статус элементу в исходной таблице
     * В частности для того чтобы в следующий раз не трогать этот элемент и лишний раз не копировать
     * @param $db
     * @param $table
     * @param string $column
     * @param int $itemId
     * @param string $status
     * @return bool
     */
    public function setStatusToItem($db, $table, $column = 'user_status', int $itemId, string $status)
    {
        try {
            $sql = "UPDATE $table SET $column = :status WHERE id = :itemId";
            $stmt = $db->prepare($sql);
            $stmt->BindValue(':status', $status, PDO::PARAM_STR);
            $stmt->BindValue(':itemId', $itemId, PDO::PARAM_INT);

            $stmt->execute();
            $this->check_pdo($stmt);
            dump("Статус у элемента $itemId успешно обновлен на $status");
//            if ($stmt->execute()) {
//                //return true;
//            } else {
//                return false;
//            }

        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setReplicateToApiProcessStatus('FREE');
            die();
        }
    }

    private function sendLeadToMysqlTest($itemValues)
    {
        $db = $this->config->getSourceSqlTableConnect();
        $sourceTable = $this->config->getSourceSqlTableName();
        $keysForSql = $this->implodeKeys(', ', $itemValues);
        $valuesKeysForPDO_Sql = ':' . $this->implodeKeys(', :', $itemValues);
        try {
            $sql = "INSERT INTO core_customer_table ($keysForSql) VALUES ($valuesKeysForPDO_Sql)";
            $stmt = $db->prepare($sql);
            foreach ($itemValues as $key => $value) {
                $stmt->bindParam(':' . $key, $value);
            }

            if ($stmt->execute()) {
                /**
                 * NOT_READY - не готов к копированию.
                 * READY_TO_REPLICATE - готов к репликации.
                 * ALREADY_REPLICATED - реплицирован больше не трогать.
                 **/
                $this->setStatusToItem($db, $sourceTable, 'user_status', $value['id'], 'ALREADY_REPLICATED');
                return true;
            }

            return false;
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setReplicateToApiProcessStatus('FREE');
            die();
        }
    }

    /**
     * Вариант функции Implode только для ключей массива
     * @param $separator
     * @param $array
     * @return string
     */
    private function implodeKeys($separator, $array): string
    {
        $out = '';
        $lastKey = array_key_last($array);
        foreach ($array as $key => $value) {
            $out .= $key;
            $out .= ($key !== $lastKey) ? $separator : '';
        }
        return $out;
    }

    public function getExecutionTime(): string
    {
        return number_format(microtime(true) - $this->start_time, 3, '.', ',');
    }

    private function check_pdo($sth): void
    {
        if (!empty($sth->errorInfo()[2])) {
            dump('[' . __LINE__ . '] Произошла ошибка репликации:');
            dump($sth->errorInfo()[2]);
            dump('Полная структура запроса:');
            dump($sth);
            $this->config->setReplicateToApiProcessStatus('FREE');//Установим флаг о том что процесс завершился
            $backtrace = debug_backtrace();
            dump($backtrace);
            die();
        }
    }
}