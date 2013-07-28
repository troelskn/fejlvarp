<?php
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
  throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

error_reporting(E_ALL);
set_error_handler('exception_error_handler');
include(dirname(__FILE__).'/../config.php');
if (is_file(dirname(__FILE__).'/../config.local.php')) {
  include(dirname(__FILE__).'/../config.local.php');
}
$db = new pdo($db_dsn, $db_username, $db_password);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function fejlvarp_log($hash, $subject, $data) {
  global $db;
  $db->beginTransaction();
  $q = $db->prepare("SELECT hash, resolved_at FROM incidents WHERE hash = :hash");
  $q->execute(array(':hash' => $hash));
  $row = $q->fetch();
  $notification = null;
  if ($row) {
    if ($row['resolved_at']) {
      $notification = "REOPEN";
    }
    $q = $db->prepare("UPDATE incidents SET occurrences = occurrences + 1, last_seen_at = NOW(), resolved_at = null, subject = :subject, data = :data WHERE hash = :hash");
    $q->execute(
      array(
        ':subject' => $subject,
        ':data' => $data,
        ':hash' => $hash));
  } else {
    $notification = "NEW";
    $q = $db->prepare("INSERT INTO incidents (hash, subject, data, occurrences, created_at, last_seen_at) VALUES (:hash, :subject, :data, 1, NOW(), NOW())");
    $q->execute(
      array(
        ':hash' => $hash,
        ':subject' => $subject,
        ':data' => $data));
  }
  $db->commit();
  if ($notification) {
    fejlvarp_notify($notification, fejlvarp_find_incident($hash));
  }
}

function fejlvarp_prune_old() {
  global $db;
  $db->exec("UPDATE incidents SET resolved_at = NOW() WHERE last_seen_at < DATE_ADD(NOW(), INTERVAL -1 DAY)");
}

function fejlvarp_find_incident($hash) {
  global $db;
  $q = $db->prepare("SELECT * FROM incidents WHERE hash = :hash");
  $q->execute(array(':hash' => $hash));
  $row = $q->fetch(PDO::FETCH_ASSOC);
  $decoded = json_decode($row['data'], true);
  if ($decoded) {
    $row['data'] = $decoded;
  }
  return $row;
}

function fejlvarp_select_incidents($include_closed = false) {
  global $db;
  $sql = "SELECT hash, subject, occurrences, created_at, last_seen_at, resolved_at FROM incidents";
  if (!$include_closed) {
    $sql .= " WHERE resolved_at IS NULL";
  }
  $sql .= " ORDER BY last_seen_at DESC";
  $q = $db->prepare($sql);
  $q->execute();
  $result = array();
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $result[] = $row;
  }
  return $result;
}

function fejlvarp_notify($notification, $row) {
  global $server_name;
  $title = "[$notification] " . $row['subject'];
  $msg = var_export($row, true);
  $uri = $server_name . '?hash='.rawurlencode($row['hash']);
  foreach (array('notify_mail', 'notify_pushover') as $fn) {
    $fn($title, $msg, $uri);
  }
}

function notify_mail($title, $msg, $uri) {
  global $mail_recipient;
  if (isset($mail_recipient) && $mail_recipient) {
    mail($mail_recipient, $title, "An incident has occurred. Once you have resolved the issue, please visit the following link and mark it as such:\n\n" . $uri . "\n\n------------\n\n" . $msg);
  }
}

function notify_pushover($title, $msg, $uri) {
  // https://pushover.net/api
  global $pushover_userkey, $pushover_apitoken;
  if (isset($pushover_apitoken) && $pushover_apitoken) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://api.pushover.net/1/messages.json');
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, array(
      'token' => $pushover_apitoken,
      'user' => $pushover_userkey,
      'title' => $title,
      'message' => $msg,
      'url' => $uri,
      'url_title' => 'See incident'
    ));
    curl_exec($curl);
  }
}

