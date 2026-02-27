<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
define('DEBUG_LOG', __DIR__ . '/debug.log');

$state_dir = __DIR__ . '/state';
if (!is_dir($state_dir)) mkdir($state_dir, 0777, true);

$body = file_get_contents('php://input');
file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " RAW: $body\n", FILE_APPEND);

$update = json_decode($body, true);
if (!$update) { http_response_code(200); exit; }

define('BOT_TOKEN', '7831703732:AAFMpmw8mG_IEfmCkQHgeG--XEyyDX7R854');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');



// --- API & SEND/EDIT ---
function post_api($url, $params = [], $urlencoded = true, $username = null, $password = null) {
    $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    if ($urlencoded) {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Origin: https://gam2022.unisim-soft.com',
            'User-Agent: Mozilla/5.0'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($username && $password) {
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    }

    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}
function maib_get_access_token() {
    $data = [
        "projectId" => "9B9C19AE-DC32-4128-9249-16412CCD7E6B",
        "projectSecret" => "efb8506c-0afb-4430-8e33-5b0336a18ccf"
    ];
    $ch = curl_init('https://api.maibmerchants.md/v1/generate-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // DOAR PENTRU DEZVOLTARE:
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result, true);
    if (isset($json['result']['accessToken'])) {
        return $json['result']['accessToken'];
    }
    return false;
}



function sendToMAIB($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // ATENȚIE! DOAR ÎN DEZVOLTARE:
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Nu verifică certificatul
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // Nu verifică hostul certificatului

    $result = curl_exec($ch);

    if($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Eroare cURL: $error");
    }

    curl_close($ch);
    return $result;
}




function getMaibAccessToken() {
    $data = [
        "projectId" => "9B9C19AE-DC32-4128-9249-16412CCD7E6B",
        "projectSecret" => "efb8506c-0afb-4430-8e33-5b0336a18ccf"
    ];
    $ch = curl_init('https://api.maibmerchants.md/v1/generate-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);
    // În documentația MAIB, răspunsul e {"ok":true, "result":{...}}
    if ($json && $json['ok'] && isset($json['result']['accessToken'])) {
        return $json['result']['accessToken'];
    }
    return false;
}


function createQiwiCashBill($amount, $chat_id, $state, $detalii) {
    $auth_resp = post_json_api(
        'https://api.qiwi.md/v1/auth',
        [
            "apiKey" => 18,
            "apiSecret" => "83E5+NmVPpYLJ?Fcm7hP",
            "lifetimeMinutes" => 1440
        ]
    );
    $auth = json_decode($auth_resp, true);
    $token = $auth['token'] ?? null;
    if(!$token) return null;

    $payload = [
        "accountIBAN" => "MD88QW000000000580074739", // IBAN QIWI test/demo
        "amount" => $amount,
        "comment" => "Plata cash la terminal",
        "validSeconds" => 3600, // valabil 1 oră
        "merchantID" => "CASH_" . date('YmdHisv'), // unic
        "reference" => "CASH_" . $chat_id,
        "redirectURL" => "https://qiwi.md/"
    ];
    $bill_resp = post_json_api(
        'https://api.qiwi.md/cash-in/create', // << endpoint-ul corect poate fi diferit
        $payload,
        [
            'Authorization: Bearer ' . $token
        ]
    );
    $bill = json_decode($bill_resp, true);
    return [
        'token' => $token,
        'code'  => $bill['reference'] ?? null,
        'merchantID' => $payload['merchantID']
    ];
}



// function post_qiwi_api($url, $params = [], $token = null) {
//     $ch = curl_init($url);
//     $headers = [
//         'Content-Type: application/json',
//         'Accept: application/json'
//     ];
//     if ($token) {
//         $headers[] = 'Authorization: Bearer ' . $token;
//     }
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // true pe prod!

//     $resp = curl_exec($ch);

//     if ($resp === false) {
//         file_put_contents(DEBUG_LOG, "[QIWI CURL ERROR] ".curl_error($ch)."\n", FILE_APPEND);
//     }
//     curl_close($ch);
//     return $resp;
// }

function post_json_api($url, $params = [], $headers = []) {
    $ch = curl_init($url);
    $default_headers = ['Content-Type: application/json'];
    $headers = array_merge($default_headers, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}

function answerCallback($callbackId) {
    @file_get_contents(API_URL . 'answerCallbackQuery?callback_query_id=' . urlencode($callbackId));
}
// Trimite mesaj și returnează info cu message_id
function sendMessageReturn($chat_id, $text, $keyboard = null) {
    $payload = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init(API_URL . 'sendMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " SEND: $json\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " RESP: $res\n", FILE_APPEND);
    return $data['result'] ?? [];
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $payload = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(API_URL . 'editMessageText');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function makePlacesKeyboard($n) {
    return [
        [
            ['text'=>'➖','callback_data'=>'minus_places'],
            ['text'=>"$n",'callback_data'=>'nr_places_'.$n],
            ['text'=>'➕','callback_data'=>'plus_places']
        ],
        [
            ['text'=>'❌ Anulează','callback_data'=>'cancel'],
            ['text'=>'🛒 Cumpără','callback_data'=>'final_conf']
        ]
    ];
}
function formatDetalii($detalii, $n, $locuri_libere = null) {
    return "🚌 <b>Ruta:</b> {$detalii['plecare']}\n".
           "🔼 <b>Plecare:</b> {$detalii['ora']}, {$detalii['data']}\n".
           "🔽 <b>Sosire:</b> {$detalii['ruta']}\n".
           "ℹ️ <b>Poți transporta animale de până la 10 kg (cu cușcă și acte complete). Pentru animale se achită loc separat la preț întreg (indică DOG/CAT).</b>\n".
           "🎦 <b>Servicii:</b> TV, băuturi, 1 bagaj gratuit, bilet online, Wi-Fi, mâncare, muzică, aer condiționat, priză, transport animale.\n".
           "💳 <b>Preț:</b> {$detalii['tarif']} MDL per loc\n".
           "💺 <b>Locuri libere:</b> ".($locuri_libere !== null ? $locuri_libere : '—')."\n\n".
           "👇 Este valabilă repartizarea liberă a locurilor. Selectează câte locuri vrei cu butoanele + și -, apoi apasă „Cumpără”.\n".
           "✔️ <b>Locuri dorite:</b> $n";
}


function editMessageReplyMarkup($chat_id, $message_id, $kb) {
    $payload = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE)
    ];
    $ch = curl_init(API_URL . 'editMessageReplyMarkup');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " EDIT_REPLYMARKUP: ".print_r($payload,1)." RESP: $res\n", FILE_APPEND);
    return $res;
}
function sendMessage($chat_id, $text, $inline_keyboard = null, $mid = null, $keyboard = null) {
    $payload = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($inline_keyboard !== null) $payload['reply_markup'] = ['inline_keyboard' => $inline_keyboard];
    if ($keyboard !== null) {
        if (isset($keyboard[0]['remove_keyboard']) && $keyboard[0]['remove_keyboard'] === true) {
            $payload['reply_markup'] = ['remove_keyboard' => true];
        } else {
            $payload['reply_markup'] = [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ];
        }
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($mid) {
        $payload['message_id'] = $mid;
        $ch = curl_init(API_URL . 'editMessageText');
    } else {
        $ch = curl_init(API_URL . 'sendMessage');
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " SEND: $json\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " RESP: $res\n", FILE_APPEND);
}


// --- STATE ---
function getStatePath($chat_id) { return __DIR__ . "/state/{$chat_id}.json"; }
function loadState($chat_id) {
    $file = getStatePath($chat_id);
    return file_exists($file)
         ? json_decode(file_get_contents($file), true)
         : ['step' => 'lang'];
}

// Functia de ștergere mesaj
function deleteMessage($chat_id, $message_id) {
    $payload = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    $ch = curl_init(API_URL . 'deleteMessage');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
function saveState($chat_id, $state) {
    file_put_contents(getStatePath($chat_id), json_encode($state));
}
function normalize($txt) {
    // Diacritice, spații, semne, lowercase
    $txt = removeDiacritics($txt);
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = preg_replace('/[\s\-\>\<\.\/\(\)]/', '', $txt); // elimină spații, săgeți, paranteze, slash etc
    return $txt;
}

function removeDiacritics($string) {
    $diacritics = [
        'ă'=>'a', 'â'=>'a', 'î'=>'i', 'ș'=>'s', 'ş'=>'s', 'ț'=>'t', 'ţ'=>'t',
        'Ă'=>'A', 'Â'=>'A', 'Î'=>'I', 'Ș'=>'S', 'Ş'=>'S', 'Ț'=>'T', 'Ţ'=>'T'
    ];
    return strtr($string, $diacritics);
}

function showSelectPlaces($chat_id, &$state, $detalii) {
    $n = $state['places'] ?? 0;
    if ($n < 0) $n = 0;
    if ($n > 5) $n = 5;
    $state['places'] = $n;

    $msg = "🚌 <b>Ruta:</b> {$detalii['plecare']}\n".
           "⏰ <b>Plecare:</b> {$detalii['ora']}, {$detalii['data']}\n".
           "🗺️ <b>Ruta:</b> {$detalii['ruta']}\n".
           "📏 <b>Distanță:</b> {$detalii['dist']} km\n".
           "💰 <b>Preț:</b> {$detalii['tarif']} pe loc\n".
           "🟩 <b>Locuri dorite:</b> $n\n\n".
           "Alege câte locuri vrei să rezervi:";

    $kb = [
        [
            ['text'=>'➖','callback_data'=>'minus_places'],
            ['text'=>"$n",'callback_data'=>'nr_places_'.$n],
            ['text'=>'➕','callback_data'=>'plus_places']
        ],
        [
            ['text'=>'❌ Anulează','callback_data'=>'cancel'],
            ['text'=>'🛒 Cumpără','callback_data'=>'final_conf']
        ]
    ];
    sendMessage($chat_id, $msg, $kb);
}

function clearState($chat_id) {
    @unlink(getStatePath($chat_id));
}
$messages = [
  'select_lang'=>['ro'=>'Selectează limba:','en'=>'Choose language:','ru'=>'Выберите язык:'],
  'mode'=>['ro'=>'Căutăm bilete pentru autobuz?','en'=>'Looking for tickets?','ru'=>'Ищем билеты?'],
  'ask_city'=>['ro'=>'Introdu numele orașului de <b>plecare</b> (ex: Balti)','en'=>'Enter departure city:','ru'=>'Введите пункт отправления:'],
  'ask_station'=>['ro'=>'Introdu numele orașului de <b>destinație</b> (ex: Balti)','en'=>'Enter destination:','ru'=>'Введите пункт назначения:'],
  'ask_date'=>['ro'=>'Introdu data dorită în formatul <b>zi.lună</b> (ex: 28.06)','en'=>'Enter date (ex: 28.06):','ru'=>'Введите дату (пример: 28.06):'],
  'ask_places'=>['ro'=>'Câte locuri vrei să rezervi? (ex: 2)','en'=>'How many seats? (ex: 2)','ru'=>'Сколько мест хотите? (пример: 2)'],
  'error'=>['ro'=>'Eroare.','en'=>'Error.','ru'=>'Ошибка.'],
  'cancelled'=>['ro'=>'Operațiune anulată.','en'=>'Cancelled.','ru'=>'Отменено.'],
  'invalid_date'=>['ro'=>'Dată invalidă sau indisponibilă!','en'=>'Invalid/unavailable date!','ru'=>'Неверная или недоступная дата!']
];

// --- BASIC HANDLING ---
if(isset($update['message'])){
    $chat_id=$update['message']['chat']['id'];
    $text=trim($update['message']['text']??'');
    $mid=$update['message']['message_id'];
    $data=null; $cbid=null;
}elseif(isset($update['callback_query'])){
    $chat_id=$update['callback_query']['message']['chat']['id'];
    $mid=$update['callback_query']['message']['message_id'];
    $data=$update['callback_query']['data'];
    $cbid=$update['callback_query']['id'];
    answerCallback($cbid);
}else{http_response_code(200);exit;}

$state=loadState($chat_id);
$lang='ro';

// === FLOW ===
if($text==='/start'){
    clearState($chat_id);
    
    $state = ['step' => 'from_city_text', 'lang' => 'ro'];
    saveState($chat_id, $state);
    sendMessage($chat_id, "Salut din nou! 🙂\nApasă /search pentru a începe căutarea biletelor.");
    exit;
}



// if (isset($text) && preg_match('/^\/cump_(\d+)/', $text, $mm)) {
//     $idx = (int)$mm[1];
//     $detalii = $state['curse_data'][$idx] ?? null;
//     if (!$detalii) {
//         sendMessage($chat_id, "Eroare: această cursă nu mai este valabilă sau sesiunea a expirat.");
//         exit;
//     }
//     $state['selectat_cursa'] = $idx;
//     $state['places'] = 1;
//     $state['step']='choose_places';
//     saveState($chat_id, $state);
//     $rez = sendMessageReturn($chat_id, formatDetalii($detalii, 1), makePlacesKeyboard(1));
//     $state['places_msgid'] = $rez['message_id'];
//     saveState($chat_id, $state);
//     exit;
// }

if (isset($text) && preg_match('/^\/(start )?cump_(\d+)/', $text, $mm)) {
    $idx = (int)$mm[2];
    $detalii = $state['curse_data'][$idx] ?? null;
    if (!$detalii) {
        sendMessage($chat_id, "Eroare: această cursă nu mai este valabilă sau sesiunea a expirat. Te rugăm să reiei căutarea.");
        exit;
    }

    // 1. Ia numarul real de locuri libere
    // Dacă deja ai în $detalii, folosește-l direct:
    $locuri_libere = $detalii['locuri_libere'] ?? null;

    // Dacă nu îl ai, trebuie să-l iei din API:
    // $locuri_libere = getLocuriLibereDinApi($detalii['code']);

    // 2. Setează restul stării
    $state['selectat_cursa'] = $idx;
    $state['places'] = 1;
    $state['step'] = 'choose_places';
    saveState($chat_id, $state);

    // 3. Trimite mesaj cu detalii și locuri reale
    $rez = sendMessageReturn($chat_id, formatDetalii($detalii, 1, $locuri_libere), makePlacesKeyboard(1));
    $state['places_msgid'] = $rez['message_id'];
    saveState($chat_id, $state);
    exit;
}




if ($text === '/search') {
    clearState($chat_id);

    $msg = "Căutăm bilete la tren sau autobuz?\nAlege, te rog, ce te interesează 👇";

    $kb = [
        [
            ['text' => "🚆 Tren",   'callback_data' => 'type_tren'],
            ['text' => "🚌 Autobuz", 'callback_data' => 'type_bus']
        ]
    ];

    $state = ['step' => 'select_type', 'lang' => 'ro'];
    saveState($chat_id, $state);

    sendMessage($chat_id, $msg, $kb);
    exit;
}



if ($data && $state['step'] === 'select_type') {
    if ($data === 'type_tren') {
        $state['step'] = 'from_city_text_tren';
        saveState($chat_id, $state);
        sendMessage($chat_id, "📝 Scrie stația de plecare a trenului!\nExemplu: Chișinău sau București");
        exit;
    }
    if ($data === 'type_bus') {
        $state['step'] = 'from_city_text_bus';
        saveState($chat_id, $state);

        // Exemplu cu ultima stație (poți înlocui cu istoric real)
        $ultima_statie = "Chișinău";
        $msg = "📝 Scrie stația de plecare a autobuzului.\nExemplu: Chișinău sau Bălți\n\nSau alege stația din căutările anterioare";
        $kb = [
            [
                ['text' => "🇲🇩 $ultima_statie", 'callback_data' => 'last_from_' . $ultima_statie]
            ]
        ];
        sendMessage($chat_id, $msg, $kb);
        exit;
    }

    if ($data === 'main_menu') {
        clearState($chat_id);
        $msg = "Căutăm bilete la tren sau autobuz?\nAlege, te rog, ce te interesează 👇";
        $kb = [
            [
                ['text' => "🚆 Tren",   'callback_data' => 'type_tren'],
                ['text' => "🚌 Autobuz", 'callback_data' => 'type_bus']
            ]
            // ,
            // [
            //     ['text' => "🧑‍💼 Ai nevoie de ajutor?", 'callback_data' => 'need_help']
            // ]
        ];
    
        $state = ['step' => 'select_type', 'lang' => 'ro'];
        saveState($chat_id, $state);
    
        sendMessage($chat_id, $msg, $kb);
        exit;
    }

    if ($data === 'need_help') {
        $user_id = $chat_id;
    
        $help_text = "Dacă ai dificultăți cu căutarea, cumpărarea sau returnarea biletelor, scrie la unul dintre contactele de mai jos 👇\n\n" .
            "<b>Telegram:</b> <a href=\"https://t.me/una_support\">@una_support</a>\n" .
            "<b>Email:</b> <a href=\"mailto:mail@gara.com\">mail@gara.com</a>\n" .
            "<b>ID-ul tău pentru suport:</b> <code>$user_id</code>";
    
        // === Butonul care trimite userul la meniul principal ===
        $kb = [
            [
                ['text' => "🔍 Caută bilete", 'callback_data' => 'main_menu']
            ]
        ];
    
        sendMessage($chat_id, $help_text, $kb);
        exit;
    }
}


// Dacă userul alege din buton
// SELECTIE PLECARE AUTOBUZ — SCRIS SAU BUTON ISTORIC
if (($data && strpos($data, 'last_from_') === 0 && $state['step'] === 'from_city_text_bus') ||
    ($state['step'] === 'from_city_text_bus' && isset($text))) {

    // Ia textul real (din buton sau scris)
    $plecare = $data && strpos($data, 'last_from_') === 0
        ? substr($data, strlen('last_from_'))
        : $text;

    // Daca din buton — sterge mesajul
    if($data && strpos($data, 'last_from_') === 0 && isset($mid)){
        deleteMessage($chat_id, $mid);
    }

    // --- ACEEASI LOGICA CA IN 'from_city_text' CLASIC ---
    $chisinauGari = [
        ['COD'=>'318880','TITLE'=>'GARA SCULENI'],
        ['COD'=>'318440','TITLE'=>'GSA GARA de NORD'],
        ['COD'=>'319502','TITLE'=>'GARA FEROVIARA GSA'],
        ['COD'=>'317765','TITLE'=>'GARA IALOVENI'],
        ['COD'=>'317345','TITLE'=>'CHISINAU']
    ];
    $response = json_decode(file_get_contents('https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all'), true);
    $startPoints = $response['content'] ?? [];
    $cauta = mb_strtolower(removeDiacritics($plecare), 'UTF-8');
    $filtered = [];
    if ($cauta === 'chisinau') {
        foreach($chisinauGari as $sp) {
            $filtered[] = ['text'=>$sp['TITLE'],'callback_data'=>'fromid_'.$sp['COD']];
        }
    }
     else {
        foreach($startPoints as $sp){
            if (mb_strpos(mb_strtolower(removeDiacritics($sp['TITLE']), 'UTF-8'), $cauta)!==false) {
                $filtered[] = ['text'=>$sp['TITLE'],'callback_data'=>'fromid_'.$sp['COD']];
            }
        }
    }

    if (count($filtered) === 1) {
        $startId = (int)substr($filtered[0]['callback_data'], 7);
        $state['startPoint'] = $startId;
        $state['step'] = 'to_city_text_bus';
        saveState($chat_id, $state);
    
        // Destinatii favorite (sau istoricul userului)
        $destinatii_recente = [
            ['text' => "🇺🇦 Harkiv (Poltava)", 'callback_data' => 'last_to_Harkiv'],
            ['text' => "🇫🇷 Paris",            'callback_data' => 'last_to_Paris']
        ];
        $msg = "📝 Scrie stația de destinație a autobuzului.\nExemplu: Bălți sau Paris\n\nSau alege stația din căutările anterioare";
        $kb = [ $destinatii_recente ];
        sendMessage($chat_id, $msg, $kb);
        exit;
    }
    if(empty($filtered)){
        sendMessage($chat_id,"Nu am găsit niciun oraș de plecare cu '$plecare'. Încearcă altceva. /search");
        exit;
    }
    $kb=[];
    foreach($filtered as $f) $kb[]=[$f];
    $kb[]=[['text'=>'❌ Anulează','callback_data'=>'cancel']];
    sendMessage($chat_id,"Alege punctul de plecare:",$kb);
    $state['step']='from_city_pick_bus';
    $state['last_filter']=$plecare;
    saveState($chat_id,$state);
    exit;
}

// SELECTIE din lista (userul alege un buton din lista de mai sus)
if($data && $state['step']==='from_city_pick_bus' && strpos($data,'fromid_')===0){
    $startId=(int)substr($data,7);
    $state['startPoint']=$startId;
    $response = json_decode(file_get_contents('https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all'), true);
    $startPoints = $response['content'] ?? [];
    foreach($startPoints as $sp){
        if ($sp['COD'] == $startId) {
            $state['last_filter'] = $sp['TITLE'];
            break;
        }
    }
    $state['step']='to_city_text_bus';
    saveState($chat_id,$state);

    // Șterge mesajul cu tastatura de opțiuni (adică "Alege punctul de plecare:")
    if(isset($mid)) {
        deleteMessage($chat_id, $mid);
    }

    // Extrage destinațiile din API pentru startPoint
    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $startId], true,
        'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];

    // Caută automat Bălți și Comrat în lista de destinații
    $popular = [];
    foreach($dest as $d){
        $title = mb_strtoupper(removeDiacritics($d['TITLE']), 'UTF-8');
        if (strpos($title, 'BALTI') !== false || strpos($title, 'COMRAT') !== false) {
            $popular[] = ['text'=>$d['TITLE'],'callback_data'=>'last_to_'.$d['TITLE']];
        }
    }
    // Sau dacă nu există Bălți și Comrat, ia primele 2 din lista API
    if(empty($popular) && !empty($dest)) {
        foreach(array_slice($dest, 0, 2) as $d){
            $popular[] = ['text'=>$d['TITLE'],'callback_data'=>'last_to_'.$d['TITLE']];
        }
    }
    // Nu trimite tastatura dacă nu ai niciun oraș
    $kb = [];
    if(!empty($popular)){
        $kb[] = $popular;
    }

    $msg = "📝 Scrie stația de destinație a autobuzului.\nExemplu: Bălți sau Comrat\n\nSau alege stația din căutările anterioare";
    sendMessage($chat_id, $msg, $kb);
    exit;
}




if ($data && strpos($data, 'last_to_') === 0 && $state['step'] === 'to_city_text_bus') {
    $destinatie_aleasa = substr($data, strlen('last_to_'));
    $state['last_filter_dest'] = $destinatie_aleasa; 
    if(isset($mid)) {
        deleteMessage($chat_id, $mid);
    }


    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $state['startPoint']], true,
         'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];


    $dest_id = null;
    foreach($dest as $d){
        if (mb_strtoupper(removeDiacritics($d['TITLE']), 'UTF-8') == mb_strtoupper(removeDiacritics($destinatie_aleasa), 'UTF-8')) {
            $dest_id = $d['COD'];
            break;
        }
    }
    if (!$dest_id) {
        sendMessage($chat_id, "Eroare: nu am găsit destinația selectată!");
        clearState($chat_id);
        exit;
    }
    $state['destPoint'] = $dest_id;
    $dates = [];
    $today = strtotime('today');
    for($i=0;$i<14;$i++){
        $d = strtotime("+$i day", $today);
        $dateTxt = date('d.m', $d);
        $zi = [
            "Duminica", "Luni", "Marti", "Miercuri",
            "Joi", "Vineri", "Sambata"
        ][date('w', $d)];
        $label = $dateTxt.', '.$zi;
        $dates[] = [
            'text'=>$label,
            'callback_data'=>"date_".$dateTxt
        ];
    }
    $kb = [];
    for($i=0;$i<count($dates);$i+=2){
        $row = [$dates[$i]];
        if(isset($dates[$i+1])) $row[] = $dates[$i+1];
        $kb[] = $row;
    }

    

    if (empty($state['startPoint']) || empty($state['destPoint'])) {
        sendMessage($chat_id, "Te rog selectează întâi orașul de plecare și destinația.");
        clearState($chat_id);
        exit;
    }

    $state['step'] = 'awaiting_date';
    $state['destPointName'] = $destinatie_aleasa; // Poți salva dacă ai nevoie
    saveState($chat_id, $state);

    $prompt = "🗓️ Introdu data dorită în formatul <b>zi.lună</b> (ex: 28.06)\nSau alege din lista de mai jos 👇";
    sendMessage($chat_id, $prompt, null, null, $kb); // Fără argumentul $keyboard, doar $inline_keyboard!

    exit;
}






// if($data && $state['step']==='wait_lang' && strpos($data,'lang_')===0){
//     $state['lang']=substr($data,5);
//     $state['step']='from_city_text';
//     saveState($chat_id,$state);
//     sendMessage($chat_id,$messages['ask_city'][$state['lang']],null);
//     exit;
// }

// --- PLECARE: SEARCH ---
if(isset($text) && $state['step']==='from_city_text'){
    $chisinauGari = [
        ['COD'=>'318880','TITLE'=>'GARA SCULENI'],
        ['COD'=>'318440','TITLE'=>'GSA GARA de NORD'],
        ['COD'=>'319502','TITLE'=>'GARA FEROVIARA GSA'],
        ['COD'=>'317765','TITLE'=>'GARA IALOVENI'],
        ['COD'=>'317345','TITLE'=>'CHISINAU']
    ];
    $response = json_decode(file_get_contents('https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all'), true);
    $startPoints = $response['content'] ?? [];
    $cauta = mb_strtolower(removeDiacritics($text), 'UTF-8');
    $filtered = [];
    if ($cauta === 'chisinau') {
        foreach($chisinauGari as $sp) {
            $filtered[] = ['text'=>$sp['TITLE'],'callback_data'=>'fromid_'.$sp['COD']];
        }
    } else {
        foreach($startPoints as $sp){
            if (mb_strpos(mb_strtolower(removeDiacritics($sp['TITLE']), 'UTF-8'), $cauta)!==false) {
                $filtered[] = ['text'=>$sp['TITLE'],'callback_data'=>'fromid_'.$sp['COD']];
            }
        }
    }
    if(empty($filtered)){
        sendMessage($chat_id,"Nu am găsit niciun oraș de plecare cu '$text'. Încearcă altceva.");
        exit;
    }
    $kb=[];
    foreach($filtered as $f) $kb[]=[$f];
    $kb[]=[['text'=>'❌ Anulează','callback_data'=>'cancel']];
    sendMessage($chat_id,"Alege punctul de plecare:",$kb);
    $state['step']='from_city_pick';
    $state['last_filter']=$text;
    saveState($chat_id,$state);
    exit;
}


// --- DESTINATIE: SEARCH ---
if(isset($text) && $state['step']==='to_city_text'){
    $chisinauGari = [
        ['COD'=>'318880','TITLE'=>'GARA SCULENI'],
        ['COD'=>'318440','TITLE'=>'GSA GARA de NORD'],
        ['COD'=>'319502','TITLE'=>'GARA FEROVIARA GSA'],
        ['COD'=>'317765','TITLE'=>'GARA IALOVENI'],
        ['COD'=>'317345','TITLE'=>'CHISINAU']
    ];
    $state['last_filter_dest']=$text; // <-- fix

    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $state['startPoint']], true,
         'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];
    $cauta = mb_strtolower($text, 'UTF-8');
    $filtered = [];
    if ($cauta === 'chisinau') {
        foreach($chisinauGari as $d){
            $filtered[] = ['text'=>$d['TITLE'],'callback_data'=>'toid_'.$d['COD']];
        }
    } else {
        foreach($dest as $d){
            if(mb_strpos(mb_strtolower($d['TITLE'],'UTF-8'), $cauta)!==false){
                $filtered[] = ['text'=>$d['TITLE'],'callback_data'=>'toid_'.$d['COD']];
            }
        }
    }
    if(empty($filtered)){
        sendMessage($chat_id,"Nu am găsit nicio destinație cu '$text'. Încearcă altceva.");
        exit;
    }
    $kb=[]; foreach($filtered as $f) $kb[]=[$f];
    $kb[]=[['text'=>'❌ Anulează','callback_data'=>'cancel']];
    sendMessage($chat_id,"Alege destinația:",$kb);
    $state['step']='to_city_pick';
    $state['last_filter_dest']=$text;
    saveState($chat_id,$state);
    exit;
}
if($data && $state['step']==='to_city_pick_bus' && strpos($data,'toid_')===0){
    $destId=(int)substr($data,5);
    $state['destPoint']=$destId;
    // $state['last_filter_dest']=$dest_name;
    $state['step']='awaiting_date';
    saveState($chat_id,$state);

    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $state['startPoint']], true,
         'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];
    $dest_name = '';
    foreach($dest as $d){
        if($d['COD'] == $destId) { $dest_name = $d['TITLE']; break; }
    }
    $state['destPoint']=$destId;
    $state['last_filter_dest']=$dest_name;  // <-- fix
    $state['step']='awaiting_date';
    saveState($chat_id,$state);

    // 1. Șterge mesajul cu butoane destinație
    if(isset($mid)) {
        deleteMessage($chat_id, $mid);
    }

    // 2. Trimite mesaj nou cu promptul + tastatura datelor
    $dates = [];
    $today = strtotime('today');
    for($i=0;$i<14;$i++){
        $d = strtotime("+$i day", $today);
        $dateTxt = date('d.m', $d);
        $zi = [
            "Duminica", "Luni", "Marti", "Miercuri",
            "Joi", "Vineri", "Sambata"
        ][date('w', $d)];
        $label = $dateTxt.', '.$zi;
        $dates[] = [
            'text'=>$label,
            'callback_data'=>"date_".$dateTxt
        ];
    }
    $kb = [];
    for($i=0;$i<count($dates);$i+=2){
        $row = [$dates[$i]];
        if(isset($dates[$i+1])) $row[] = $dates[$i+1];
        $kb[] = $row;
    }

    $prompt = "📅 Introdu data dorită în formatul zi.lună (ex: 28.06)\nSau alege din lista de mai jos 👇";
    sendMessage($chat_id, "Alegeți ora pentru cursa selectată <b>$numeCursa</b>:", [$curse_ora]);
    $state['curse_data'] = $curse_data;
    $state['step'] = 'choose_cursa';
    saveState($chat_id, $state);
    exit;
}



// --- SELECTARE DATA ---
if(isset($text) && $state['step']==='awaiting_date'){
    if (empty($state['startPoint']) || empty($state['destPoint'])) {
        sendMessage($chat_id, "Eroare: trebuie să selectezi întâi orașul de plecare și destinația!");
        clearState($chat_id);
        exit;
    }
    $date = null;
    $day = null;
    $month = null;
    // Acceptă "dd.mm"
    if (preg_match('/^(\d{1,2})\.(\d{2})$/', $text, $m)) {
        $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $date = "$day.$month";
    }
    // Acceptă "dd.mm, Sambata"
    elseif (preg_match('/^(\d{1,2})\.(\d{2}),\s*([a-zA-ZăâîșșțŢÎÂĂŞ]+)$/u', $text, $m)) {
        $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $date = "$day.$month";
    }
    if($date && $day && $month){
        $year  = date('Y');
        $selected_date = "$year-{$month}-{$day}";
        $params = [
            'startPoint'   => $state['startPoint'],
            'station'      => $state['destPoint'],
            'data'         => $selected_date,
            'connect_type' => 'web',
            'org'          => 'all',
            'ro'           => '',
            'api_type'     => ''
        ];
        $response = post_api("https://gam2022.unisim-soft.com/widget_una/include/do_search.php?", $params, true,
        'Unisimso',      
        's0ft2025Web'    );
        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " DO_SEARCH RESPONSE: $response\n", FILE_APPEND);
        file_put_contents(DEBUG_LOG, "[DO_SEARCH PARAMS]: ".print_r($params,1)."\n", FILE_APPEND);

        // extrage curse doar pt ruta selectata (ambele orașe, în orice ordine, inclusiv "BALTI ..." sau "CHISINAU ...")
        // preg_match_all(
        //     '/<tr>\s*<td[^>]*>.*?<\/td>\s*<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/',
        //     $response, $matches, PREG_SET_ORDER
        // );
        preg_match_all(
            '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*' .  // extrage route și data
            '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*' .                        // plecare/ruta
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // data
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // ora
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // ruta
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // distanță
            '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/',
            $response, $matches, PREG_SET_ORDER
        );

        $plecare = mb_strtoupper($state['last_filter'] ?? '');
        $destinatie = mb_strtoupper($state['last_filter_dest'] ?? '');
        $state['last_filter_dest'] = $destinatie; 
        $curse_data = [];
        $curse_ora = [];
        $plecare_norm = normalize($state['last_filter'] ?? '');
        $destinatie_norm = '';
        if (!empty($state['last_filter_dest'])) {
            $destinatie_norm = normalize(explode(' ', $state['last_filter_dest'])[0]);
        }file_put_contents(DEBUG_LOG, "[DEBUG] plecare_norm: $plecare_norm | destinatie_norm: $destinatie_norm\n", FILE_APPEND);


// $curse_data = [];
// $curse_ora = [];


// foreach($matches as $idx=>$m){
//     $dir_norm = normalize($m[3]);
//     file_put_contents(DEBUG_LOG, "[DEBUG] dir_norm: $dir_norm\n", FILE_APPEND);


//     if($destinatie_norm){
//       if(strpos($dir_norm, $destinatie_norm) === false) continue;
//     }

//     $detalii = [
//         'code'    => $m[1],      // pentru getPlaces.php
//         'data'    => $m[2],
//         'plecare' => $m[3],      // textul rutei, ex: CHISINAU->BALTI
//         'data_td' => $m[4],
//         'ora'     => $m[5],
//         'ruta'    => $m[6],
//         'dist'    => $m[7],
//         'tarif'   => $m[8]
//     ];
//     $curse_data[$idx] = $detalii;
//     $curse_ora[] = ['text' => "{$m[5]}", 'callback_data'=>'cursa_'.$idx];
// }
// 1. Trimite mesajul de status „mă uit după curse...”
$text_wait = "💪 Încep căutarea\nMă uit după curse, te rog așteaptă...";
$wait_resp = post_api(API_URL . 'sendMessage', [
    'chat_id' => $chat_id,
    'text' => $text_wait,
    'parse_mode' => 'HTML'
]);
$wait_data = json_decode($wait_resp, true);
$status_id = $wait_data['result']['message_id'] ?? null;


// 2. Formezi lista de curse ca RailwayBot
$curse_data = [];
$text_msg = "";
$curse_count = 0;
$bot_username = "BIL_GARA_MDbot"; // fără @

// Începi foreach
foreach($matches as $idx => $m){
    $dir_norm = normalize($m[3]);
    if ($destinatie_norm) {
        if (strpos($dir_norm, $destinatie_norm) === false) continue;
    }

    // 1. Obține numărul de locuri libere pentru fiecare cursă
    $locuri_libere = '?';
    $station = $state['destPoint'] ?? null;
    if ($station) {
        $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$m[1]}&data={$m[2]}&station={$station}";
        $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
        $json = json_decode($resp, true);
        if (isset($json['content']['place'])) {
            $locuri_libere = 0;
            foreach ($json['content']['place'] as $p) {
                if ($p['occupied'] == "0") $locuri_libere++;
            }
        }
    }

    // 2. Construiește array-ul de detalii, inclusiv locurile libere
    $detalii = [
        'code'    => $m[1],
        'data'    => $m[2],
        'plecare' => $m[3],
        'data_td' => $m[4],
        'ora'     => $m[5],
        'ruta'    => $m[6],
        'dist'    => $m[7],
        'tarif'   => $m[8],
        'locuri_libere' => $locuri_libere   // <-- Foarte important!
    ];
    $curse_data[$idx] = $detalii;
    $curse_count++;

    // 3. Creează comanda unică pentru fiecare cursă
    $cmd = "cump_$idx";

    // 4. Afișează detaliile pentru fiecare cursă (inclusiv /cump_xx)
    $text_msg .= "🚌 <b>{$detalii['plecare']}</b>\n";
    $text_msg .= "⏰ <b>Plecare:</b> {$detalii['ora']}, {$detalii['data_td']}\n";
    $text_msg .= "🗺️ <b>Destinație:</b> {$detalii['ruta']}\n";
    $text_msg .= "💺 <b>Locuri libere:</b> $locuri_libere\n";
    $text_msg .= "💵 <b>Preț:</b> {$detalii['tarif']} MDL\n";
    $text_msg .= "🔗 <b>Cumpără bilet:</b> /$cmd\n";
    $text_msg .= "────────────────────────\n";

    // Optional: sleep(0.2); // Dacă vrei să eviți flood la API
}


// 3. Ștergi mesajul de status ca să nu încarci chatul (opțional, doar dacă ai status_id)
if($status_id){
    post_api(API_URL . 'deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $status_id
    ]);
}

