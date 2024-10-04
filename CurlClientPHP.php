<?php

namespace CurlClientPHP;

class CurlClientPHP
{

    private $base_url;
    private $headers;
    private $params;
    private $secure;
    private $proxy = "";

    public function __construct(String $base_url, Array $headers, Array $params = [], Bool $secure = false)
    {
        $this->base_url = $base_url;
        $this->headers = $headers;
        $this->params = $params;
        $this->secure = $secure;
    }
    public function setProxy(String $proxy){
        $this->proxy = $proxy;
        return true;
    }
    public function getProxy(String $proxy){
        return $this->proxy = $proxy;
    }
    public function sendRequest(String $endpoint_uri, Array $parameters = [], String $request_type = 'GET') : array
    {
        try {

            $ch = curl_init();
            $url = $this->base_url . $endpoint_uri;
            if(isset($this->params['CURLOPT_USERPWD'])) curl_setopt($ch, CURLOPT_USERPWD, $this->params['username'] . ":" . $this->params['password']);
            if($request_type !== 'GET') curl_setopt($ch, CURLOPT_POST, $request_type);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_FAILONERROR,false);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600); //timeout in seconds

            if(in_array($request_type, ['POST', 'PUT', 'PATCH']) && empty($parameters)){
                throw new \Exception("Payload is required for this request type", 100);
            }

            foreach ($this->headers as $key => $header){
                if(stripos($header, 'Content-Type') !== FALSE){
                    unset($this->headers[$key]);
                }
            }

            if(!empty($parameters)){
                if($request_type === 'GET'){
                    $url .= sprintf("?%s", http_build_query($parameters));
                }elseif(in_array($request_type, ['POST', 'PUT', 'PATCH'])){
                    if(isset($parameters['file_upload']) && $parameters['file_upload'] === true) {
                        array_push($this->headers, 'Content-Type: '.$parameters['file_type']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, @file_get_contents($parameters['file']));
                        $url .= '?table_name=' . $parameters['table_name'] . '&table_sys_id=' . $parameters['table_sys_id'] . '&file_name=' . urlencode(basename($parameters['file']));
                    }else{
                        $payload = json_encode($parameters, JSON_FORCE_OBJECT);
                        array_push($this->headers, 'Content-Type: application/json');
                        if($request_type !== 'PUT'){
                            array_push($this->headers, 'Content-Length: ' .strlen($payload));
                        }
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    }
                }
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

            if(!$this->secure){
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            $result = curl_exec($ch);
            if($result === false){
                throw new \Exception("(". curl_errno($ch) . ") " . curl_strerror(curl_errno($ch)) . " - " . curl_error($ch), 101);
            }
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if($result === '' || $response_code == 204){
                throw new \Exception("No match found!", 404);
            }

            $response = json_decode($result,true, 512, JSON_THROW_ON_ERROR);

            return ["status" => true, "data" => $response['data'], "code" => $response_code];
        } catch (\Throwable $e) {
            return ["status" => false, "code" => $e->getCode(), "data" => $e->getMessage() . "\nCURL Result: $result\n"   , "trace" => $e->getTraceAsString(), "timestamp" => date("Y-m-d H:i:s")];
        }
    }

}