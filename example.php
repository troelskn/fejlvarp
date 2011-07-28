<?php
include(dirname(__FILE__).'/config.php');
if (is_file(dirname(__FILE__).'/config.local.php')) {
  include(dirname(__FILE__).'/config.local.php');
}

$hash = md5("test");
$subject = "Test of fejlvarp";
$data = json_encode(array('test' => "test of data"));
$opts = array(
  'http' => array(
    'method' => "POST",
    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
    'content' => http_build_query(array('hash' => $hash, 'subject' => $subject, 'data' => $data))
  )
);
$context = stream_context_create($opts);
file_get_contents($server_name, false, $context);