if ($curse_count === 0) {
    sendMessage($chat_id, "Nu sunt curse pentru această dată/rută.");
    exit;
}

$numeCursa = $curse_data[array_key_first($curse_data)]['plecare'];
sendMessage($chat_id, "<b>Curse disponibile pentru $numeCursa:</b>\n\n$text_msg");

// Salvezi în state toate detaliile inclusiv locurile libere:
$state['curse_data'] = $curse_data;
$state['step'] = 'choose_cursa_list';
saveState($chat_id, $state);

exit;


// $text_wait = "💪 Încep căutarea\nMă uit după curse, te rog așteaptă...";
// post_api(API_URL . 'sendMessage', [
//     'chat_id' => $chat_id,
//     'text' => $text_wait,
//     'parse_mode' => 'HTML'
// ]);


// if (empty($curse_gasite)) {
//     // (opțional) șterge mesajul de status ca să nu încarci chatul
//     post_api(API_URL . 'deleteMessage', [
//         'chat_id' => $chat_id,
//         'message_id' => $status_id
//     ]);

//     // Trimite mesajul final cu butoane inline
//     post_api(API_URL . 'sendMessage', [
//         'chat_id' => $chat_id,
//         'text' =>
//             "🟩 Bilete nu au fost găsite!\n".
//             "Poate toate biletele au fost cumpărate sau nu există curse pentru această dată.",
//         'parse_mode' => 'HTML',
//         'reply_markup' => json_encode([
//             'inline_keyboard' => [
//                 [
//                     ['text' => '📅 Altă zi', 'callback_data' => 'other_day'],
//                     ['text' => '🚍 Caută autobuz', 'callback_data' => 'find_bus']
//                 ],
//                 [
//                     ['text' => '🔄 Repetă căutarea', 'callback_data' => 'repeat_search'],
//                     ['text' => '🔁 Bilet retur', 'callback_data' => 'return_ticket']
//                 ],
//                 [
//                     ['text' => '🆕 Căutare nouă', 'callback_data' => 'new_search']
//                 ]
//             ]
//         ])
//     ]);
// }

