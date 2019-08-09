<?php

namespace Replication\ReplicateTableToApi;


class ReplicateTableToApi
{
    private $config;
    private $dataSchema;
    private $newLeads;


    private $start_time;
    private $end_time;

    public function __construct()
    {
//        $this->start_time = microtime(true);
//        $this->end_time = microtime(true);

        $this->config = new Config();
        /**
        Установим статус репликации о том что она в процессе
        для того чтобы параллельный запуск процессов копирования по крону не привел ошибкам
        0 - процесс копирования завершен. 1 -  процесс копирования идет в настоящий момент
         */
        if ($this->config->getParam('replicate_to_core_process_status') == 1){
            die('Ошибка! В настоящий момент происходит другой процесс репликации');
        } else {
            $this->config->setParam('replicate_to_core_process_status',1);
        }

        $this->dataSchema = $this->config->getDataSchema();


    }

    public function getNewLeads()
    {
        $db = $this->config->getDestTableConnect();

        try {
            $sql = 'SELECT * from dest_table WHERE user_status = 1';

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll();

            $newLeads = [];
            foreach ($data as $index => $row) {
                $newLeads[$index] = $this->parseRow($row, $this->dataSchema);
            }
            $this->newLeads = $newLeads;
            return $newLeads;
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setParam('replicate_to_core_process_status',0);
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
        dump($outputRow);
        return $outputRow;
    }

    /**
     * Вариант функции Implode только для ключей массива
     * @param $separator
     * @param $array
     * @return string
     */
    private function implodeKeys($separator, $array)
    {
        $out = '';
        $lastKey = array_key_last($array);
        foreach ($array as $key => $value) {
            $out .= $key;
            $out .= ($key != $lastKey) ? $separator : '';
        }
        return $out;
    }

    /**
     * @param $leads
     * @return string
     */
    public function sendLeadsToCoreTable($leads)
    {
        foreach ($leads as $lead) {
            $result = $this->sendLeadToCore($lead);
            switch ($result){
                case (empty($result)):
                    return 'empty response';
                case ($result['status'] == 'ok'):
                    $this->setStatusToLead($lead['id'], 2);//0 - не готов к копированию.1 - готов к репликации.2 - реплицирован больше не трогать.
//                    return 'ok';
                    continue;
                case ($result['status'] == 'error'):
                    return $result;
                default:
                    return false;
            }
        }
        return 'ok';
    }

    private function sendLeadToCore($leadValues)
    {
        $url = 'https://core.brainysoft.ru:9025/bs-core/main/leads/';//Запрос на создание лида
        $bodyFields = $leadValues;

        $bsauth = '=='; //fastmoney
        $headers = [
            "Content-Type" => "application/json",
            "bsauth" => "$bsauth",
            "cache-control" => "no-cache"
        ];
        $request = new Request('curl');
        $response = $request->run('POST', $url, $bodyFields, $headers);
        $response = json_decode($response,true);
        return $response;
    }

    /**
     * Установить статус лиду в исходной таблице
     * В частности для того чтобы в следующий раз не трогать этого лида и лишний раз не копировать
     * @param int $status
     * @param int $leadId
     * @return bool
     */
    private function setStatusToLead(int $leadId, int $status)
    {
        $db = $this->config->getDestTableConnect();
        try {
            $sql = "UPDATE dest_table SET user_status = :status WHERE id = :leadId";
            $stmt = $db->prepare($sql);
            $stmt->BindValue(':status', $status, PDO::PARAM_INT);
            $stmt->BindValue(':leadId', $leadId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                //return true;
                dump("Статус у лида $leadId успешно обновлен на $status");
            } else {
                return false;
            }
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setParam('replicate_to_core_process_status',0);
            die();
        }
    }

    private function sendLeadToMysqlTest($leadValues)
    {
        $db = $this->config->getDestTableConnect();
        $keysForSql = $this->implodeKeys(', ', $leadValues);
        $valuesKeysForPDO_Sql = ':' . $this->implodeKeys(', :', $leadValues);
        try {
            $sql = "INSERT INTO core_customer_table ($keysForSql) VALUES ($valuesKeysForPDO_Sql)";
            $stmt = $db->prepare($sql);
            foreach ($leadValues as $key => $value) {
                $stmt->bindParam(':' . $key, $value);
            }

            if ($stmt->execute()) {
                $this->setStatusToLead($leadValues['id'], 3);//0 - не готов к копированию.2 - готов к репликации.3 - реплицирован больше не трогать.
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            print "Error!:" . $e->getMessage() . "<br/>";
            $this->config->setParam('replicate_to_core_process_status',0);
            die();
        }
    }

    public function getExecutionTime()
    {
        return number_format($this->end_time - $this->start_time, 3, '.', ',');
    }

}

class Request
{
    private $method = '';

    public function __construct(String $method = 'curl')
    {
        $this->method = $method;
    }

    public function run($requestType, $url, $bodyFields, $headers)
    {
        switch ($this->method) {
            case 'curl':
                return $this->curl($requestType, $url, $bodyFields, $headers);
            case 'socket':
                return $this->socket($requestType, $url, $bodyFields, $headers);
            case 'phpstream':
                return $this->phpStream();
            default:
                return ('change right request method type: curl or socket or phpstream');
        }
    }

    private function curl($requestType, $url, $bodyFields, $headers = ["Content-Type" => "application/json", "cache-control" => "no-cache"])
    {
        $curl = curl_init();
        curl_setopt_array(
            $curl, [
                //CURLOPT_PORT => "9025",
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $requestType,
                CURLOPT_POSTFIELDS => json_encode($bodyFields, JSON_UNESCAPED_UNICODE), //Собранный JSON
                CURLOPT_HTTPHEADER => $this->prepareHeaders($headers)
            ]
        );
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return "cURL Error during request: " . $err;
        } else {
            return $response;
        }
    }

    private function socket()
    {
        $url = parse_url(''); // url
        $requestArray = array('var' => 'value');
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($sock, $url['host'], ((isset($url['port'])) ? $url['port'] : 80));
        if (!$sock) {
            throw new Exception('Connection could not be established');
        }

        $request = '';
        if (!empty($requestArray)) {
            foreach ($requestArray as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $v2) {
                        $request .= urlencode($k) . '[]=' . urlencode($v2) . '&';
                    }
                } else {
                    $request .= urlencode($k) . '=' . urlencode($v) . '&';
                }
            }
            $request = substr($request, 0, -1);
        }
        $data = "POST " . $url['path'] . ((!empty($url['query'])) ? '?' . $url['query'] : '') . " HTTP/1.0\r\n"
            . "Host: " . $url['host'] . "\r\n"
            . "Content-type: application/x-www-form-urlencoded\r\n"
            . "User-Agent: PHP\r\n"
            . "Content-length: " . strlen($request) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $request . "\r\n\r\n";
        socket_send($sock, $data, strlen($data), 0);

