<?php
class Proxmox {

  private $hostname;
  private $port;
  private $username;
  private $password;
  private $realm;
  private $ch;
  public $logintime;

  static private $headers = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => false,
    CURLOPT_HTTPHEADER => [ 'Transfer-Encoding: ' ]
  ];

  public function __construct($hostname, $username, $password, $realm = pam, $port = 8006) {
    $this->hostname = $hostname;
    $this->port = $port;
    $this->username = $username;
    $this->password = $password;
    $this->realm = $realm;

    $this->ch = curl_init();

    curl_setopt_array($this->ch, self::$headers);

    $this->login();
  }

  public function login() {
    $result = $this->request('/access/ticket', "POST", [
      'username' => $this->username,
      'password' => $this->password,
      'realm' => $this->realm,
    ]);

    curl_setopt_array($this->ch, [
      CURLOPT_COOKIE => "PVEAuthCookie=".$result->ticket,
      CURLOPT_HTTPHEADER => array_merge(self::$headers[CURLOPT_HTTPHEADER], ['CSRFPreventionToken: '.$result->CSRFPreventionToken])
    ]);

    $this->logintime = microtime(true);
  }

  private function getUrl() {
    return 'https://' . $this->hostname . ':' . $this->port . '/api2/json';
  }

  public function request($actionpath, $method = "GET", $params = []) {

    $options = [
      CURLOPT_URL => $this->getUrl().$actionpath."?".http_build_query($params),
      CURLOPT_POST => null,
      CURLOPT_CUSTOMREQUEST => null,

    ];

    if ($method === "POST")
      $options[CURLOPT_POST] = true;

    if ($method === "PUT")
      $options[CURLOPT_CUSTOMREQUEST] = "PUT";

    curl_setopt_array($this->ch, $options);

    $result = curl_exec($this->ch);
    $error = curl_error($this->ch);
    $info = curl_getinfo($this->ch);

    if ($error !== "") {
      throw new Exception($error);
    }

    if ($info['http_code'] === 401) {
      throw new Exception('Invalid username or password');
    }

    if ($result===false) {
      throw new Exception('Error - No result');
    }

    return json_decode($result)->data;

  }
}
?>