// Ia numele cursei pentru afișare (prima găsită după filtru)
// $numeCursa = $curse_data ? $curse_data[array_key_first($curse_data)]['plecare'] : '';

// if (empty($curse_data)) {
//     sendMessage($chat_id, "Nu sunt curse disponibile pentru această rută.");
//     clearState($chat_id);
//     exit;
// }

// $state['curse_data'] = $curse_data;
// $state['step'] = 'choose_cursa';
// saveState($chat_id, $state);
// sendMessage($chat_id, "Alegeți ora pentru cursa selectată <b>$numeCursa</b>:", [$curse_ora]);
// exit;


    } else {
        sendMessage($chat_id, "Format invalid! Te rog să introduci data în formatul <b>zi.lună</b> (ex: 28.06)", null);
        exit;
    }
    
}



// SELECTARE DATA DIN TASTATURA (callback_data = date_...)
if ($data && strpos($data, 'date_') === 0 && $state['step'] === 'awaiting_date') {
    $date = substr($data, 5); // Ex: "01.07"
    $day = substr($date, 0, 2);
    $month = substr($date, 3, 2);
    $year = date('Y');
    $selected_date = "$year-{$month}-{$day}";

    if (empty($state['startPoint']) || empty($state['destPoint'])) {
        sendMessage($chat_id, "Eroare: trebuie să selectezi întâi orașul de plecare și destinația!");
        clearState($chat_id);
        exit;
    }

    $params = [
        'startPoint' => $state['startPoint'],
        'station' => $state['destPoint'],
        'data' => $selected_date,
        'connect_type' => 'web',
        'org' => 'all',
        'ro' => '',
        'api_type' => ''
    ];
    $response = post_api("https://gam2022.unisim-soft.com/widget_una/include/do_search.php?", $params, true,
    'Unisimso',      
    's0ft2025Web');
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " DO_SEARCH RESPONSE: $response\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, "[DO_SEARCH PARAMS]: " . print_r($params, 1) . "\n", FILE_APPEND);

        // extrage curse doar pt ruta selectata (ambele orașe, în orice ordine, inclusiv "BALTI ..." sau "CHISINAU ...")
        // preg_match_all(
        //     '/<tr>\s*<td[^>]*>.*?<\/td>\s*<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/',
        //     $response, $matches, PREG_SET_ORDER
        // );

        preg_match_all(
            '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*' .  // extrage route și data
            '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*' .                        // plecare/ruta
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // data
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // ora
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // ruta
            '<td[^>]*>([^<]+)<\/td>\s*' .                                                           // distanță
            '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/',
            $response, $matches, PREG_SET_ORDER
        );

        $plecare = mb_strtoupper($state['last_filter'] ?? '');
        $destinatie = mb_strtoupper($state['last_filter_dest'] ?? '');
        $state['last_filter_dest'] = $destinatie; 
        $curse_data = [];
        $curse_ora = [];
        $plecare_norm = normalize($state['last_filter'] ?? '');
        $destinatie_norm = '';
        if (!empty($state['last_filter_dest'])) {
            $destinatie_norm = normalize(explode(' ', $state['last_filter_dest'])[0]);
        }file_put_contents(DEBUG_LOG, "[DEBUG] plecare_norm: $plecare_norm | destinatie_norm: $destinatie_norm\n", FILE_APPEND);


// $curse_data = [];
// $curse_ora = [];



// foreach($matches as $idx=>$m){
//     $dir_norm = normalize($m[3]);
//     file_put_contents(DEBUG_LOG, "[DEBUG] dir_norm: $dir_norm\n", FILE_APPEND);

//     if($destinatie_norm){
//         if($destinatie_norm) 
//         if(strpos($dir_norm, $destinatie_norm) === false) continue;
//     }

//     $detalii = [
//         'code'    => $m[1],    
//         'data'    => $m[2],
//         'plecare' => $m[3],      
//         'data_td' => $m[4],
//         'ora'     => $m[5],
//         'ruta'    => $m[6],
//         'dist'    => $m[7],
//         'tarif'   => $m[8]
//     ];
//     $curse_data[$idx] = $detalii;
//     $curse_ora[] = ['text' => "{$m[5]}", 'callback_data'=>'cursa_'.$idx];
// }

// $text_wait = "💪 Încep căutarea\nMă uit după curse, te rog așteaptă...";
// post_api(API_URL . 'sendMessage', [
//     'chat_id' => $chat_id,
//     'text' => $text_wait,
//     'parse_mode' => 'HTML'
// ]);

// 1. Trimite mesajul de status „mă uit după curse...”
$text_wait = "💪 Încep căutarea\nMă uit după curse, te rog așteaptă...";
$wait_resp = post_api(API_URL . 'sendMessage', [
    'chat_id' => $chat_id,
    'text' => $text_wait,
    'parse_mode' => 'HTML'
]);
$wait_data = json_decode($wait_resp, true);
$status_id = $wait_data['result']['message_id'] ?? null;

// 2. Formezi lista de curse ca RailwayBot
$curse_data = [];
$text_msg = "";
$curse_count = 0;
$bot_username = "BIL_GARA_MDbot"; // fără @

// Începi foreach
foreach($matches as $idx => $m){
    $dir_norm = normalize($m[3]);
    if ($destinatie_norm) {
        if (strpos($dir_norm, $destinatie_norm) === false) continue;
    }

    // 1. Obține numărul de locuri libere pentru fiecare cursă
    $locuri_libere = '?';
    $station = $state['destPoint'] ?? null;
    if ($station) {
        $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$m[1]}&data={$m[2]}&station={$station}";
        $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
        $json = json_decode($resp, true);
        if (isset($json['content']['place'])) {
            $locuri_libere = 0;
            foreach ($json['content']['place'] as $p) {
                if ($p['occupied'] == "0") $locuri_libere++;
            }
        }
    }

    // 2. Construiește array-ul de detalii, inclusiv locurile libere
    $detalii = [
        'code'    => $m[1],
        'data'    => $m[2],
        'plecare' => $m[3],
        'data_td' => $m[4],
        'ora'     => $m[5],
        'ruta'    => $m[6],
        'dist'    => $m[7],
        'tarif'   => $m[8],
        'locuri_libere' => $locuri_libere   // <-- Foarte important!
    ];
    $curse_data[$idx] = $detalii;
    $curse_count++;

    // 3. Creează comanda unică pentru fiecare cursă
    $cmd = "cump_$idx";

    // 4. Afișează detaliile pentru fiecare cursă (inclusiv /cump_xx)
    $text_msg .= "🚌 <b>{$detalii['plecare']}</b>\n";
    $text_msg .= "⏰ <b>Plecare:</b> {$detalii['ora']}, {$detalii['data_td']}\n";
    $text_msg .= "🗺️ <b>Destinație:</b> {$detalii['ruta']}\n";
    $text_msg .= "💺 <b>Locuri libere:</b> $locuri_libere\n";
    $text_msg .= "💵 <b>Preț:</b> {$detalii['tarif']} MDL\n";
    $text_msg .= "🔗 <b>Cumpără bilet:</b> /$cmd\n";
    $text_msg .= "────────────────────────\n";

    // Optional: sleep(0.2); // Dacă vrei să eviți flood la API
}