function ago($time) {
  $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
  $lengths = array("60","60","24","7","4.35","12","10");
  $now = time();
  $difference = $now - strtotime($time);
  $tense = "ago";
  for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
    $difference /= $lengths[$j];
  }
  $difference = round($difference);
  if ($difference != 1) {
    $periods[$j] .= "s";
  }
  return "$difference $periods[$j] $tense ";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (empty($_POST) && isset($_GET['hash'])) {
    $q = $db->prepare("UPDATE incidents SET resolved_at = NOW() WHERE hash = :hash");
    $q->execute(
      array(
        ':hash' => $_GET['hash']));
    header("Location: " . $server_name . '?hash=' . rawurlencode($_GET['hash']), true, '303');
    exit;
  } elseif (empty($_POST) && isset($_GET['prune'])) {
    fejlvarp_prune_old();
    header("Location: " . $server_name, true, '303');
    exit;
  } else {
    $required_options = array('hash', 'subject', 'data');
    foreach ($required_options as $name) {
      if (!isset($_POST[$name]) || ($_POST[$name] == "")) {
        throw new Exception("Missing argument $name");
      }
    }
    fejlvarp_log($_POST['hash'], $_POST['subject'], $_POST['data']);
    echo "OK";
    exit;
  }
}
?>
<html>
  <head>
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="google" value="notranslate" />
    <meta name="format-detection" content="telephone=no" />
    <title>Incidents</title>
<style type="text/css">
* { font-family: "Monaco", "Courier New", monospace; font-size: 12px; }
body { -webkit-text-size-adjust: none; margin: 0; padding: 0; }
h1,h2,h3,h4 { font-family: "Lucida Grande", Helvetica, Arial, Freesans, Clean, sans-serif; }
h1 { font-size: 200%; }
h2 { font-size: 150%; }
h3 { font-size: 125%; }
h4 { font-size: 110%; }
.resolved, .open { padding: 2px; border-radius: 2px; color: white; }
.resolved { background-color: green; }
.open { background-color: red; }
h1 .resolved, h1 .open { vertical-align: middle; }
h1 img { margin-right: 8px; vertical-align: middle; }

