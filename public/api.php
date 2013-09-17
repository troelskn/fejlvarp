<?php
$action = @$_GET['action'];
if (!in_array($action, array('geoip', 'useragent'))) {
  header("HTTP/1.0 404 Not found");
  exit;
}
if ($action == "geoip") {
  $ip = $_GET['ip'];
  $parts = explode(",", $_GET['ip']);
  $url = "http://freegeoip.net/json/" . rawurlencode($parts[0]);
  $json = file_get_contents($url);
  $data = json_decode($json, true);
  $response = array(
    'country_name' => $data['country_name'],
    'region_name' => $data['region_name']
  );
} elseif ($action == "useragent") {
  $url = "http://www.useragentstring.com/?getJSON=all&uas=" . rawurlencode($_GET['useragent']);
  $opts = array(
    'http' => array(
      'method' => "GET",
      'header' =>
      "Accept: application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5\r\n".
      "User-Agent: Fejlvarp (http://github.com/troelskn/fejlvarp)\r\n".
      "Accept-Language: en-US,en;q=0.8\r\n".
      "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3\r\n"
    )
  );
  $context = stream_context_create($opts);
  $raw = file_get_contents($url, false, $context);
  $data = json_decode($raw, true);
  $response = array();
  $response['name'] = $data['agent_name'];
  $response['type'] = $data['agent_type'];
  $response['info'] = implode(" / ", array_filter(array_values($data)));
}

header("Content-Type: text/javascript");
$content = json_encode($response);
if (isset($_GET['callback'])) {
  echo $_GET['callback'] . "(" . $content . ");";
} else {
  echo $content;
}