// 3. Ștergi mesajul de status ca să nu încarci chatul (opțional, doar dacă ai status_id)
if($status_id){
    post_api(API_URL . 'deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $status_id
    ]);
}

if ($curse_count === 0) {
    sendMessage($chat_id, "Nu sunt curse pentru această dată/rută.");
    exit;
}

$numeCursa = $curse_data[array_key_first($curse_data)]['plecare'];
sendMessage($chat_id, "<b>Curse disponibile pentru $numeCursa:</b>\n\n$text_msg");

// Salvezi în state toate detaliile inclusiv locurile libere:
$state['curse_data'] = $curse_data;
$state['step'] = 'choose_cursa_list';
saveState($chat_id, $state);

exit;



// if (empty($curse_gasite)) {
//     // (opțional) șterge mesajul de status ca să nu încarci chatul
//     post_api(API_URL . 'deleteMessage', [
//         'chat_id' => $chat_id,
//         'message_id' => $status_id
//     ]);

//     // Trimite mesajul final cu butoane inline
//     post_api(API_URL . 'sendMessage', [
//         'chat_id' => $chat_id,
//         'text' =>
//             "🟩 Bilete nu au fost găsite!\n".
//             "Poate toate biletele au fost cumpărate sau nu există curse pentru această dată.",
//         'parse_mode' => 'HTML',
//         'reply_markup' => json_encode([
//             'inline_keyboard' => [
//                 [
//                     ['text' => '📅 Altă zi', 'callback_data' => 'other_day'],
//                     ['text' => '🚍 Caută autobuz', 'callback_data' => 'find_bus']
//                 ],
//                 [
//                     ['text' => '🔄 Repetă căutarea', 'callback_data' => 'repeat_search'],
//                     ['text' => '🔁 Bilet retur', 'callback_data' => 'return_ticket']
//                 ],
//                 [
//                     ['text' => '🆕 Căutare nouă', 'callback_data' => 'new_search']
//                 ]
//             ]
//         ])
//     ]);
// }
// Ia numele cursei pentru afișare (prima găsită după filtru)
// $numeCursa = $curse_data ? $curse_data[array_key_first($curse_data)]['plecare'] : '';

// if (empty($curse_data)) {
//     sendMessage($chat_id, "Nu sunt curse disponibile pentru această rută.");
//     clearState($chat_id);
//     exit;
// }

// $state['curse_data'] = $curse_data;
// $state['step'] = 'choose_cursa';
// saveState($chat_id, $state);
// sendMessage($chat_id, "Alegeți ora pentru cursa selectată <b>$numeCursa</b>:", [$curse_ora]);


// exit;


}    


// --- SELECTIE CURSA => INTRODUCERE NR LOCURI ---
if($data && $state['step']=='choose_cursa' && strpos($data, 'cursa_')===0){
    $idx = (int)substr($data, 6);
    $detalii = $state['curse_data'][$idx] ?? null;
    if(!$detalii){
        sendMessage($chat_id, "Eroare la selectarea cursei.");
        clearState($chat_id);
        exit;
    }
    if (isset($mid)) deleteMessage($chat_id, $mid);

    $state['selectat_cursa'] = $idx;   // Salvezi indexul corect
    $state['places'] = 0;
    $state['step']='choose_places'; // step nou, nu ask_places

    $code_cursa = $detalii['code'] ?? $detalii['cod'] ?? null;
    $date_cursa = $detalii['data'];
    $station    = $state['destPoint'];
    $locuri_libere = null; // default
    $locuri_libere = null;
    if ($code_cursa && $date_cursa && $station) {
        $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$detalii['code']}&data={$detalii['data']}&station={$state['destPoint']}";
        $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
        if ($resp) {
            $json = json_decode($resp, true);
            if (isset($json['content']['place'])) {
                $locuri_libere = 0;
                foreach ($json['content']['place'] as $p) {
                    if ($p['occupied'] == "0") $locuri_libere++;
                }

                $state['locuri_libere'] = $locuri_libere;
                $places_info = [];
                foreach ($json['content']['place'] as $place) {
                    $places_info[] = "Nr: {$place['place_nr']} => " . ($place['occupied'] == "0" ? "liber" : "ocupat");
                }
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API DEBUG]\n".implode("\n", $places_info)."\n", FILE_APPEND);
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API LIBERE] $locuri_libere din ".count($json['content']['place'])." locuri\n", FILE_APPEND);
               
            }
        }
    }

    $rez = sendMessageReturn($chat_id, formatDetalii($detalii, 1, $locuri_libere), makePlacesKeyboard(1));
    $state['places_msgid'] = $rez['message_id'];
    saveState($chat_id, $state);
    exit;
}


if (($data === 'plus_places' || $data === 'minus_places') && $state['step'] === 'choose_places') {
    $n = $state['places'] ?? 0;
    if ($data === 'plus_places') $n = min(5, $n + 1);
    if ($data === 'minus_places') $n = max(1, $n - 1);
    $state['places'] = $n;
    saveState($chat_id, $state);

    $idx = $state['selectat_cursa'];
    $detalii = $state['curse_data'][$idx] ?? null;
    $locuri_libere = $state['locuri_libere'] ?? null;

    // Dacă vrei să updatezi live la fiecare click:
    // recomanzi să păstrezi numărul inițial pentru a nu abuza API-ul
    // Dar dacă vrei mereu proaspăt, decomentează liniile de mai jos:
    
    $code_cursa = $detalii['code'] ?? $detalii['cod'] ?? null;
    $date_cursa = $detalii['data'];
    $station    = $state['destPoint'];
    if ($code_cursa && $date_cursa && $station) {
        $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$detalii['code']}&data={$detalii['data']}&station={$state['destPoint']}";
        $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
        if ($resp) {
            $json = json_decode($resp, true);
            if (isset($json['content']['place'])) {
                $locuri_libere = 0;
                foreach ($json['content']['place'] as $p) {
                    if ($p['occupied'] == "0") $locuri_libere++;
                }

                $state['locuri_libere'] = $locuri_libere;

                $places_info = [];
                foreach ($json['content']['place'] as $place) {
                    $places_info[] = "Nr: {$place['place_nr']} => " . ($place['occupied'] == "0" ? "liber" : "ocupat");
                }
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API DEBUG]\n".implode("\n", $places_info)."\n", FILE_APPEND);
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API LIBERE] $locuri_libere din ".count($json['content']['place'])." locuri\n", FILE_APPEND);
            
            }
        }
    }
    editMessageText($chat_id, $state['places_msgid'], formatDetalii($detalii, $n, $locuri_libere), makePlacesKeyboard($n));
    exit;
}


// După apăsarea "Cumpără" - începe colectarea datelor pasagerilor
if($data === 'final_conf' && $state['step'] == 'choose_places'){
    $idx = $state['selectat_cursa'];
    $detalii = $state['curse_data'][$idx] ?? [];
    $nr_loc = $state['places'] ?? 1;

    $state['step'] = 'input_passenger_phone';
    $state['input_pass_idx'] = 0;
    $state['input_pass_data'] = [];
    $state['places'] = 1;

    saveState($chat_id, $state);

    sendMessage($chat_id, "✍️ Scrieți, vă rog, numărul de telefon pentru pasagerul de pe locul 1. Este necesar pentru informarea privind călătoria.");
    exit;
}


if($state['step'] == 'input_passenger_phone' && isset($text)){
    $idx = $state['input_pass_idx'];
    // Validează telefonul (poți ajusta regex)
    if(!preg_match('/^\+?\d{6,15}$/', trim($text))){
        sendMessage($chat_id, "⚠️ Număr de telefon invalid! Te rugăm să introduci un număr valid (ex: +37369123456).");
        exit;
    }
    $state['input_pass_data'][$idx]['phone'] = trim($text);
    $state['step'] = 'input_passenger_name';
    saveState($chat_id, $state);
    sendMessage($chat_id, "✍️ Scrieți numele și prenumele pentru locul ".($idx+1).".\nExemplu: Maria Popescu");
    exit;
}

