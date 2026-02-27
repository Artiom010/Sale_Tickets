<?php
define('BOT_TOKEN', '7831703732:AAFMpmw8mG_IEfmCkQHgeG--XEyyDX7R854');
define('API_URL',   'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('STATE_DIR', __DIR__ . '/state');
define('DEBUG_LOG', __DIR__ . '/debug.log');
define('COMPLETE_LOG', __DIR__ . '/maib_complete.log');

// functii utilitare
function logStep($msg) {
    file_put_contents(COMPLETE_LOG, date('[Y-m-d H:i:s] ').$msg."\n", FILE_APPEND);
}
function loadState($chat_id) {
    $file = STATE_DIR . "/state_{$chat_id}.json";
    logStep("loadState($chat_id)");
    return file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : [];
}
function saveState($chat_id, $state) {
    $file = STATE_DIR . "/state_{$chat_id}.json";
    file_put_contents($file, json_encode($state));
    logStep("saveState($chat_id)");
}
function sendMessage($chat_id, $text, $reply_markup = null) {
    $payload = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $payload['reply_markup'] = is_string($reply_markup)
            ? $reply_markup
            : json_encode($reply_markup);
    }
    file_get_contents(API_URL . "sendMessage?" . http_build_query($payload));
    logStep("sendMessage to $chat_id: ".trim($text));
}
function post_api($url, $params = [], $urlencoded = true, $user = null, $pass = null) {
    logStep("post_api to $url with ".json_encode($params, JSON_UNESCAPED_UNICODE));
    $ch = curl_init($url);
    if ($urlencoded) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    if ($user && $pass) curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);
    logStep("post_api response: ".$resp);
    return $resp;
}
function maib_get_access_token() {
    $data = [
        "projectId"     => "9B9C19AE-DC32-4128-9249-16412CCD7E6B",
        "projectSecret" => "efb8506c-0afb-4430-8e33-5b0336a18ccf"
    ];
    logStep("Requesting MAIB token");
    $ch = curl_init('https://api.maibmerchants.md/v1/generate-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    logStep("MAIB token response: ".$result);
    $json = json_decode($result, true);
    return $json['result']['accessToken'] ?? false;
}
function trimite_pdf($chat_id, $detalii, $locuri, $amount) {
    logStep("Generating PDF for $chat_id");
    require_once(__DIR__ . '/fpdf/fpdf.php');
    $pdf = new FPDF('P','mm',[80,120]);
    $pdf->AddPage();
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,8,'Bilet / Bon pentru cursa',0,1,'C');
    $pdf->Ln(2);
    $pdf->Cell(0,7,'Ruta: '.$detalii['plecare'],0,1);
    $pdf->Cell(0,7,'Data: '.$detalii['data_td'].'  Ora: '.$detalii['ora'],0,1);
    $pdf->Cell(0,7,'Locuri: '.implode(', ',$locuri),0,1);
    $pdf->Cell(0,7,'Total: '.number_format($amount,2,'.','').' MDL',0,1);
    $pdf->Ln(4);
    $pdf->Cell(0,6,'Va dorim drum bun!',0,1,'C');
    $tmp = __DIR__ . "/bilet_{$chat_id}.pdf";
    $pdf->Output('F', $tmp);

    $ch2 = curl_init(API_URL.'sendDocument');
    $cfile = new CURLFile($tmp,'application/pdf','Bilet.pdf');
    curl_setopt_array($ch2, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chat_id,
            'caption'  => 'Biletul tau electronic',
            'document' => $cfile
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch2);
    curl_close($ch2);
    unlink($tmp);
    logStep("PDF sent and file deleted");
}

// ===== MAIN =====
$chat_id = $_GET['chat_id'] ?? null;
$payId   = $_GET['payId']   ?? null;
$data    = $_GET['data']    ?? null;
logStep("ENTER MAIN with ".json_encode($_GET));

// 1) Retrimitere bon
if ($data === 're_emit_bon' && $chat_id) {
    logStep("Handler re_emit_bon");
    $state = loadState($chat_id);
    $idx   = $state['selectat_cursa'] ?? null;
    $det   = $state['curse_data'][$idx] ?? [];
    $loc   = $state['locuri_rezervate'] ?? [];
    if (empty($loc)) {
        $loc = $state['rezervare']['locuri']
             ?? (is_array($det['locuri']) ? $det['locuri'] : explode(',', $det['locuri'] ?? '1'));
    }
    $nr    = count($loc);
    $tarif = (float)preg_replace('/[^0-9\.]/','',str_replace(',','.',$det['tarif']??0));
    $amt   = round($tarif * $nr, 2);
    trimite_pdf($chat_id, $det, $loc, $amt);
    exit;
}

// 2) Anulare rezervare
if ($data === 'cancel_paid' && $chat_id) {
    logStep("Handler cancel_paid");
    $state = loadState($chat_id);
    $code  = $state['reservation_code'] ?? null;
    if (!$code) {
        sendMessage($chat_id, "❌ Nu gasesc codul de rezervare pentru anulare.");
        exit;
    }
    $cres = json_decode(
        file_get_contents("https://gam2022.unisim-soft.com/widget_una/include/cancel_reservation.php?reservation_code={$code}"),
        true
    );
    sendMessage(
        $chat_id,
        !empty($cres['type']) && $cres['type']==='success'
            ? "✅ Rezervarea a fost anulata cu succes."
            : "❌ Eroare la anulare. Te rog contacteaza suportul."
    );
    exit;
}