        $result = '';
        do {
            $piece = socket_read($sock, 1024);
            $result .= $piece;
        } while ($piece != '');
        socket_close($sock);
        // TODO: Add Header Validation for 404, 403, 401, 500 etc.
        echo $result;
    }

    private function phpStream()
    {
        $postdata = http_build_query(
            array(
                'var1' => 'some content',
                'var2' => 'doh'
            )
        );
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context = stream_context_create($opts);
        $result = file_get_contents('http://example.com/submit.php', false, $context);
        return $result;
    }

    private function prepareHeaders($headers)
    {
        $flattened = [];
        foreach ($headers as $key => $header) {
            if (is_int($key)) {
                $flattened[] = $header;
            } else {
                $flattened[] = $key . ': ' . $header;
            }
        }
        return $flattened;//implode("\r\n", $flattened);
    }
}

$repl = new ReplicateLeadsToCoreTable();

$newLeads = $repl->getNewLeads();
if (empty($newLeads)) {
    dump('Новых лидов для репликации не найдено');
    $this->config->setParam('replicate_to_core_process_status',1);
} else {
    $result = $repl->sendLeadsToCoreTable($newLeads);
    dump($result);
    $this->config->setParam('replicate_to_core_process_status',1);
    die();
}

/*
  Создание нового лида https://connect.brainysoft.ru/documentation/page/453
  элементарный сценарий по созданию нового лида: https://connect.brainysoft.ru/documentation/article/336
*/
$url = 'https://core.brainysoft.ru:9025/bs-core/main/leads/';//Запрос на создание лида
$bodyFields = [
    "firstName" => '12',
    "mobilePhone" => '312'
];

$url = 'https://core.brainysoft.ru:9025/bs-core/main/leads/partial-load';//Поиск по лидам https://connect.brainysoft.ru/documentation/page/735
$bodyFields = [
    "fields" => [
        "id",
        "channel",
        "firstName",
    ],
    "countTo" => 10
];
$url = 'https://core.brainysoft.ru:9025/bs-core/main/leads/450';//Получение лида по ID https://connect.brainysoft.ru/documentation/page/451

$bsauth = '=='; //fastmoney
$headers = [
    "Content-Type" => "application/json",
    "bsauth" => "$bsauth",
    "cache-control" => "no-cache"
];
$request = new Request('curl');
$response = $request->run('GET', $url, $bodyFields, $headers);
dump($response);

//dump($repl->getExecutionTime());