if($state['step'] == 'input_passenger_name' && isset($text)){
    $idx = $state['input_pass_idx'];
    $state['input_pass_data'][$idx]['name'] = trim($text);

    // Acum cere email-ul
    $state['step'] = 'input_passenger_email';
    saveState($chat_id, $state);
    sendMessage($chat_id, "✉️ Scrieți emailul pentru locul ".($idx+1).".\nExemplu: exemplu@email.com");
    exit;
}



if ($state['step'] == 'input_passenger_email' && isset($text)) {
    $idx = $state['input_pass_idx'];

    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[STEP input_passenger_email] Email introdus pentru idx $idx: $text\n", FILE_APPEND);

    // Validare email
    if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[STEP input_passenger_email] Email INVALID: $text\n", FILE_APPEND);
        sendMessage($chat_id, "⚠️ Email invalid! Te rugăm să introduci un email valid (ex: exemplu@email.com).");
        exit;
    }
    $state['input_pass_data'][$idx]['email'] = trim($text);

    // (Dacă ai mai mulți pasageri, completează datele aici...)
    // ...

    // Treci la etapa de bronare în API
    $state['step'] = 'bronare_in_progress';
    saveState($chat_id, $state);

    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] START -- Pasageri: " . json_encode($state['input_pass_data']) . "\n", FILE_APPEND);

    // Mesaj intermediar
    $rez = sendMessageReturn($chat_id, "⏳ Bronăm biletele... Așteptați câteva secunde, vă rugăm.");
    $bronare_msgid = $rez['message_id'];
    $state['bronare_msgid'] = $bronare_msgid;
    saveState($chat_id, $state);

    // ----==== PREPARE DATA ====----
    // Presupunem că alegi mereu primul pasager (sau faci un loop dacă ai mai mulți, vezi după cerință)
    $pass = $state['input_pass_data'][0]; // presupunem primul pasager
    $name = trim($pass['name']);
    $parts = explode(' ', $name, 2);
    $first_name = $parts[0];
    $last_name = isset($parts[1]) ? $parts[1] : $parts[0];

    $params = [
        'biletcount' => 1,
        'startPoint' => $state['plecare_id'],
        'destination' => $state['destinatie_id'],
        'RouteCode' => $state['route_id'],
        'data' => $state['data_calatorie'],
        'first_name' => 'asf',
        'last_name' => 'asfasf',
        'phone' => $pass['phone'],
        'email' => $pass['email']
    ];
    // LOG: ce trimiți către API
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] TRIMIT LA API /reserve.php: " . http_build_query($payload) . "\n", FILE_APPEND);

    // ==== REQUEST LA API ====
    $url = 'https://gam2022.unisim-soft.com/widget_una/include/reserve.php';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "Unisimso:s0ft2025Web");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $api_response = curl_exec($ch);
    $api_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($api_response === false) {
        $curl_err = curl_error($ch);
        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] CURL ERROR: $curl_err\n", FILE_APPEND);
    }
    curl_close($ch);

    // LOG răspuns brut
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] RASPUNS HTTP $api_http_code: $api_response\n", FILE_APPEND);

    // Decodare răspuns
    $raspuns_api = json_decode($api_response, true);

    // LOG: răspuns decodat
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] RASPUNS DECODED: " . print_r($raspuns_api, true) . "\n", FILE_APPEND);

    // Verificare răspuns
    if (!empty($raspuns_api['content']['reservation_code'])) {
        $cod_rez = $raspuns_api['content']['reservation_code'];
        $locuri_rezervate = $raspuns_api['content']['places'] ?? null;

        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] SUCCES - Cod rezervare: $cod_rez, Locuri: " . json_encode($locuri_rezervate) . "\n", FILE_APPEND);

        $state['reservation_code'] = $cod_rez;
        $state['locuri_rezervate'] = $locuri_rezervate;
        saveState($chat_id, $state);

        deleteMessage($chat_id, $bronare_msgid);

        sendMessage($chat_id, "✅ Biletele au fost rezervate cu succes!\n\nAlege modalitatea de plată:", [
            [
                ['text'=>'💳 Plată online (MIA/QR)','callback_data'=>'pay_qiwi'],
                ['text'=>'💳 Plată cu card','callback_data'=>'pay_card'],
            ],
            [
                ['text'=>'🏦 Plată cash la casa','callback_data'=>'pay_cash_casa'],
                ['text'=>'💸 Plată cash (QIWI)','callback_data'=>'pay_cash'],
            ],
            [['text'=>'❌ Anulează','callback_data'=>'cancel']]
        ]);
        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] Final - butoane plată trimise\n", FILE_APPEND);
        exit;
    } else {
        // Eroare la rezervare
        deleteMessage($chat_id, $bronare_msgid);
        $err_msg = $raspuns_api['content']['message'] ?? 'Eroare la rezervare! Încearcă din nou.';
        sendMessage($chat_id, "❌ Rezervarea a eșuat!\n\n$err_msg");
        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[BRONARE] EROARE rezervare: $err_msg\n", FILE_APPEND);
        exit;
    }
}


// if($state['step'] == 'input_passenger_email' && isset($text)){
//     $idx = $state['input_pass_idx'];
//     if(!filter_var($text, FILTER_VALIDATE_EMAIL)){
//         sendMessage($chat_id, "⚠️ Email invalid! Te rugăm să introduci un email valid (ex: exemplu@email.com).");
//         exit;
//     }
//     $state['input_pass_data'][$idx]['email'] = trim($text);

//     // Dacă mai sunt pasageri, treci la următorul
//     // if($idx+1 < ($state['places'] ?? 1)){
//     //     $state['input_pass_idx']++;
//     //     $state['step'] = 'input_passenger_phone';
//     //     saveState($chat_id, $state);
//     //     sendMessage($chat_id, "✍️ Scrieți, vă rog, numărul de telefon pentru pasagerul de pe locul ".($idx+2).".");
//     //     exit;
//     // }
    
//     // Toți pasagerii au fost introduși
//     $state['step'] = 'bronare_in_progress';
//     saveState($chat_id, $state);

//     // Mesaj „bronăm biletele...”
//     $rez = sendMessageReturn($chat_id, "⏳ Bronăm biletele... Așteptați câteva secunde, vă rugăm.");
//     $bronare_msgid = $rez['message_id'];
//     $state['bronare_msgid'] = $bronare_msgid;
//     $state['reservation_code'] = $raspuns_api['reservation_code'];
//     saveState($chat_id, $state);

//     // --- Aici faci efectiv bronarea în API cu datele din $state['input_pass_data'] ---
//     // (Poți face request-uri sincron sau asincron, după implementare)
//     // SUGESTIE: poți folosi sleep(1); sau sleep(2); ca să simulezi delay dacă e demo

//     // Exemplu simplu (DOAR SIMULARE) -- înlocuiești cu logica reală de rezervare!
//     sleep(2); // simulează delay bronare

//     // --- după rezervare, șterge mesajul de „bronăm...” și afișează modalitățile de plată
//     deleteMessage($chat_id, $bronare_msgid);
//     sendMessage($chat_id, "✅ Biletele au fost rezervate cu succes!\n\nAlege modalitatea de plată:", [
//         [
//             ['text'=>'💳 Plată online (MIA/QR)','callback_data'=>'pay_qiwi'],
//             ['text'=>'💳 Plată cu card','callback_data'=>'pay_card'],
//         ],
//         [
//             ['text'=>'🏦 Plată cash la casa','callback_data'=>'pay_cash_casa'],
//             ['text'=>'💸 Plată cash (QIWI)','callback_data'=>'pay_cash'],

//         ],
//         [['text'=>'❌ Anulează','callback_data'=>'cancel']]
//     ]);
//     // Dacă ai nevoie să salvezi și confirmarea în state, fă saveState din nou
//     exit;
// }


