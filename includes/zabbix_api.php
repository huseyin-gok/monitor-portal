<?php
// Zabbix API Sınıfı
class ZabbixAPI {
    private $url;
    private $authToken = null;
    private $username;
    private $password;
    
    public function __construct($url, $username, $password) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
    }
    
    private function request($method, $params = []) {
        $data = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1
        ];
        
        if ($this->authToken) {
            $data['auth'] = $this->authToken;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Zabbix API hatası: HTTP $httpCode");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("Zabbix API hatası: " . $result['error']['message']);
        }
        
        return $result['result'] ?? null;
    }
    
    public function login() {
        $result = $this->request('user.login', [
            'user' => $this->username,
            'password' => $this->password
        ]);
        
        $this->authToken = $result;
        return $this->authToken;
    }
    
    public function getGraphs($graphIds = []) {
        if (!$this->authToken) {
            $this->login();
        }
        
        $params = [
            'output' => ['graphid', 'name', 'width', 'height'],
            'selectGraphItems' => ['itemid', 'color', 'calc_fnc', 'type']
        ];
        
        if (!empty($graphIds)) {
            $params['graphids'] = $graphIds;
        }
        
        return $this->request('graph.get', $params);
    }
    
    public function getGraphImage($graphId, $width = 800, $height = 200, $period = 3600) {
        if (!$this->authToken) {
            $this->login();
        }
        
        // Zabbix grafik URL'i
        $url = str_replace('/api_jsonrpc.php', '', $this->url);
        $url .= "/chart2.php?graphid=$graphId&width=$width&height=$height&period=$period";
        
        // Auth token ile cookie ekle
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, "zbx_sessionid=" . $this->authToken);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $image = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return base64_encode($image);
        }
        
        return null;
    }
    
    public function getHosts() {
        if (!$this->authToken) {
            $this->login();
        }
        
        return $this->request('host.get', [
            'output' => ['hostid', 'host', 'name', 'status']
        ]);
    }
}
