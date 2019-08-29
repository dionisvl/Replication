<?php


namespace Replication\ReplicateTableToApi;


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