if($data==='pay_qiwi'){
    set_time_limit(180); // dacă ai voie pe server
    ignore_user_abort(true);

    // Curăță orice stare veche de QR
    unset(
        $state['qr_msgid'],
        $state['qr_time'],
        $state['text_msgid1'],
        $state['text_msgid2'],
        $state['qr_merchant'],
        $state['qiwi_token']
    );
    saveState($chat_id, $state);

    // Autentificare QIWI
    $auth_resp = post_json_api(
        'https://api.qiwi.md/v1/auth',
        [
            "apiKey" => 18,
            "apiSecret" => "83E5+NmVPpYLJ?Fcm7hP",
            "lifetimeMinutes" => 1440
        ]
    );
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " QIWI AUTH RESP: $auth_resp\n", FILE_APPEND);

    $auth = json_decode($auth_resp, true);
    $token = $auth['token'] ?? null;
    if(!$token){
        sendMessage($chat_id, "Eroare la autentificare QIWI!");
        exit;
    }

    // Generează QR dynamic
    $merchantID = "LNM_DYN_" . date('YmdHisv');
    $detalii = $state['curse_data'][$state['selectat_cursa']];
    $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 0);

    $payload = [
        "accountIBAN" => "MD88QW000000000580074739",
        "name"        => "Dynamic Test QIWI",
        "amount"      => $amount,
        "comment"     => "Plata bilet autobuz",
        "validSeconds"=> 120,
        "redirectURL" => "https://qiwi.md/",
        "merchantID"  => $merchantID,
        "end2EndID"   => "QIWI1005",
        "reference"   => "QIWI1005"
    ];

    $qr_resp = post_json_api(
        'https://api.qiwi.md/qr/create-qr-dynamic',
        $payload,
        [
            'Authorization: Bearer ' . $token
        ]
    );
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " QIWI CREATE QR RESP: $qr_resp\n", FILE_APPEND);

    $qr = json_decode($qr_resp, true);

    if(!isset($qr['image']) || !isset($qr['text'])){
        sendMessage($chat_id, "Eroare la generarea QR. Încearcă mai târziu.");
        exit;
    }

    // Trimite text cu link și salvează id
    $msg1 = sendMessageReturn($chat_id, "Scanează QR-ul sau accesează linkul de mai jos pentru plată. Valabil 2 minute:\n{$qr['text']}");
    $textMsgId1 = $msg1['message_id'] ?? null;

    // Salvează imaginea ca fișier PNG temporar
    $img = base64_decode($qr['image']);
    $tmp = sys_get_temp_dir()."/qr_{$chat_id}.png";
    file_put_contents($tmp, $img);

    // Trimite imaginea QR
    $ch = curl_init(API_URL.'sendPhoto');
    $cfile = new CURLFile($tmp, 'image/png', "qr.png");
    $payload_img = [
        'chat_id' => $chat_id,
        'caption' => 'QR pentru plată, valabil 2 minute.',
        'photo' => $cfile
    ];
    curl_setopt($ch, CURLOPT_POST,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_img);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);
    unlink($tmp);

    $photoMsg = json_decode($resp, true);
    $photoMessageId = $photoMsg['result']['message_id'] ?? null;

    // Trimite text cu atenționare și salvează id
    $msg2 = sendMessageReturn($chat_id, "Atenție! Dacă nu achiți în 2 minute, acest cod va expira și mesajul se va șterge automat.");
    $textMsgId2 = $msg2['message_id'] ?? null;

    // Salvează în state
    $state['qr_msgid'] = $photoMessageId;
    $state['qr_time'] = time();
    $state['text_msgid1'] = $textMsgId1;
    $state['text_msgid2'] = $textMsgId2;
    $state['qr_merchant'] = $merchantID;
    $state['qiwi_token'] = $token;
    saveState($chat_id, $state);

    // ---- LOOP AUTO-VERIFICARE PLATĂ ----
    $timeout = 120; // secunde
    $interval = 1;
    $start = time();
    $paid = false;

    while(time() - $start < $timeout) {
        sleep($interval);

        $opts = [
            "http" => [
                "header" => "Authorization: Bearer $token\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $status_resp = @file_get_contents('https://api-stg.qiwi.md/qr/get-qr-status-by-merchant-id?merchantID='.$merchantID, false, $context);
        $status_data = json_decode($status_resp, true);
        $status = $status_data['status'] ?? null;

        if($status === 'Paid') {
            file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Plata confirmata la ".date('Y-m-d H:i:s')."\n", FILE_APPEND);

            // Șterge mesajele QR + text
            foreach(['qr_msgid','text_msgid1','text_msgid2'] as $k)
                if(isset($state[$k])) deleteMessage($chat_id, $state[$k]);

            // Trimite mesaj de succes
            sendMessage($chat_id, "✅ Plata a fost efectuată cu succes!\nRezervarea ta este confirmată.");
            file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Mesaj de confirmare trimis!\n", FILE_APPEND);

            // Selectează locuri libere pentru PDF
            $nr_locuri = $state['places'] ?? 0;
            $detalii = $state['curse_data'][$state['selectat_cursa']];
            $code_cursa = $detalii['code'];
            $date_cursa = $detalii['data'];
            $station    = $state['destPoint'];
            $locuri_rezervate = [];
            file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Pregatesc interogare locuri pentru PDF: code=$code_cursa, data=$date_cursa, station=$station\n", FILE_APPEND);

            if ($code_cursa && $date_cursa && $station) {
                $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$code_cursa}&data={$date_cursa}&station={$station}";
                $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
                file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Raspuns API getPlaces: $resp\n", FILE_APPEND);

                if ($resp) {
                    $json = json_decode($resp, true);
                    if (isset($json['content']['place'])) {
                        foreach ($json['content']['place'] as $p) {
                            if ($p['occupied'] == "0") {
                                $locuri_rezervate[] = $p['place_nr'];
                                if (count($locuri_rezervate) >= $nr_locuri) break;
                            }
                        }
                        file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Locuri rezervate gasite: ".implode(', ', $locuri_rezervate)."\n", FILE_APPEND);
                    } else {
                        file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Nu am gasit cheia 'place' in json-ul returnat!\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Raspuns gol de la getPlaces!\n", FILE_APPEND);
                }
            } else {
                file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Date lipsa pentru getPlaces!\n", FILE_APPEND);
            }

            $state['locuri_rezervate'] = $locuri_rezervate;
            saveState($chat_id, $state);

            // Generează și trimite PDF
            require_once(__DIR__.'/fpdf/fpdf.php');
            $pdf = new FPDF('P', 'mm', [80, 120]);
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, 'Bilet / Bon pentru cursa', 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->Cell(0, 7, 'Ruta: '.$detalii['plecare'], 0, 1);
            $pdf->Cell(0, 7, 'Data: '.$detalii['data_td'].' Ora: '.$detalii['ora'], 0, 1);

            // Afișează locuri
            if (!empty($locuri_rezervate)) {
                $pdf->Cell(0, 7, 'Locuri: '.implode(', ', $locuri_rezervate), 0, 1);
            } else {
                $pdf->Cell(0, 7, 'Locuri: '.$nr_locuri, 0, 1);
            }

            $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * $nr_locuri;
            $pdf->Cell(0, 7, 'Total: '.number_format($amount,2,'.','').' MDL', 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, 'Va dorim drum bun!', 0, 1, 'C');
            $tmp_pdf = __DIR__."/bilet_{$chat_id}.pdf";
            $pdf->Output('F', $tmp_pdf);

            // Trimite PDF la user
            $ch = curl_init(API_URL.'sendDocument');
            $cfile = new CURLFile($tmp_pdf, 'application/pdf', "Bilet.pdf");
            $payload_pdf = [
                'chat_id' => $chat_id,
                'caption' => 'Biletul/bonul tău electronic',
                'document' => $cfile
            ];
            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response_pdf = curl_exec($ch);
            curl_close($ch);
            unlink($tmp_pdf);

            // --- Rezervare locuri în API după plată ---
            // function rezervareBilete($detalii, $locuri_rezervate, $state, $prenume = 'test', $nume = 'test', $telefon = '+37311111111', $email = 'aciobanu@unisim-soft.com') {
            //     $url = "https://gam2022.unisim-soft.com/widget_una/include/reserve.php";
            //     $params = [
            //         'route'      => $detalii['code'],
            //         'data'       => $detalii['data_td'], // asigură-te că e DD.MM.YYYY
            //         'biletcount' => count($locuri_rezervate) ?: 1,
            //         'locuri'     => implode(',', $locuri_rezervate),
            //         'station'    => $state['destPoint'],
            //         'startPoint' => $state['startPoint'],
            //         'RouteCode'  => $detalii['code'],
            //         'first_name' => $prenume,
            //         'last_name'  => $nume,
            //         'phone'      => $telefon,
            //         'email'      => $email,
            //     ];
            //     return post_api($url, $params, true, 'Unisimso', 's0ft2025Web');
            // }


            function rezervareBilete(
                array $detalii,
                array $locuri_rezervate,
                int   $startPoint,
                int   $station,
                string $prenume,
                string $nume,
                string $telefon,
                string $email
            ) {
                $url = "https://gam2022.unisim-soft.com/widget_una/include/reserve.php";
                $params = [
                    'route'      => $detalii['code'],
                    'data'       => $detalii['data_td'],    // ex: "10.07.2025"
                    'biletcount' => count($locuri_rezervate) ?: 1,
                    'locuri'     => implode(',', $locuri_rezervate),
                    'station'    => $station,
                    'startPoint' => $startPoint,
                    'RouteCode'  => $detalii['code'],
                    'first_name' => $prenume,
                    'last_name'  => $nume,
                    'phone'      => $telefon,
                    'email'      => $email,
                ];
                return post_api($url, $params, true, 'Unisimso', 's0ft2025Web');
            }
            

            // $response_rezervare = rezervareBilete($detalii, $locuri_rezervate, $state);

            // --- după ce ai generat şi trimis PDF-ul cu biletul, înainte de apelul la rezervareBilete() ---

                // 1. Ia detaliile cursei și locurile rezervate
                $detalii           = $state['curse_data'][$state['selectat_cursa']];
                $locuri_rezervate  = $state['locuri_rezervate'];
                $startPoint        = $state['startPoint'];
                $station           = $state['destPoint'];

                // 2. Ia datele pasagerului din state (telefon, nume complet, email)
                $pass = $state['input_pass_data'][0];  
                //   ['phone'=>'+373...', 'name'=>'Maria Popescu', 'email'=>'maria@ex.com']

                // 3. Sparge "nume complet" în prenume + nume de familie
                $nameParts = explode(' ', trim($pass['name']), 2);
                $prenume   = $nameParts[0];
                $nume      = $nameParts[1] ?? '';

                // 4. Apelează API-ul de rezervare cu valorile exacte
                $reserveResp = rezervareBilete(
                    $detalii,
                    $locuri_rezervate,
                    $state,      // îl poți elimina din semnătură dacă vrei
                    $prenume,
                    $nume,
                    $pass['phone'],
                    $pass['email']
                );

                // 5. Loghează răspunsul pentru debugging
                file_put_contents(DEBUG_LOG, "[API RESERVE] " . print_r($reserveResp,1) . "\n", FILE_APPEND);

            file_put_contents(DEBUG_LOG, "[DEBUG][PAID][RESERVE.PHP] Răspuns API rezervare:\n$response_rezervare\n", FILE_APPEND);

            $rez = json_decode($response_rezervare, true);
            if(isset($rez['type']) && $rez['type'] === 'success') {
                $state['reservation_code'] = $rez['reservation_code'];
                saveState($chat_id, $state);
                sendMessage($chat_id, "✔️ Locurile tale au fost bornate cu succes în sistem. Vezi biletul PDF trimis.");
                file_put_contents(DEBUG_LOG, "[DEBUG][PAID][RESERVE.PHP] Rezervare SUCCES\n", FILE_APPEND);
            } else {
                sendMessage($chat_id, "⚠️ Plata a fost acceptată, dar nu am reușit să bornezi automat locurile. Contactează suport dacă întâmpini probleme.");
                file_put_contents(DEBUG_LOG, "[DEBUG][PAID][RESERVE.PHP] Rezervare EȘEC sau răspuns neașteptat\n", FILE_APPEND);
            }

            clearState($chat_id);
            $paid = true;
            break;
        }
    }

// Dacă NU s-a plătit în 120s, șterge mesajele și trimite butoane
if(!$paid) {
    foreach(['qr_msgid','text_msgid1','text_msgid2'] as $k)
        if(isset($state[$k])) deleteMessage($chat_id, $state[$k]);
    
    $kb = [ 
        [['text'=>'🔄 Generează QR nou', 'callback_data'=>'pay_qiwi']],
        [['text'=>'❌ Anulează', 'callback_data'=>'cancel']]
    ];
    sendMessage($chat_id, "⏱️ Codul QR a expirat. Apasă din nou pe plată ca să primești altul.\n\nPoți anula comanda dacă nu mai dorești să plătești.", $kb);

    unset(
        $state['qr_msgid'],
        $state['qr_time'],
        $state['text_msgid1'],
        $state['text_msgid2'],
        $state['qr_merchant'],
        $state['qiwi_token']
    );
    // !!! Adaugă și AICI:
    $state['qr_active'] = false;
    saveState($chat_id, $state);
    exit; // !!! Foarte important să fie exit aici, altfel curge în continuare codul
}

    
}







// if (isset($state['qr_msgid'], $state['qr_time'])) {
//     if (time() - $state['qr_time'] > 120) {
//         // Șterge mesajul cu QR
//         deleteMessage($chat_id, $state['qr_msgid']);
//         unset($state['qr_msgid'], $state['qr_time']);
//         saveState($chat_id, $state);

//         // Trimite mesaj cu variante
//         $kb = [
//             [['text'=>'🔄 Generează QR nou', 'callback_data'=>'pay_qiwi']],
//             [['text'=>'❌ Anulează', 'callback_data'=>'cancel']]
//         ];
//         sendMessage($chat_id, "⏱️ Codul QR a expirat. Apasă din nou pe plată ca să primești altul.\n\nPoți anula comanda dacă nu mai dorești să plătești.", $kb);
//         exit;
//     }
// }



if ($data === 'pay_card') {
    $accessToken = maib_get_access_token();
    if (!$accessToken) {
        sendMessage($chat_id, "Eroare: nu am putut obține token-ul MAIB!");
        exit;
    }
    $idx = $state['selectat_cursa'] ?? 0;
    $detalii = $state['curse_data'][$idx] ?? [];
    $nr_loc = $state['places'] ?? 1;
    $tarif = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif']));
    $amount = $tarif * $nr_loc;

    // IA DATELE reale din state
    $clientName = $state['input_pass_data'][0]['name'] ?? 'Pasager';
    $email      = $state['input_pass_data'][0]['email'] ?? 'nedefinit@bilete.md';
    $phone      = $state['input_pass_data'][0]['phone'] ?? '069000000';

    $orderId = uniqid("order_");
    $body = [
        "amount"      => round($amount, 2),
        "currency"    => "MDL",
        "clientIp"    => $_SERVER['REMOTE_ADDR'] ?? "127.0.0.1",
        "language"    => "ro",
        "description" => "Plata bilet autogara",
        "clientName"  => $clientName,
        "email"       => $email,
        "phone"       => $phone,
        "orderId"     => $orderId,
            "callbackUrl" => "https://exemplu.ro/maib_webhook.php",
            // "okUrl"       => "https://exemplu.ro/maib_complete.php?chat_id={$chat_id}&payId={$payId}",
             "okUrl" => "https://83b9353ac5da.ngrok-free.app/maib_complete.php?chat_id={$chat_id}&payId={$payId}", //for maib complete php
            "failUrl"     => "https://exemplu.ro/maib_fail.php?chat_id={$chat_id}"
    ];

    // Cerere la /v1/hold, nu /v1/pay!
    $ch = curl_init("https://api.maibmerchants.md/v1/hold");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    $result = curl_exec($ch);
    if(curl_errno($ch)){
        sendMessage($chat_id, "Eroare conexiune MAIB: " . curl_error($ch));
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    $resp = json_decode($result, true);
    file_put_contents(DEBUG_LOG, "[PAY_CARD_MAIB_HOLD_RESP]: $result\n", FILE_APPEND);

    if(isset($resp['result']['payUrl']) && isset($resp['result']['payId'])){
        $payUrl = $resp['result']['payUrl'];
        $payId  = $resp['result']['payId'];

        // Salvezi in state payId pentru pasul 2!
        $state['maib_payId'] = $payId;
        $state['maib_orderId'] = $orderId;
        $state['maib_plata_in_asteptare'] = true;
        saveState($chat_id, $state);

        // Trimite link-ul userului în bot:
        sendMessage($chat_id, 
            "Apasă butonul de mai jos pentru a achita online biletul cu cardul bancar MAIB. Vei fi redirecționat pe pagina oficială MAIB, iar la finalizarea plății revino și apasă <b>Am plătit</b> pentru validare.",
            [
                [
                    ['text' => '💳 Achită cu cardul MAIB', 'url' => $payUrl]
                ],
                [
                    ['text' => '✅ Am plătit', 'callback_data' => 'pay_card_confirm'],
                    ['text'=>'🔙 Revin la meniu', 'callback_data'=>'main_menu']
                ]
            ]
        );
    } else {
        sendMessage($chat_id, "Eroare la inițializarea plății MAIB.\n\nDetalii: ".print_r($resp, 1));
    }

    exit;
}

// --- CONFIRM MAIB PAYMENT, EMITERE BILET + BUTON ANULARE ---

if ($data === 'pay_card_confirm' && !empty($state['maib_payId'])) {
    file_put_contents(DEBUG_LOG, "[DEBUG] PAS 1 - INTRAT in if pay_card_confirm\n", FILE_APPEND);

    // 1. Obține token MAIB
    $accessToken = maib_get_access_token();
    file_put_contents(DEBUG_LOG, "[DEBUG] PAS 2 - ACCESS TOKEN: " . ($accessToken ? $accessToken : 'NULL') . "\n", FILE_APPEND);
    if (!$accessToken) {
        sendMessage($chat_id, "❌ Eroare: nu am putut obține token-ul MAIB pentru confirmare!");
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 2B - FARA ACCESS TOKEN, IESIRE\n", FILE_APPEND);
        exit;
    }

    // 2. Confirmă plata
    $body = ["payId" => $state['maib_payId']];
    file_put_contents(DEBUG_LOG, "[DEBUG] PAS 3 - BODY MAIB: " . json_encode($body) . "\n", FILE_APPEND);

    $ch = curl_init("https://api.maibmerchants.md/v1/complete");
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
    ]);
    $result = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    file_put_contents(DEBUG_LOG, "[PAY_CARD_COMPLETE_RESP] $result\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, "[DEBUG] PAS 4 - CURL ERR: $curl_err\n", FILE_APPEND);

    $resp = json_decode($result, true);

    // 3. Verifică răspunsul de la MAIB
    file_put_contents(DEBUG_LOG, "[DEBUG] PAS 5 - RASPUNS MAIB: " . var_export($resp, true) . "\n", FILE_APPEND);

    if (!empty($resp['result']['status']) && $resp['result']['status'] === 'OK') {
        // 4. Anunță user că plata a fost confirmată
        sendMessage($chat_id, "✅ Plata cu cardul a fost procesată cu succes!\nBiletul tău electronic este gata mai jos.");
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 6 - MESAJ SUCCES TRIMIS\n", FILE_APPEND);

        // 5. Extrage date din state
        $idx              = $state['selectat_cursa'] ?? null;
        $detalii          = $state['curse_data'][$idx] ?? null;

        // --- NOU: Recuperează locurile rezervate corect ---
        $locuri_rezervate = $state['locuri_rezervate'] ?? [];
        if (empty($locuri_rezervate)) {
            if (!empty($state['rezervare']['locuri'])) {
                $locuri_rezervate = $state['rezervare']['locuri'];
                file_put_contents(DEBUG_LOG, "[DEBUG] PAS 6.3 - Locuri recuperate din rezervare\n", FILE_APPEND);
            } elseif (!empty($detalii['locuri'])) {
                $locuri_rezervate = is_array($detalii['locuri']) ? $detalii['locuri'] : explode(',', $detalii['locuri']);
                file_put_contents(DEBUG_LOG, "[DEBUG] PAS 6.4 - Locuri recuperate din detalii[locuri]\n", FILE_APPEND);
            } else {
                $locuri_rezervate = ['1'];
                file_put_contents(DEBUG_LOG, "[DEBUG] PAS 6.5 - Fallback 1 loc default\n", FILE_APPEND);
            }
        }
        $nr_locuri = count($locuri_rezervate);

        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 6.6 - detalii=" . var_export($detalii, true) . " locuri_rezervate=" . var_export($locuri_rezervate, true) . "\n", FILE_APPEND);

        // Verificare date minime
        if (!$detalii || empty($detalii['plecare']) || empty($detalii['data_td']) || empty($detalii['ora']) || !$nr_locuri) {
            file_put_contents(DEBUG_LOG, "[DEBUG] EROARE DATE LIPSA pentru PDF\n", FILE_APPEND);
            sendMessage($chat_id, "Eroare la generarea biletului. Date lipsă. Contactează suportul!");
            exit;
        }

        $tarif  = (float)preg_replace('/[^0-9\.]/','', str_replace(',','.',$detalii['tarif']));
        $amount = round($tarif * $nr_locuri, 2);

        // 6. Generează PDF cu try-catch
        try {
            file_put_contents(DEBUG_LOG, "[DEBUG] PAS 7 - INTRU GENERARE PDF\n", FILE_APPEND);
            require_once(__DIR__ . '/fpdf/fpdf.php');
            file_put_contents(DEBUG_LOG, "[DEBUG] PAS 7.1 - FPDF LOADED\n", FILE_APPEND);

            $pdf = new FPDF('P','mm',[80,120]);
            $pdf->AddPage();
            $pdf->SetFont('Arial','',10);
            $pdf->Cell(0,8,'Bilet / Bon pentru cursa',0,1,'C');
            $pdf->Ln(2);
            $pdf->Cell(0,7,'Ruta: '.$detalii['plecare'],0,1);
            $pdf->Cell(0,7,'Data: '.$detalii['data_td'].'  Ora: '.$detalii['ora'],0,1);
            $pdf->Cell(0,7,'Locuri: '.implode(', ',$locuri_rezervate),0,1);
            $pdf->Cell(0,7,'Total: '.number_format($amount,2,'.','').' MDL',0,1);
            $pdf->Ln(4);
            $pdf->Cell(0,6,'Vă dorim drum bun!',0,1,'C');
            $tmp = __DIR__ . "/bilet_{$chat_id}.pdf";
            $pdf->Output('F', $tmp);
            file_put_contents(DEBUG_LOG, "[DEBUG] PAS 8 - PDF generat la $tmp\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents(DEBUG_LOG, "[DEBUG] EROARE PDF: " . $e->getMessage() . "\n", FILE_APPEND);
            sendMessage($chat_id, "Eroare la generarea PDF-ului: ".$e->getMessage());
            exit;
        }

        // 7. Trimite PDF
        $ch2 = curl_init(API_URL.'sendDocument');
        $cfile = new CURLFile($tmp,'application/pdf','Bilet.pdf');
        curl_setopt_array($ch2, [
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => [
                'chat_id'  => $chat_id,
                'caption'  => 'Biletul tău electronic',
                'document' => $cfile
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp2 = curl_exec($ch2);
        $curl_err2 = curl_error($ch2);
        curl_close($ch2);
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 9 - PDF RESP: $resp2\n", FILE_APPEND);
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 9.1 - CURL ERROR: $curl_err2\n", FILE_APPEND);
        unlink($tmp);

        // 8. Trimite buton de anulare cu reply_markup
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text'=>'❌ Anulează rezervarea','callback_data'=>'cancel_paid'],
                    ['text'=>'🔄 Retrimite bon','callback_data'=>'re_emit_bon']
                ]
            ]
        ];
        $payload = [
            'chat_id'      => $chat_id,
            'text'         => "Dacă dorești să anulezi această rezervare sau să retrimiți bonul, apasă aici:",
            'reply_markup' => json_encode($keyboard),
        ];
        $ch3 = curl_init(API_URL.'sendMessage');
        curl_setopt($ch3, CURLOPT_POST, true);
        curl_setopt($ch3, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
        $resp3 = curl_exec($ch3);
        $curl_err3 = curl_error($ch3);
        curl_close($ch3);
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 10 - BUTOANE RESP: $resp3\n", FILE_APPEND);
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 10.1 - CURL ERROR: $curl_err3\n", FILE_APPEND);

        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 11 - FINAL, IESIRE\n", FILE_APPEND);
        exit;
    } else {
        sendMessage($chat_id, "⚠️ Plata nu a fost confirmată. Te rog încearcă din nou sau contactează suportul.");
        file_put_contents(DEBUG_LOG, "[DEBUG] PAS 12 - PLATA NU A FOST CONFIRMATA\n", FILE_APPEND);
    }
    exit;
}

// ---------- PASUL DE ANULARE REZERVARE ----------

if ($data === 'cancel_paid') {
    file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE_REZERVARE - INTRAT\n", FILE_APPEND);

    $state = loadState($chat_id);
    if (empty($state['reservation_code'])) {
        sendMessage($chat_id, "❌ Nu găsesc codul rezervării. Contactează suportul!");
        return;
    }
    $reservation_code = $state['reservation_code'];

    // Construiește URL-ul cu reservation_code
    $url = "https://gam2022.unisim-soft.com/widget_una/include/cancel_reservation.php?reservation_code=" . urlencode($reservation_code);

    // Autentificare BASIC
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, 'Unisimso:s0ft2025Web');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE - CURL_ERR: $err\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE - RESP: $result\n", FILE_APPEND);

    $cres = json_decode($result, true);

    if (!empty($cres['type']) && $cres['type']==='success') {
        sendMessage($chat_id, "✅ Rezervarea a fost anulată cu succes.");
    } else {
        sendMessage($chat_id, "❌ Eroare la anulare. Te rog contactează suportul.");
    }

    clearState($chat_id);
    file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE - IESIRE\n", FILE_APPEND);
    exit;
}

// ---------- BONUS: RE-EMITERE BON PDF ----------
if ($data === 're_emit_bon') {
    file_put_contents(DEBUG_LOG, "[DEBUG] RE_EMIT_BON - INTRAT\n", FILE_APPEND);

    $idx              = $state['selectat_cursa'] ?? null;
    $detalii          = $state['curse_data'][$idx] ?? null;
    $locuri_rezervate = $state['locuri_rezervate'] ?? [];
    if (empty($locuri_rezervate)) {
        if (!empty($state['rezervare']['locuri'])) {
            $locuri_rezervate = $state['rezervare']['locuri'];
        } elseif (!empty($detalii['locuri'])) {
            $locuri_rezervate = is_array($detalii['locuri']) ? $detalii['locuri'] : explode(',', $detalii['locuri']);
        } else {
            $locuri_rezervate = ['1'];
        }
    }
    $nr_locuri = count($locuri_rezervate);
    $tarif  = (float)preg_replace('/[^0-9\.]/','', str_replace(',','.',$detalii['tarif']));
    $amount = round($tarif * $nr_locuri, 2);

    try {
        require_once(__DIR__ . '/fpdf/fpdf.php');
        $pdf = new FPDF('P','mm',[80,120]);
        $pdf->AddPage();
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0,8,'Bilet / Bon pentru cursa',0,1,'C');
        $pdf->Ln(2);
        $pdf->Cell(0,7,'Ruta: '.$detalii['plecare'],0,1);
        $pdf->Cell(0,7,'Data: '.$detalii['data_td'].'  Ora: '.$detalii['ora'],0,1);
        $pdf->Cell(0,7,'Locuri: '.implode(', ',$locuri_rezervate),0,1);
        $pdf->Cell(0,7,'Total: '.number_format($amount,2,'.','').' MDL',0,1);
        $pdf->Ln(4);
        $pdf->Cell(0,6,'Vă dorim drum bun!',0,1,'C');
        $tmp = __DIR__ . "/bilet_{$chat_id}.pdf";
        $pdf->Output('F', $tmp);
    } catch (Exception $e) {
        sendMessage($chat_id, "Eroare la generarea PDF-ului: ".$e->getMessage());
        exit;
    }

    $ch2 = curl_init(API_URL.'sendDocument');
    $cfile = new CURLFile($tmp,'application/pdf','Bilet.pdf');
    curl_setopt_array($ch2, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chat_id,
            'caption'  => 'Biletul tău electronic',
            'document' => $cfile
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch2);
    curl_close($ch2);
    unlink($tmp);

    exit;
}


// if ($data === 'pay_card_confirm' && !empty($state['maib_payId'])) {
//     $accessToken = maib_get_access_token();
//     if (!$accessToken) {
//         sendMessage($chat_id, "Eroare: nu am putut obține token-ul MAIB pentru confirmare!");
//         exit;
//     }
//     $payId = $state['maib_payId'];
//     $body = [ "payId" => $payId ];

//     $ch = curl_init("https://api.maibmerchants.md/v1/complete");
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         "Authorization: Bearer $accessToken",
//         "Content-Type: application/json"
//     ]);
//     $result = curl_exec($ch);
//     if(curl_errno($ch)){
//         sendMessage($chat_id, "Eroare conexiune MAIB (confirmare): " . curl_error($ch));
//         curl_close($ch);
//         exit;
//     }
//     curl_close($ch);

//     $resp = json_decode($result, true);
//     file_put_contents(DEBUG_LOG, "[PAY_CARD_MAIB_COMPLETE_RESP]: $result\n", FILE_APPEND);

//     if (isset($resp['result']['status']) && $resp['result']['status'] === 'OK') {
//         // Banii au fost retrași. Emite biletul aici!
//         sendMessage($chat_id, "✅ Plata cu cardul a fost procesată cu succes! Rezervarea este confirmată.\n\nImediat primești biletul electronic.");
    
//         // 1. Extrage datele necesare
//         $idx              = $state['selectat_cursa'];
//         $detalii          = $state['curse_data'][$idx];
//         $locuri_rezervate = $state['locuri_rezervate'];
//         $nr_locuri        = count($locuri_rezervate);
    
//         // 2. Generează și trimite PDF
//         require_once(__DIR__.'/fpdf/fpdf.php');
//         $pdf = new FPDF('P', 'mm', [80, 120]);
//         // … restul codului de construire a PDF-ului, așa cum îl ai …
    
//         // 3. Trimite PDF-ul
//         $tmp_pdf = __DIR__ . "/bilet_{$chat_id}.pdf";
//         $pdf->Output('F', $tmp_pdf);
//         $ch = curl_init(API_URL.'sendDocument');
//         $cfile = new CURLFile($tmp_pdf, 'application/pdf', "Bilet.pdf");
//         curl_setopt_array($ch, [
//           CURLOPT_POST       => 1,
//           CURLOPT_POSTFIELDS => ['chat_id'=>$chat_id, 'caption'=>'Biletul tău electronic','document'=>$cfile],
//           CURLOPT_RETURNTRANSFER => true,
//           CURLOPT_SSL_VERIFYPEER => false,
//         ]);
//         curl_exec($ch);
//         curl_close($ch);
//         unlink($tmp_pdf);
    
      

//             sendMessage($chat_id,
//         "Dacă vrei să anulezi această rezervare, apasă butonul de mai jos:",
//         [
//         [
//             ['text'=>'❌ Anulează rezervarea','callback_data'=>'cancel_paid']
//         ]
//         ]
//     );
//         clearState($chat_id);
//     } else {
//         sendMessage($chat_id, "Eroare sau plata nu a fost confirmată încă. Încearcă din nou peste câteva secunde sau contactează suportul.");
//     }
//     exit;
// }


// if($data==='pay_card'){
//     sendMessage($chat_id, "Plata cu card: (DEMO) În curând vei putea plăti direct cu cardul.");
//     exit;
// }

if ($data === 'pay_cash_casa') {
    // 1. Găsește detaliile cursei selectate
    $idx = $state['selectat_cursa'] ?? null;
    $detalii = $state['curse_data'][$idx] ?? [];
    
    $nr_loc = $state['places'] ?? 0;
    $tarif = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif']));
    $pret_total = $tarif * $nr_loc;
    $pret_total_fmt = number_format($pret_total, 2, '.', '');
    $now = time();

    // 2. Calculăm limita de plată (maxim 48h sau până la 1h înainte de plecare, oricare e mai devreme)
    $data_ora_plecare = "{$detalii['data_td']} {$detalii['ora']}";
    $plecare_timestamp = strtotime(str_replace('.', '-', $detalii['data_td']) . ' ' . $detalii['ora']);
    $max_deadline = min($plecare_timestamp - 3600, $now + 48*3600); // cu 1 oră înainte sau 48h din acest moment
    $deadline_txt = date('d.m.Y H:i', $max_deadline);

    // 3. Generează un cod unic de rezervare (sau folosește id-ul userului și data)
    $cod_rezervare = strtoupper('RESV-' . $chat_id . '-' . date('YmdHis'));

    // 4. Salvează rezervarea în state (poți salva și într-un fișier/log DB pentru evidență)
    $state['rezervare_pending'] = [
        'cod'      => $cod_rezervare,
        'bilete'   => $nr_loc,
        'total'    => $pret_total_fmt,
        'deadline' => $max_deadline,
        'deadline_txt' => $deadline_txt,
        'status'   => 'pending',
        'detalii'  => $detalii,
        'created'  => $now,
    ];
    saveState($chat_id, $state);

    // 5. Trimite instrucțiunile către utilizator
    $msg = "<b>Rezervare temporară (maxim 5 bilete)</b>\n\n" .
        "🔒 Rezervarea ta a fost blocată și trebuie achitată <b>la casă</b> sau prin <b>transfer bancar</b>.\n\n" .
        "• Cod rezervare: <b>$cod_rezervare</b>\n" .
        "• Ruta: {$detalii['plecare']} ({$detalii['ruta']})\n" .
        "• Data: {$detalii['data_td']} Ora: {$detalii['ora']}\n" .
        "• Număr bilete: <b>$nr_loc</b>\n" .
        "• Preț pe loc: <b>{$detalii['tarif']}</b> MDL\n" .
        "• <b>Total:</b> $pret_total_fmt MDL\n\n" .
        "✅ Locurile sunt rezervate până la <b>$deadline_txt</b>.\n\n" .
        "<b>Instrucțiuni:</b>\n" .
        "1️⃣ Mergi la orice casă din autogară sau efectuează transfer bancar folosind codul de rezervare.\n" .
        "2️⃣ Prezintă codul și achită suma până la data limită.\n" .
        "3️⃣ Dacă nu achiți până la <b>$deadline_txt</b>, rezervarea se anulează automat.\n\n" .
        "<i>Pentru detalii suplimentare contactează casieria sau suportul: 022-222-222</i>";

    $kb = [
        [['text' => 'Revin la meniul principal', 'callback_data' => 'main_menu']]
    ];

    sendMessage($chat_id, $msg, $kb);
    exit;
}


if ($data === 'main_menu') {
    clearState($chat_id);
    $msg = "Căutăm bilete la tren sau autobuz?\nAlege, te rog, ce te interesează 👇";
    $kb = [
        [
            ['text' => "🚆 Tren",   'callback_data' => 'type_tren'],
            ['text' => "🚌 Autobuz", 'callback_data' => 'type_bus']
        ]
        // ,
        // [
        //     ['text' => "🧑‍💼 Ai nevoie de ajutor?", 'callback_data' => 'need_help']
        // ]
    ];

    $state = ['step' => 'select_type', 'lang' => 'ro'];
    saveState($chat_id, $state);

    sendMessage($chat_id, $msg, $kb);
    exit;
}



// if($data==='pay_cash'){
//     $detalii = $state['curse_data'][$state['selectat_cursa']];
//     $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 1);

//     // Încearcă să obții cod QIWI (doar dacă ai API confirmat)
//     $auth_resp = post_json_api(
//         'https://api-stg.qiwi.md/v1/auth',
//         [
//             "apiKey" => 13,
//             "apiSecret" => "Tk4yWcQPWb4Qa2=CPgDa",
//             "lifetimeMinutes" => 1440
//         ]
//     );
//     $auth = json_decode($auth_resp, true);
//     $token = $auth['token'] ?? null;

//     $invoiceResp = '';
//     if ($token) {
//         $invoiceResp = post_json_api(
//             'https://api-stg.qiwi.md/v1/invoice/create-cash',
//             [
//                 "amount" => $amount,
//                 "currency" => "MDL",
//                 "comment" => "Plata bilet cash",
//                 "lifetimeMinutes" => 120,
//                 "reference" => uniqid("bilet_", true),
//             ],
//             [
//                 'Authorization: Bearer ' . $token
//             ]
//         );
//     }
//     file_put_contents(DEBUG_LOG, "[CASH INVOICE QIWI RESP]: $invoiceResp\n", FILE_APPEND);
//     $invoice = json_decode($invoiceResp, true);

//     if(isset($invoice['code']) && !empty($invoice['code'])){
//         // Cod QIWI generat corect (Rar! Doar dacă API funcționează!)
//         $cod_plata = $invoice['code'];
//         sendMessage(
//             $chat_id,
//             "Plată cash la terminal QIWI:\n\n".
//             "1️⃣ Mergi la orice terminal QIWI din Moldova.\n".
//             "2️⃣ Selectează <b>Plată după cod</b>.\n".
//             "3️⃣ Introdu codul: <b>$cod_plata</b>.\n".
//             "4️⃣ Plătește suma: <b>$amount MDL</b>.\n\n".
//             "După plată, biletul va fi validat automat."
//         );
//     } else {
//         // **Fallback universal**: doar instrucțiuni text
//         sendMessage(
//             $chat_id,
//             "Plată cash la terminal QIWI:\n\n".
//             "1️⃣ Mergi la orice terminal QIWI din Moldova.\n".
//             "2️⃣ Caută <b>Bilete Gara</b> sau <b>Plăți online</b>.\n".
//             "3️⃣ Introdu datele rezervării sau solicită ajutor la casierie.\n".
//             "4️⃣ Plătește suma exactă afișată.\n\n".
//             "Dacă nu găsești opțiunea sau ai întrebări, revino în bot sau alege altă metodă de plată!"
//         );
//     }
//     exit;
// }

// function checkDemoCashPaid($code) {
//     $paid = false;
//     $codes = file_exists('paid_cash_codes.txt')
//         ? file('paid_cash_codes.txt', FILE_IGNORE_NEW_LINES)
//         : [];
//     if (in_array($code, $codes)) $paid = true;
//     return $paid;
// }

function checkDemoCashPaid($code) {
    $codes = file_exists(__DIR__.'/paid_cash_codes.txt')
        ? file(__DIR__.'/paid_cash_codes.txt', FILE_IGNORE_NEW_LINES)
        : [];
    return in_array($code, $codes);
}


if ($data === 'pay_cash') {
    $detalii = $state['curse_data'][$state['selectat_cursa']];
    $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 0);
    file_put_contents(DEBUG_LOG, "[QR][AMOUNT] $amount\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, "[QR][DETALII] ".print_r($detalii,1)."\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, "[QR][STATE] ".print_r($state,1)."\n", FILE_APPEND);
    // Generezi cod cash demo
    $cod_cash = "CASH-" . rand(10000,99999); // Poți genera unic cu uniqid()
    $state['pay_cash_code'] = $cod_cash;
    saveState($chat_id, $state);

    // Salvezi instrucțiunile și linkul de simulare plată
    $msg = "Plată DEMO cash:\n\n" .
        "1️⃣ Mergi la terminal DEMO sau <a href='https://83b9353ac5da.ngrok-free.app/plata-cash-demo.php?code=$cod_cash'>apasa aici pentru a marca ca platit</a>\n".
           "2️⃣ Codul tău: <b>$cod_cash</b>\n".
           "3️⃣ Suma: <b>$amount MDL</b>\n\n".
           "Așteaptă confirmarea automată a plății.";
    sendMessage($chat_id, $msg);

    // Începe verificarea automată (loop scurt)
    $max_wait = 60; // secunde, sau cât vrei tu (până la 2 min)
    $waited = 0;
    $interval = 3; // secunde
    while ($waited < $max_wait) {
        sleep($interval);
        if (checkDemoCashPaid($cod_cash)) {
            sendMessage($chat_id, "✅ Plata cash a fost procesată cu succes! Rezervarea este confirmată.");
    //             // 2. Apelează API-ul de rezervare exact ca la QIWI
    $detalii          = $state['curse_data'][$state['selectat_cursa']];
    $locuri_rezervate = $state['locuri_rezervate'];
    $pass             = $state['input_pass_data'][0];
    $nameParts        = explode(' ', trim($pass['name']), 2);
    $prenume          = $nameParts[0];
    $nume             = $nameParts[1] ?? '';
    $phone            = $pass['phone'];
    $email            = $pass['email'];

    $reserveResp = rezervareBilete(
        $detalii,
        $locuri_rezervate,
        $state,
        $prenume,
        $nume,
        $phone,
        $email
    );
    file_put_contents(DEBUG_LOG, "[CASH DEMO RESERVE] ".print_r($reserveResp,1)."\n", FILE_APPEND);

          
            require_once(__DIR__.'/fpdf/fpdf.php');
            $pdf = new FPDF('P', 'mm', [80, 120]);
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, 'Bilet / Bon pentru cursa', 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->Cell(0, 7, 'Ruta: '.$detalii['plecare'], 0, 1);
            $pdf->Cell(0, 7, 'Data: '.$detalii['data_td'].' Ora: '.$detalii['ora'], 0, 1);
            $pdf->Cell(0, 7, 'Locuri: '.($state['places'] ?? 0), 0, 1);
            $pdf->Cell(0, 7, 'Total: '.number_format($amount,2,'.','').' MDL', 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, 'Va dorim drum bun!', 0, 1, 'C');
            $tmp_pdf = __DIR__."/bilet_{$chat_id}.pdf";
            $pdf->Output('F', $tmp_pdf);

            $ch = curl_init(API_URL.'sendDocument');
            $cfile = new CURLFile($tmp_pdf, 'application/pdf', "Bilet.pdf");
            $payload_pdf = [
                'chat_id' => $chat_id,
                'caption' => 'Biletul/bonul tău electronic',
                'document' => $cfile
            ];
            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response_pdf = curl_exec($ch);
            curl_close($ch);
            unlink($tmp_pdf);

            clearState($chat_id);
            break;
        }
        $waited += $interval;
    }
    if ($waited >= $max_wait) {
        sendMessage($chat_id, "⏰ Plata cash nu a fost confirmată în timp util. Încearcă din nou.");
        clearState($chat_id);
    }
    exit;
}