// 3) Confirmare plata MAIB si rezervare dupa plata
if ($chat_id && $payId && !$data) {
    logStep("Handler complete payment for payId=$payId");
    $state = loadState($chat_id);

    // 3.1) confirmare plata
    $token = maib_get_access_token();
    if (!$token) {
        sendMessage($chat_id, "❌ Eroare: nu am putut obtine access_token MAIB.");
        logStep("MAIB token missing, abort");
        exit;
    }

    logStep("Calling MAIB complete for payId");
    $ch = curl_init("https://api.maibmerchants.md/v1/complete");
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => json_encode(['payId'=>$payId]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    logStep("MAIB complete response: ".$res);
    $res = json_decode($res, true);

    if (empty($res['result']['status']) || $res['result']['status']!=='OK') {
        sendMessage($chat_id, "⚠️ Plata nu a fost confirmata. Te rog incearca din nou.");
        exit;
    }

    sendMessage($chat_id, "✅ Plata a fost procesata cu succes! Continuam rezervarea...");
    logStep("Payment confirmed, proceeding to reserve.php");

    // 3.2) parametrizare si apel reserve.php
    $detalii    = $state['curse_data'][$state['selectat_cursa']];
    $code       = $detalii['code'];
    $date       = $detalii['data'];
    $station    = $state['destPoint'];
    $startPoint = $state['startPoint'];
    $locuri     = $state['locuri_rezervate'];
    $nr         = count($locuri);
    $input      = $state['input_pass_data'][0] ?? [];
    $parts      = explode(' ', trim($input['name'] ?? ''), 2);

    $params = [
        'route'      => $code,
        'RouteCode'  => $code,
        'data'       => date('d.m.Y', strtotime($date)),
        'biletcount' => $nr,
        'locuri'     => implode(',', $locuri),
        'station'    => $station,
        'startPoint' => $startPoint,
        'first_name' => $parts[0] ?? '',
        'last_name'  => $parts[1] ?? '',
        'phone'      => $input['phone'] ?? '',
        'email'      => $input['email'] ?? '',
    ];

    logStep("Reserve params: ".json_encode($params, JSON_UNESCAPED_UNICODE));
    $resp = post_api(
        "https://gam2022.unisim-soft.com/widget_una/include/reserve.php",
        $params,
        true,
        'Unisimso',
        's0ft2025Web'
    );

    logStep("reserve.php response: ".$resp);
    $jr = json_decode($resp, true);
    if (empty($jr['type']) || $jr['type']!=='success') {
        sendMessage($chat_id, "❌ Rezervarea a esuat dupa plata: ".($jr['error'] ?? $resp));
        exit;
    }

    $state['reservation_code'] = $jr['reservation_code'];
    saveState($chat_id, $state);
    logStep("Reservation succeeded, code ".$jr['reservation_code']);

    // 3.3) trimitere PDF
    $tarif  = (float)preg_replace('/[^0-9\.]/','',str_replace(',','.',$detalii['tarif']??0));
    $amount = round($tarif * $nr, 2);
    trimite_pdf($chat_id, $detalii, $locuri, $amount);

    // 3.4) butoane anulare / reemitere
    $kbd = ['inline_keyboard'=>[
        [['text'=>'❌ Anuleaza rezervarea','callback_data'=>'cancel_paid'],
         ['text'=>'🔄 Retrimite bon','callback_data'=>'re_emit_bon']]
    ]];
    sendMessage($chat_id,
        "Daca doresti sa anulezi rezervarea sau sa retrimiti bonul, apasa aici:",
        $kbd
    );

    // 3.5) pagina HTML finala
    $bot  = "BIL_GARA_MDbot";
    $link = "https://t.me/{$bot}";
    logStep("Rendering final HTML redirect");
    echo <<<HTML
<!doctype html>
<html lang="ro">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="refresh" content="5;url={$link}">
  <style>
    body{background:#f6fff4;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:Montserrat,sans-serif}
    .card{background:#fff;border-radius:1.5em;box-shadow:0 6px 32px #0f61261a;padding:2em;text-align:center;max-width:350px}
    .loader{border:4px solid #e1ffe7;border-top:4px solid #22c55e;border-radius:50%;width:36px;height:36px;animation:spin 1s linear infinite;margin:1em auto}
    @keyframes spin{100%{transform:rotate(360deg)}}
    .button{background:#22c55e;color:#fff;padding:.8em 1.7em;border:none;border-radius:999px;text-decoration:none;box-shadow:0 1px 8px #22c55e22;transition:.2s}
    .button:hover{background:#16a34a}
  </style>
</head>
<body>
  <div class="card">
    <h1>Plata efectuata cu succes!</h1>
    <div class="loader"></div>
    <p>Vei fi redirectionat in <span id="t">5</span>s</p>
    <a class="button" href="{$link}">Inapoi la bot</a>
  </div>
  <script>
    let s=5;setInterval(()=>{if(--s>0)document.getElementById('t').innerText=s},1000);
  </script>
</body>
</html>
HTML;
    exit;
}

// fallback
logStep("Fallback: parametri lipsa sau invalidi");
echo "<h1>Lipsesc parametri sau actiune invalida</h1>";
