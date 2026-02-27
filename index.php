<?php
// index.php
require __DIR__.'/vendor/autoload.php';
define('BOT_TOKEN','7831703732:AAFMpmw8mG_IEfmCkQHgeG--XEyyDX7R854');
define('API_URL',"https://api.telegram.org/bot".BOT_TOKEN."/");

if (isset($_GET['setWebhook'])) {
    $url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST']
         .dirname($_SERVER['REQUEST_URI']).'/webhook.php';
    $res = file_get_contents(API_URL.'setWebhook?url='.urlencode($url));
    echo "<pre>".htmlspecialchars($res)."</pre>";
    exit;
}

if (isset($_GET['getWebhook'])) {
    $res = file_get_contents(API_URL.'getWebhookInfo');
    echo "<pre>".htmlspecialchars($res)."</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="UTF-8"><title>Bot Bilete</title></head>
<body>
  <h1>Admin Bot Bilete</h1>
  <ul>
    <li><a href="?setWebhook=1">Setează Webhook</a></li>
    <li><a href="?getWebhook=1">Verifică Webhook</a></li>
  </ul>
</body>
</html>