if(isset($state['pay_cash_demo_code'])){
    $code = $state['pay_cash_demo_code'];
    $file = __DIR__."/state/cash_paid_{$code}.txt";
    if(file_exists($file)){
        sendMessage($chat_id, "✅ Plata cash (DEMO) procesată cu succes! Rezervarea ta este confirmată.");
        unlink($file);
        clearState($chat_id);
        exit;
    } else {
        sendMessage($chat_id, "Așteptam confirmarea plății cash... (DEMO)\nDacă ai achitat deja, revino peste câteva secunde sau apasă /check");
        exit;
    }


    
}



// if (isset($state['pay_cash_code'], $state['pay_cash_token'], $state['pay_cash_merchant'])) {
//     $timeout = 3600; // 1 oră
//     $interval = 10;
//     $start = time();
//     $paid = false;
//     while(time() - $start < $timeout) {
//         sleep($interval);
//         $opts = [
//             "http" => [
//                 "header" => "Authorization: Bearer {$state['pay_cash_token']}\r\n"
//             ]
//         ];
//         $context = stream_context_create($opts);
//         $status_resp = @file_get_contents('https://api-stg.qiwi.md/cash-in/status?merchantID='.$state['pay_cash_merchant'], false, $context);
//         $status_data = json_decode($status_resp, true);
//         $status = $status_data['status'] ?? null;

