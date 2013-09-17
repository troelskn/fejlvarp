<?php
// This is a full example of how to setup fejlvarp logging in your app (the fejlvarp client)

// Configuration. You'd want to change these two.
$GLOBALS['FEJLVARP'] = array(
  // Configure URI of the fejlvarp service endpoint.
  'DESTINATION' => '',
  // Identify your application here.
  'APPLICATION_NAME' => 'My Webapp',
);

function fejlvarp_exception_handler($exception) {
  // Generate unique hash from message + file + line number
  // We strip out revision-part of the file name.
  // Assuming a standard capistrano deployment path, this will prevent duplicates across deploys.
  $hash = $GLOBALS['FEJLVARP']['APPLICATION_NAME'] . $exception->getMessage() . preg_replace('~revisions/[0-9]{14}/~', '--', $exception->getFile()) . $exception->getLine();
  $opts = array(
    'http' => array(
      'method' => "POST",
      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query(
        array(
          'hash' => md5($hash),
          'subject' => $exception->getMessage(),
          'data' => json_encode(
            array(
              'application' => $GLOBALS['FEJLVARP']['APPLICATION_NAME'],
              'error' => array(
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
              ),
              'environment' => array(
                'GET' => isset($_GET) ? $_GET : null,
                'POST' => isset($_POST) ? $_POST : null,
                'SERVER' => isset($_SERVER) ? $_SERVER : null,
                'SESSION' => isset($_SESSION) ? $_SESSION : null))))))
  );
  $context = stream_context_create($opts);
  file_get_contents($GLOBALS['FEJLVARP']['SERVER'], false, $context);
}

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
  throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

error_reporting(E_ALL);
set_error_handler('exception_error_handler');
set_exception_handler('fejlvarp_exception_handler');
