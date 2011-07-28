<?php
$action = @$_GET['action'];
if (!in_array($action, array('geoip', 'useragent'))) {
  header("HTTP/1.0 404 Not found");
  exit;
}
if ($action == "geoip") {
  $url = "http://freegeoip.net/json/" . $_GET['ip'];
  $json = file_get_contents($url);
  $data = json_decode($json, true);
  $response = array(
    'country_name' => $data['country_name'],
    'region_name' => $data['region_name']
  );
} elseif ($action == "useragent") {
  $url = "http://www.aqtronix.com/useragents/?Action=ShowAgentDetails&Name=" . rawurlencode($_GET['useragent']);
  $opts = array(
    'http' => array(
      'method' => "GET",
      'header' =>
      "Cache-Control: max-age=0\r\n".
      "Accept: application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5\r\n".
      "User-Agent: Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6;en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.102 Safari/534.13\r\n".
      "Accept-Language: en-US,en;q=0.8\r\n".
      "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3\r\n"
    )
  );
  $context = stream_context_create($opts);
  $html = file_get_contents($url, false, $context);
  $response = array();
  if (preg_match('~<tr><td width=150>Name:</td><td>(.+?)</td>~', $html, $reg)) {
    $response['name'] = $reg[1];
  }
  if (preg_match('~<tr><td width=150>Type\(s\):</td>\s*\n\s*<td><strong>(.+?)</strong>~', $html, $reg)) {
    $response['type'] = implode(", ", array_filter(explode('<br>', $reg[1])));
  }
  if (preg_match('~<td valign=top>Info:</td><td>(.+?)</td>~', $html, $reg)) {
    $response['info'] = trim(preg_replace('~<br[/]?>~i', "\n", $reg[1]));
  }
}

header("Content-Type: text/javascript");
$content = json_encode($response);
if (isset($_GET['callback'])) {
  echo $_GET['callback'] . "(" . $content . ");";
} else {
  echo $content;
}