//         if($status === 'Paid') {
//             // -- finalizează comanda ca la QIWI QR --
//             sendMessage($chat_id, "✅ Plata cash a fost procesată cu succes! Rezervarea este confirmată.");
//             clearState($chat_id);
//             $paid = true;
//             break;
//         }
//     }
//     if(!$paid){
//         sendMessage($chat_id, "⏰ Codul pentru plată cash a expirat. Apasă din nou pe 'Plată cash' pentru un cod nou.");
//         clearState($chat_id);
//     }
//     exit;
// }
/**
 * Trimite cerere de anulare către API-ul Unisim
 */





// === CANCEL ETC ===
if($data==='cancel'){
    clearState($chat_id);
    sendMessage($chat_id,"La revedere, mersi! Dacă vrei din nou, folosește comanda /search");
    exit;
}
if (isset($text) && $text !== '/start' && $text !== '/search') {
    clearState($chat_id);

    $msg = "Căutăm bilete la tren sau autobuz?\nAlege, te rog, ce te interesează 👇";
    $kb = [
        [
            ['text' => "🚆 Tren",   'callback_data' => 'type_tren'],
            ['text' => "🚌 Autobuz", 'callback_data' => 'type_bus']
        ],
        [
            ['text' => "🧑‍💼 Ai nevoie de ajutor?", 'callback_data' => 'need_help']
        ]
    ];

    $state = ['step' => 'select_type', 'lang' => 'ro'];
    saveState($chat_id, $state);

    sendMessage($chat_id, $msg, $kb);
    exit;
}
clearState($chat_id);
sendMessage($chat_id,$messages['error'][$lang]);
http_response_code(200);
?>