a:hover { text-decoration: none; }
table.list { width: 100%; margin: 0; padding: 0; border-collapse: collapse; border-spacing: 0; border: 1px solid #d8d8d8; -moz-box-shadow: 0 0 3px rgba(0,0,0,0.2); -webkit-box-shadow: 0 0 3px rgba(0,0,0,0.2); box-shadow: 0 0 3px rgba(0,0,0,0.2); }
table.list td, table.list th { padding: 5px; }
table.list td.nobreak { white-space: nowrap; }
table.list th { color: #afafaf; font-weight: normal; }
table.list tr {
  background-color: #eaeaea;
  height: 2.5em;
  border-bottom: 1px solid #e1e1e1;
  text-align: left;
}
table.list tbody tr {
  background: #F9F9F9; /* old browsers */
  background: -moz-linear-gradient(top, #F9F9F9 0%, #EFEFEF 100%); /* firefox */
  background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#F9F9F9), color-stop(100%,#EFEFEF)); /* webkit */
  filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#F9F9F9', endColorstr='#EFEFEF',GradientType=0 ); /* ie */
  color: #545454;
}

#page-header {
  padding: 0 27px;
  margin-bottom: 20px;
  padding-top: 8px;
  background: #FFFFFF; /* old browsers */
  background: -moz-linear-gradient(top, #FFFFFF 0%, #F5F5F5 100%); /* firefox */
  background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#FFFFFF), color-stop(100%,#F5F5F5)); /* webkit */
  filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#FFFFFF', endColorstr='#F5F5F5',GradientType=0 ); /* ie */
  border-bottom: 1px solid #dfdfdf;
}
.page-content {
  padding: 0 27px;
}
.action {
  margin: 0 auto 15px auto;
  background: #eaf2f5;
  border: 1px solid #bedce7;
  padding: 5px;
}
.action span { padding: 2px; border-radius: 2px; color: white; background-color: #333; }
pre { border: 1px solid #fadd87; background-color: #fbecc5; padding: 4px; overflow-y: auto; }
table.definitionlist th { min-width: 12em; text-align: left; font-weight: bold; }
table.definitionlist th,
table.definitionlist td { padding: 5px;  }
</style>
  </head>
  <body>
<?php
$geoip = null;
$user_agent = null;
if (isset($_GET['hash'])) {
  $incident = fejlvarp_find_incident($_GET['hash']);
  echo "<div id=\"page-header\">";
  echo "<p><a href=\"", htmlspecialchars($server_name), "\"><span style=\"font-weight:bold;font-size:32px;line-height:8px;\">&larr;</span> List all incidents</a></p>\n";
  echo "<h1>", htmlspecialchars($incident['subject']), "\n";
  echo $incident['resolved_at'] ? '<span class="resolved">RESOLVED</span>' : '<span class="open">OPEN</span>';
  echo "</h1>\n";
  echo "</div>";

  echo "<div class=\"page-content\">";

  if (!$incident['resolved_at']) {
    echo "<form method=\"post\" action=\"?hash=", htmlspecialchars(rawurlencode($incident['hash'])), "\">\n";
    echo "<div class=\"action\">\n";
    echo "<p>If the incident has been resolved, please mark it by pressing this button:</p>";
    echo "<p><input type=\"submit\" value=\"Mark Resolved\" /></p>";
    echo "</div>\n";
    echo "</form>\n";
  }

  echo "<table class=\"definitionlist\">\n<tbody>\n";
  foreach (array('hash', 'occurrences', 'created_at', 'last_seen_at', 'resolved_at') as $name) {
    if (isset($incident[$name])) {
      echo "<tr><th>", htmlspecialchars($name), "</th><td>", htmlspecialchars($incident[$name]), "</td></tr>\n";
    }
  }
  echo "</tbody>\n</table>\n";

  if (isset($incident['data']['error']['type'])) {
    echo "<h2>Error Details</h2>\n";
    echo "<table class=\"definitionlist\">\n<tbody>\n";
    foreach (array('type', 'code', 'file', 'line') as $name) {
      if (isset($incident['data']['error'][$name])) {
        echo "<tr><th>", htmlspecialchars($name), "</th><td>", htmlspecialchars($incident['data']['error'][$name]), "</td></tr>\n";
      }
    }
    echo "</tbody>\n</table>\n";
    echo "<h2>Trace</h2>\n";
    echo "<pre>", htmlspecialchars($incident['data']['error']['trace']), "</pre>\n";
  }

  if (isset($incident['data']['environment']['SERVER'])) {
    echo "<h2>Request Synopsis</h2>\n";
    echo "<table class=\"definitionlist\">\n<tbody>\n";
    foreach (array('HTTP_HOST', 'REQUEST_URI', 'SERVER_ADDR', 'HTTP_REFERER') as $name) {
      if (isset($incident['data']['environment']['SERVER'][$name])) {
        echo "<tr><th>", htmlspecialchars($name), "</th><td>", htmlspecialchars($incident['data']['environment']['SERVER'][$name]), "</td></tr>\n";
      }
    }
    if (isset($incident['data']['environment']['SERVER']['HTTP_USER_AGENT'])) {
      $user_agent = $incident['data']['environment']['SERVER']['HTTP_USER_AGENT'];
      echo "<tr><th>HTTP_USER_AGENT</th><td>", htmlspecialchars($user_agent), " <span id=\"useragent\">Loading ...</span></td></tr>\n";
    }
    if (isset($incident['data']['environment']['SERVER']['HTTP_X_FORWARDED_FOR'])) {
      $geoip = $incident['data']['environment']['SERVER']['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($incident['data']['environment']['SERVER']['REMOTE_ADDR'])) {
      $geoip = $incident['data']['environment']['SERVER']['REMOTE_ADDR'];
    }
    if ($geoip) {
      echo "<tr><th>CLIENT_IP</th><td>", htmlspecialchars($geoip), " <span id=\"geoip\">Loading ...</span></td></tr>\n";
    }
    echo "</tbody>\n</table>\n";
  }

  if (isset($incident['data']['environment'])) {
    echo "<h2>Request Context</h2>\n";
    echo "<pre>", htmlspecialchars(var_export($incident['data']['environment'], true)), "</pre>\n";
  }

  if (!isset($incident['data']['error']['type']) && !isset($incident['data']['environment'])) {
    echo "<h2>Data</h2>\n";
    echo "<pre>", htmlspecialchars(var_export($incident['data'], true)), "</pre>\n";
  }

  echo "</div>";

} else {
  echo "<div id=\"page-header\"><h1><img src=\"/favicon.ico\" height=\"32px\" width=\"32px\" />Incidents</h1></div>";
  echo "<div class=\"page-content\">";

  echo "<div class=\"action\">\n";
  $show_all = isset($_GET['show']) && $_GET['show'] == "all";
  if ($show_all) {
    echo "<p>Show <a href=\"", htmlspecialchars($server_name), "\">just open incidents</a> or <span>all incidents</span></p>";
  } else {
    echo "<p>Show <span>just open incidents</span> or <a href=\"", htmlspecialchars($server_name), "?show=all\">all incidents</a></p>";
  }
  echo "</div>\n";

  $incidents = fejlvarp_select_incidents($show_all);
  if (empty($incidents)) {
    echo "<p>There are no incidents to show</p>\n";
  } else {
    echo "<table class=\"list\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th>State</th>\n";
    echo "<th>Subject</th>\n";
    echo "<th>Created</th>\n";
    echo "<th>Last seen</th>\n";
    echo "<th>Occurrences</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    foreach ($incidents as $incident) {
      echo "<tr>\n";
      echo "<td class=\"nobreak\">", ($incident['resolved_at'] ? '<span class="resolved">RESOLVED</span>' : '<span class="open">OPEN</span>'), "</td>\n";
      echo "<td><a href=\"?hash=", htmlspecialchars(rawurlencode($incident['hash'])), "\">", htmlspecialchars($incident['subject']), "</a></td>\n";
      echo "<td class=\"nobreak\">", htmlspecialchars(ago($incident['created_at'])), "</td>\n";
      echo "<td class=\"nobreak\">", htmlspecialchars(ago($incident['last_seen_at'])), "</td>\n";
      echo "<td class=\"nobreak\">", htmlspecialchars($incident['occurrences']), "</td>\n";
      echo "</tr>\n";
    }
    echo "</tbody>\n";
    echo "</table>\n";
  }

  echo "<br/>\n";
  echo "<div class=\"action\">\n";
  echo "<form method=\"post\" action=\"?prune\">\n";
  echo "<p><input type=\"submit\" value=\"Prune old incidents\" /></p>";
  echo "</form>\n";
  echo "</div>\n";

  echo "</div>";

}

?>
  </body>
<?php if ($user_agent): ?>
  <script type="text/javascript">
  function useragentCallback(data) {
    document.getElementById("useragent").innerHTML = data.name ? ("[" + data.type + " - " + data.info + "]") : "";
  }
  </script>
  <script type="text/javascript" src="/api.php?action=useragent&useragent=<?php echo $user_agent; ?>&callback=useragentCallback"></script>
<?php endif; ?>
<?php if ($geoip): ?>
  <script type="text/javascript">
  function geoipCallback(data) {
  document.getElementById("geoip").innerHTML = data.country_name ? ("[" + data.country_name + (data.region_name && (" - " + data.region_name)) + "]") : "";
  }
  </script>
  <script type="text/javascript" src="/api.php?action=geoip&ip=<?php echo $geoip; ?>&callback=geoipCallback"></script>
<?php endif; ?>
</html>
