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
    function post_api($url, $params = [], $urlencoded = true, $username = null, $password = null) {
        $ch = curl_init($url);
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        if($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Eroare cURL: $error");
        }

        curl_close($ch);
        return $result;
    }

function showCursePeZi($chat_id, $state) {
    $startPoint      = $state['startPoint'];
    $destPoint       = $state['destPoint'];
    $plecare_full    = $state['last_filter'] ?? '';
    $destinatie_full = $state['last_filter_dest'] ?? '';
    $plecare_norm    = mb_strtoupper(removeDiacritics(trim($plecare_full)), 'UTF-8');
    $chisinau_statii = ['318880','318440','319502','317765','317345'];
if (in_array($state['startPoint'], $chisinau_statii)) {
    $plecare_norm = 'CHISINAU';
}
    $destinatie_norm = mb_strtoupper(removeDiacritics(trim($destinatie_full)), 'UTF-8');
    $destinatie_short = explode(' ', $destinatie_norm)[0];
    $foundDates = [];
    $loading = sendMessageReturn($chat_id, "🔄 Caut rute disponibile...\nProgress: [⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜]");
    $loading_mid = $loading['message_id'];
    $progressTotal = 10;

    for ($i = 0; $i < $progressTotal; $i++) {
        $ts   = strtotime("+{$i} day");
        $data = date('Y-m-d', $ts);

        $resp = post_api(
            "https://gam2022.unisim-soft.com/widget_una/include/do_search.php?",
            [
                'startPoint'   => $startPoint,
                'station'      => $destPoint,
                'data'         => $data,
                'connect_type' => 'web',
                'org'          => 'all',
                'ro'           => '',
                'api_type'     => ''
            ],
            true, 'Unisimso', 's0ft2025Web'
        );
        if (preg_match_all(
            '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*' .
            '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>/is',
            $resp, $matches, PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $titlu_norm = mb_strtoupper(removeDiacritics(trim($m[3])), 'UTF-8');
                $titlu_norm = preg_replace('/\s*=>\s*/', '->', $titlu_norm);
                $titlu_norm = preg_replace('/\s+/', ' ', $titlu_norm);
                $search = $plecare_norm . '->' . $destinatie_short;
                $search = preg_replace('/\s+/', ' ', $search);
                file_put_contents(DEBUG_LOG, "[DEBUG COMPARA] titlu_norm='$titlu_norm' search='$search'\n", FILE_APPEND);
                if (strpos($titlu_norm, $search) === 0) {
                    $foundDates[] = $ts;
                    break;
                }
            }
        }
        $progressCount = $i + 1;
        $progressFilled = str_repeat('🟦', $progressCount);
        $progressEmpty = str_repeat('⬜', $progressTotal - $progressCount);
        $progressText = "🔄 Caut rute disponibile...\nProgress: \n[{$progressFilled}{$progressEmpty}]";
        editMessageText($chat_id, $loading_mid, $progressText);

        usleep(100000);
    }
    usleep(250000);
    deleteMessage($chat_id, $loading_mid);
    if (empty($foundDates)) {
        $inline_keyboard = [
            [
                ['text' => 'Vezi curse generale', 'callback_data' => 'show_general']
            ]
        ];
        sendMessage(
            $chat_id,
            "❌ Nu sunt curse valabile pentru ruta <b>$plecare_norm → $destinatie_norm</b> în următoarele 14 zile.\n" .
            "Doriţi să vedeţi toate cursele la general pentru destinația aleasă?",
            $inline_keyboard
        );
        return;
    }
        $zile_ro = [
        'Monday'    => 'Luni',
        'Tuesday'   => 'Marți',
        'Wednesday' => 'Miercuri',
        'Thursday'  => 'Joi',
        'Friday'    => 'Vineri',
        'Saturday'  => 'Sâmbătă',
        'Sunday'    => 'Duminică'
    ];

    $inline_keyboard = [];
    foreach ($foundDates as $ts) {
         $en = date('l', $ts);
        $ro = $zile_ro[$en] ?? $en;
        $inline_keyboard[] = [
        ['text' => date('d.m', $ts) . ', ' . $ro, 'callback_data' => 'date_' . date('d.m', $ts)]
        ];
    }
    sendMessage(
        $chat_id,
        "🗓️ Alege data pentru ruta $plecare_norm → $destinatie_norm:",
        $inline_keyboard
    );
}

function formatCurseListMsg($curse_data) {
    $txt = "";
    foreach ($curse_data as $idx => $d) {
        $txt .= "🚌 <b>{$d['plecare']}</b>\n";
        $txt .= "⏰ Plecare: {$d['ora']}, {$d['data_td']}\n";
        if (!empty($d['sosire']) && $d['sosire'] !== '—') {
            $txt .= "🔽 Sosire: {$d['sosire']}\n";
        }
        $txt .= "💳 Preț: {$d['tarif']}\n";
        $txt .= "💺 Locuri libere: {$d['locuri_libere']}\n";
        $txt .= "🔗 /Cumpara_bilet_{$idx}\n";
        $txt .= "──────────────\n\n";
    }
    return $txt;
}


    function fuzzyMatch($input, $list, $threshold = 0.5) {
    $results = [];
    $input_norm = normalize($input);
    foreach ($list as $item) {
        $title_norm = normalize($item['TITLE']);
        $lev = levenshtein($input_norm, $title_norm);
        $max_len = max(strlen($input_norm), strlen($title_norm));
        $similarity = 1 - ($lev / $max_len);
        if ($similarity >= $threshold || strpos($title_norm, $input_norm) !== false) {
            $results[] = [
                'station' => $item,
                'score' => $similarity
            ];
        }
    }
    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($results, 0, 10);
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
            "accountIBAN" => "MD88QW000000000580074739",
            "amount" => $amount,
            "comment" => "Plata cash la terminal",
            "validSeconds" => 3600, 
            "merchantID" => "CASH_" . date('YmdHisv'),
            "reference" => "CASH_" . $chat_id,
            "redirectURL" => "https://qiwi.md/"
        ];
        $bill_resp = post_json_api(
            'https://api.qiwi.md/cash-in/create',
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

    function getStationNameById($id) {
    $response = json_decode(file_get_contents('https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all'), true);
    foreach($response['content'] as $sp){
        if($sp['COD'] == $id) return $sp['TITLE'];
    }
    return $id;
}
$start = $state['last_filter'] ?? getStationNameById($state['startPoint']);
$dest  = $state['destPointName'] ?? getStationNameById($state['destPoint']);

    function answerCallback($callbackId) {
        @file_get_contents(API_URL . 'answerCallbackQuery?callback_query_id=' . urlencode($callbackId));
    }
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
    function formatDetalii(array $detalii, int $n, ?int $locuri_libere = null): string {
        $data    = $detalii['data_td'] ?? $detalii['data'] ?? '—';
        $sosire  = $detalii['sosire'] ?? '—';
        $free    = $locuri_libere ?? ($detalii['locuri_libere'] ?? '—');

        return
            "🚌 <b>Ruta:</b> {$detalii['plecare']}\n" .
            "🔼 <b>Plecare:</b> {$detalii['ora']}, {$data}\n" .
            "🔽 <b>Sosire:</b> {$sosire}\n" .
            "ℹ️ <b>Poți transporta animale de până la 10 kg (cu cușcă și acte complete). Pentru animale se achită loc separat la preț întreg (indică DOG/CAT).</b>\n" .
            "🎦 <b>Servicii:</b> TV, băuturi, 1 bagaj gratuit, bilet online, Wi-Fi, mâncare, muzică, aer condiționat, priză, transport animale.\n" .
            "💳 <b>Preț:</b> {$detalii['tarif']} per loc\n" .
            "💺 <b>Locuri libere:</b> {$free}\n\n" .
            "👇 Este valabilă repartizarea liberă a locurilor. Selectează câte locuri vrei cu butoanele + și -, apoi apasă „Cumpără”.\n" .
            "✔️ <b>Locuri dorite:</b> {$n}";
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
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($inline_keyboard !== null) {
        $payload['reply_markup'] = ['inline_keyboard' => $inline_keyboard];
    } elseif ($keyboard !== null) {
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $res = curl_exec($ch);
    curl_close($ch);

    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " SEND: $json\n", FILE_APPEND);
    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " RESP: $res\n", FILE_APPEND);
}

    function getStatePath($chat_id) { return __DIR__ . "/state/{$chat_id}.json"; }
    function loadState($chat_id) {
        $file = getStatePath($chat_id);
        return file_exists($file)
            ? json_decode(file_get_contents($file), true)
            : ['step' => 'lang'];
    }
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
        $txt = removeDiacritics($txt);
        $txt = mb_strtolower($txt, 'UTF-8');
        $txt = preg_replace('/[\s\-\>\<\.\/\(\)]/', '', $txt); 
        $txt = preg_replace('/[^\p{L}]/u', '', $txt);

        return $txt;
    }
    function sendLongMessage($chat_id, $text, $maxLen = 3500) {
        $text = str_replace("\\n\\n", "\n\n", $text);
        while (mb_strlen($text, 'UTF-8') > $maxLen) {
            $pos = mb_strrpos(mb_substr($text, 0, $maxLen, 'UTF-8'), "\n\n", 0, 'UTF-8');
            if ($pos === false || $pos < $maxLen * 0.5) {
                $pos = $maxLen;
            }
            $chunk = mb_substr($text, 0, $pos, 'UTF-8');
            if (trim($chunk) !== "") {
                sendMessage($chat_id, $chunk);
                usleep(150000);
            }
            $text = mb_substr($text, $pos, null, 'UTF-8');
        }
        if (mb_strlen($text, 'UTF-8') > 0 && trim($text) !== "") {
            sendMessage($chat_id, $text);
        }
    }
    function removeDiacritics($string) {
        $diacritics = [
            'ă'=>'a', 'â'=>'a', 'î'=>'i', 'ș'=>'s', 'ş'=>'s', 'ț'=>'t', 'ţ'=>'t',
            'Ă'=>'A', 'Â'=>'A', 'Î'=>'I', 'Ș'=>'S', 'Ş'=>'S', 'Ț'=>'T', 'Ţ'=>'T'
        ];
        return strtr($string, $diacritics);
    }
    function showSelectPlaces($chat_id, &$state, $detalii) {
        $n = $state['places'] ?? 1;
        if ($n < 1) $n = 1;
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
    function groupButtons($buttons, $perRow = 2) {
    return array_chunk($buttons, $perRow);
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
    if($text==='/start'){
        
        clearState($chat_id);
        
        $state = ['step' => 'from_city_text', 'lang' => 'ro'];
        saveState($chat_id, $state);
        sendMessage($chat_id, "Salut din nou! 🙂\nApasă /search pentru a începe căutarea biletelor.");
        exit;
    }
    if (isset($text) && preg_match('/^\/(start )?Cumpara_bilet_(\d+)/', $text, $mm)) {
        $idx = (int)$mm[2];
        $detalii = $state['curse_data'][$idx] ?? null;
        if (!$detalii) {
            sendMessage($chat_id, "Eroare: această cursă nu mai este valabilă sau sesiunea a expirat. Te rugăm să reiei căutarea.");
            exit;
        }
        $routeCode = $detalii['code'];
        $dateTd    = $detalii['data_td'] ?? $detalii['data'];
        $respPlaces = post_api(
            "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php",
            [
                'code'    => $routeCode,
                'data'    => $dateTd,
                'station' => $state['destPoint'],
            ],
            true, 'Unisimso', 's0ft2025Web'
        );
        $jp = json_decode($respPlaces, true);
        $free = 0;
        if (!empty($jp['content']['place'])) {
            foreach ($jp['content']['place'] as $p) {
                if ($p['occupied'] === "0") {
                    $free++;
                }
            }
        }
        $detalii['locuri_libere'] = $free;
        $state['curse_data'][$idx] = $detalii;
        saveState($chat_id, $state);
        $state['selectat_cursa'] = $idx;
        $state['places']         = 1;
        $state['step']           = 'choose_places';
        saveState($chat_id, $state);
        $rez = sendMessageReturn(
            $chat_id,
            formatDetalii(
                $detalii,
                $state['places'],
                $detalii['locuri_libere']
            ),
            makePlacesKeyboard($state['places'])
        );
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
            ['text' => "🚌 Autobuz", 'callback_data' => 'type_bus'],
        ],
        [
            ['text' => "🤖 Am nevoie de ajutor!", 'callback_data' => 'need_help']

        ]
    ];
    $state = ['step' => 'select_type', 'lang' => 'ro'];
    saveState($chat_id, $state);
    sendMessage($chat_id, $msg, $kb);
    exit;
}

if ($data && $state['step'] === 'select_type') {
    $mid = $update['callback_query']['message']['message_id'];
    deleteMessage($chat_id, $mid);

    if ($data === 'type_tren') {
        $state['step'] = 'from_city_text_tren';
        saveState($chat_id, $state);
        sendMessage($chat_id, "📝 Scrie stația de plecare a trenului!\nExemplu: Chișinău sau București");
        exit;
    }
    if ($data === 'type_bus') {
        $state['step'] = 'from_city_text';
        saveState($chat_id, $state);
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
            ],
            [
                ['text' => "🤖 Am nevoie de ajutor!", 'callback_data' => 'need_help']

            ]
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
                $kb = [
            [
                ['text' => "🔍 Caută bilete",         'callback_data' => 'main_menu'],
            ],
            [
                ['text' => "📞 Contactează-ne",       'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "❌ Anulează Bronarea",    'callback_data' => 'cancel_reservation'],
                ['text' => "✅ Verifică Bronarea",    'callback_data' => 'check_reservation'],
            ]
        ];

        file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . " CALLBACK NECUNOSCUT LA SELECT_TYPE: $data\n", FILE_APPEND);
        sendMessage($chat_id, $help_text, $kb);
        exit;
    }

    if ($data === 'contact_support') {
                $user_id = $chat_id;

            $help_text = "Pentru suport scrie pe @una_support sau mail@gara.com";
    $kb = [
            [
                ['text' => "🔍 Caută bilete",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],

            ]
        ];
            sendMessage($chat_id, $help_text, $kb);

    exit;
}



}



if ($data === 'cancel_reservation') {
    $mid = $update['callback_query']['message']['message_id'] ?? null;
    if ($mid) {
        deleteMessage($chat_id, $mid);
    }
        $help_text = "🗑️ Trimite codul rezervării pe care vrei să o anulezi.\nExemplu: <code>123456789</code>";
    sendMessage($chat_id, $help_text , []);
    $state['step'] = 'awaiting_cancel_code';
    saveState($chat_id, $state);
    exit;
}


if ($data === 'check_reservation') {
    $mid = $update['callback_query']['message']['message_id'] ?? null;
    if ($mid) {
        deleteMessage($chat_id, $mid);
    }
     $help_text = "🔎 Trimite codul rezervării pentru a verifica detalii.\nExemplu: <code>51518960</code>";
    sendMessage($chat_id,  $help_text);
    $state['step'] = 'awaiting_check_code';
    saveState($chat_id, $state);
    exit;
}
if (
    ($state['step'] ?? '') === 'awaiting_check_code' &&
    !empty($text)
) {
    $rez_code = trim($text);

    if (strlen($rez_code) < 6) {
        sendMessage($chat_id, "⚠️ Codul de rezervare introdus nu pare valid.\nÎncearcă din nou.");
        exit;
    }
    $user = 'Unisimso';
    $pass = 's0ft2025Web';
    $check_url = "https://gam2022.unisim-soft.com/widget_una/include/get_reservation.php?reservation_code={$rez_code}";

    $ch = curl_init($check_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass"); 
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $checkResp = curl_exec($ch);
    curl_close($ch);
    $cres = json_decode($checkResp, true);
    if (!empty($cres['type']) && $cres['type'] === 'success') {
        $row = $cres['info_reservation']['ROW'];
        $ticket = $cres['info_ticket']['ROW'] ?? [];

        $plecare    = $row['DEPARTURE'] ?? '-';
        $sosire     = $row['ARRIVAL'] ?? '-';
        $ora        = $row['SEANS'] ?? '-';
        $nume       = $row['FIRST_NAME'] ?? '-';
        $prenume    = $row['LAST_NAME'] ?? '-';
        $telefon    = $row['PHONE'] ?? '-';
        $stare      = $row['STATE'] ?? '-';
        $carier     = $row['CARRIER'] ?? '-';
        $cod_rez    = $row['RESERVATION_CODE'] ?? $rez_code;
        $locuri = [];
        $preturi = [];
        $coduri_bilet = [];

        if (is_array($ticket) && isset($ticket[0])) {
            foreach ($ticket as $t) {
                $locuri[] = $t['LOC'] ?? '-';
                $preturi[] = $t['PRICE'] ?? '0';
                $coduri_bilet[] = $t['COD'] ?? '-';
            }
        } elseif (is_array($ticket)) {
            $locuri[] = $ticket['LOC'] ?? '-';
            $preturi[] = $ticket['PRICE'] ?? '0';
            $coduri_bilet[] = $ticket['COD'] ?? '-';
        }

        $stari = [
            '1' => 'Nerezervat',
            '2' => 'Rezervat (neachitat)',
            '3' => 'Anulat',
            '4' => 'Achitat'
        ];
        $stare_text = $stari[trim($stare)] ?? $stare;
        $pret_total = 0;
        foreach ($preturi as $p) {
            $pret_total += floatval($p);
        }

        $msg = "📄 <b>Detalii rezervare:</b>\n"
            . "🔑 Cod rezervare: <code>{$cod_rez}</code>\n"
            . "🚌 Rută: <b>$plecare</b> ➡️ <b>$sosire</b>\n"
            . "🕓 Data & Ora: <b>$ora</b>\n"
            . "👤 Pasager: <b>$nume $prenume</b>\n"
            . "📱 Telefon: <code>$telefon</code>\n"
            . "🚏 Transportator: <b>$carier</b>\n"
            . "💺 Locuri: <b>" . implode(', ', $locuri) . "</b>\n"
            . "🎟️ Coduri bilete: <b>" . implode(', ', $coduri_bilet) . "</b>\n"
            . "💵 Preț total: <b>{$pret_total} MDL</b>\n"
            . "📦 Status: <b>$stare_text</b>";

        $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],
            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];
        sendMessage($chat_id, $msg, $kb);
        clearState($chat_id);
    } else {
        $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],
            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];
        sendMessage($chat_id, "❌ Cod invalid sau nu există detalii despre această rezervare.", $kb);
    }
    exit;
}
if (
    ($state['step'] ?? '') === 'awaiting_cancel_code' &&
    !empty($text)
) {
    $rez_code = trim($text);
    if (strlen($rez_code) < 6) {
            $help_text = "⚠️ Codul de rezervare introdus nu pare valid.\nÎncearcă din nou.";
            $kb = [
            [
                ['text' => "❌ Repetă Anularea Bronării",    'callback_data' => 'cancel_reservation'],

            ]
        ];

        sendMessage($chat_id, $help_text, $kb );
        exit;
    }
    $cancel_url = "https://gam2022.unisim-soft.com/widget_una/include/cancel_reservation.php?reservation_code={$rez_code}";
    $cancelResp = post_api($cancel_url, [], true, 'Unisimso', 's0t2025Web');
    $cres = json_decode($cancelResp, true);
    
    if (!empty($cres['type']) && $cres['type'] === 'success') {
       $help_text = "✅ Rezervarea cu codul <code>{$rez_code}</code> a fost anulată cu succes.";
         $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],

            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];
        sendMessage($chat_id, $help_text, $kb);
        clearState($chat_id);
    } else {
         $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],

            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];

        sendMessage($chat_id, "❌ Eroare la anularea rezervării sau cod invalid. Contactează suportul dacă problema persistă." , $kb);
    }
    exit;
}

if (
    ($state['step'] ?? '') === 'awaiting_check_code' &&
    !empty($text)
) {
    $rez_code = trim($text);
    if (strlen($rez_code) < 6) {
         $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],

            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];
        sendMessage($chat_id, "⚠️ Codul de rezervare introdus nu pare valid.\nÎncearcă din nou.", $kb);
        exit;
    }
    $check_url = "https://gam2022.unisim-soft.com/widget_una/include/check_reservation.php?reservation_code={$rez_code}";
    $checkResp = post_api($check_url, [], true, 'Unisimso', 's0ft2025Web');
    $cres = json_decode($checkResp, true);

    if (!empty($cres['type']) && $cres['type'] === 'success') {
        $data     = $cres['data'] ?? [];
        $curse    = $data['curse'] ?? 'necunoscută';
        $ora      = $data['ora'] ?? '-';
        $data_c   = $data['data'] ?? '-';
        $locuri   = $data['locuri'] ?? '-';
        $status   = $data['status'] ?? '-';

        $msg = "📄 <b>Detalii rezervare:</b>\n"
             . "🔑 Cod: <code>{$rez_code}</code>\n"
             . "🚌 Cursa: <b>{$curse}</b>\n"
             . "⏰ Data: <b>{$data_c}</b> | Ora: <b>{$ora}</b>\n"
             . "💺 Locuri: <b>{$locuri}</b>\n"
             . "📦 Status: <b>{$status}</b>";
        sendMessage($chat_id, $msg, [[['text'=>'🏠 Meniu principal','callback_data'=>'main_menu']]], 'HTML');
        clearState($chat_id);
    } else {
         $kb = [
            [
                ['text' => "🏠 Meniu principal",'callback_data' => 'main_menu'],
                ['text' => "📞 Contactează-ne",'callback_data' => 'contact_support'],
            ],
            [
                ['text' => "✅ Verifică o altă Bronare",    'callback_data' => 'check_reservation'],
                ['text' => "🗑️ Anulează o Rezervare",    'callback_data' => 'cancel_reservation'],

            ],
            [
                ['text' => "🔙 Înapoi la Meniu Principal",    'callback_data' => 'main_menu'],
            ]
        ];
        sendMessage($chat_id, "❌ Cod invalid sau nu există detalii despre această rezervare.", $kb);
    }
    exit;
}



if (
    $data && 
    strpos($data, 'fromid_') === 0 && 
    in_array($state['step'], ['from_city_text', 'from_city_pick_bus'])
) {
    $startId = (int)substr($data, 7);
    $response = json_decode(
        file_get_contents('https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all'),
        true
    );
    $startPoints = $response['content'] ?? [];
    $chisinauGari = [
        '318880' => 'GARA SCULENI',
        '318440' => 'GSA GARA de NORD',
        '319502' => 'GARA FEROVIARA GSA',
        '317765' => 'GARA IALOVENI',
        '317345' => 'CHISINAU'
    ];
    $plecare_nume = null;
    foreach ($startPoints as $sp) {
        if ($sp['COD'] == $startId) {
            $plecare_nume = $sp['TITLE'];
            break;
        }
    }
    if (!$plecare_nume && isset($chisinauGari[$startId])) {
        $plecare_nume = $chisinauGari[$startId];
    }
    $state['startPoint'] = $startId;
    $state['last_filter'] = $plecare_nume;
    $state['step'] = 'to_city_text_bus';
    saveState($chat_id, $state);
    if (isset($mid)) {
        deleteMessage($chat_id, $mid);
    }
    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $startId], true,
        'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];
    $popular = [];
    foreach ($dest as $d) {
        $title = mb_strtoupper(removeDiacritics($d['TITLE']), 'UTF-8');
        if (strpos($title, 'BALTI') !== false || strpos($title, 'COMRAT') !== false) {
            $popular[] = ['text' => $d['TITLE'], 'callback_data' => 'last_to_' . $d['TITLE']];
        }
    }
    if (empty($popular) && !empty($dest)) {
        foreach (array_slice($dest, 0, 2) as $d) {
            $popular[] = ['text' => $d['TITLE'], 'callback_data' => 'last_to_' . $d['TITLE']];
        }
    }
    $kb = [];
    if (!empty($popular)) {
        $kb[] = $popular;
    }
    $msg = "📝 Scrie stația de destinație a autobuzului.\nExemplu: Bălți sau Comrat\n\nSau alege stația din căutările anterioare";
    sendMessage($chat_id, $msg, $kb);
    file_put_contents(DEBUG_LOG, "[SELECTAT PLECARE: $plecare_nume | ID: $startId]\n", FILE_APPEND);

    exit;
}




    if ($data && strpos($data, 'last_to_') === 0 && $state['step'] === 'to_city_text_bus') {
        $text_wait = "💪 Încep căutarea\nMă uit după curse, te rog așteaptă poate dura câteva minute...";
        $wait_resp = post_api(API_URL . 'sendMessage', [
            'chat_id'    => $chat_id,
            'text'       => $text_wait,
            'parse_mode' => 'HTML',
        ]);
        $wait_data  = json_decode($wait_resp, true);
        $wait_msgid = $wait_data['result']['message_id'] ?? null;

        $destinatie_aleasa = substr($data, strlen('last_to_'));
        $state['last_filter_dest'] = $destinatie_aleasa; 
        if(isset($mid)) deleteMessage($chat_id, $mid);
        $resp = post_api(
            'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
            ['startPoint' => $state['startPoint']], true,
            'Unisimso', 's0ft2025Web'
        );
        $arr = json_decode($resp, true);
        $dest = $arr['content'] ?? [];
        $dest_id = null;
        foreach ($dest as $d) {
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
        $state['destPointName'] = $destinatie_aleasa;
        $state['step'] = 'awaiting_date';
        saveState($chat_id, $state);
        
        showCursePeZi($chat_id, $state);
            if ($wait_msgid) {
            post_api(API_URL . 'deleteMessage', [
                'chat_id'    => $chat_id,
                'message_id' => $wait_msgid
            ]);
        }
        exit;
    }
if (isset($data) && strpos($data, 'last_from_') === 0 && ($state['step'] ?? '') === 'from_city_text') {
    $text = substr($data, strlen('last_from_'));
    file_put_contents(DEBUG_LOG, "[BTN->TEXT] Buton apăsat last_from_: $text\n", FILE_APPEND);
}
if (isset($text) && ($state['step'] ?? '') === 'from_city_text') {
    file_put_contents(DEBUG_LOG, "[STEP: from_city_text] Text primit: $text\n", FILE_APPEND);
    $caut = mb_strtolower(removeDiacritics(trim($text)), 'UTF-8');
    file_put_contents(DEBUG_LOG, "[NORMALIZAT] Caut: $caut\n", FILE_APPEND);
    if (in_array($caut, ['chisinau', 'chișinău', 'kisina', 'kisinaŭ', 'kisinaŭ'])) {
        file_put_contents(DEBUG_LOG, "[CHIȘINĂU EXCLUSIV] Text exact: $caut\n", FILE_APPEND);

        $chisinauGari = [
            ['COD'=>'318880','TITLE'=>'GARA SCULENI'],
            ['COD'=>'318440','TITLE'=>'GSA GARA de NORD'],
            ['COD'=>'319502','TITLE'=>'GARA FEROVIARA GSA'],
            ['COD'=>'317765','TITLE'=>'GARA IALOVENI'],
            ['COD'=>'317345','TITLE'=>'CHISINAU']
        ];
        $rezultate = [];
        foreach ($chisinauGari as $gara) {
            $rezultate[] = ['text' => "🚍 " . $gara['TITLE'], 'callback_data' => 'fromid_' . $gara['COD']];
        }
        sendMessage($chat_id, "📍 Selectează stația din Chișinău:", array_chunk($rezultate, 2));
        return;
    }
    $url = 'https://gam2022.unisim-soft.com/widget_una/include/getStartPoints.php?org=all';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, 'Unisimso:s0ft2025Web');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerr = curl_error($ch);
    curl_close($ch);

    file_put_contents(DEBUG_LOG, "[DEBUG STARTPOINT API HTTP $httpcode]: $response\n", FILE_APPEND);
    if ($curlerr) {
        file_put_contents(DEBUG_LOG, "[CURL ERROR]: $curlerr\n", FILE_APPEND);
    }

    $data_resp = json_decode($response, true);
    $startPoints = $data_resp['content'] ?? [];

    if (!is_array($startPoints) || count($startPoints) === 0) {
        file_put_contents(DEBUG_LOG, "[EROARE] Nu s-au încărcat stațiile sau lista e goală\n", FILE_APPEND);
        sendMessage($chat_id, "❗️Eroare la încărcarea stațiilor. Încearcă din nou.");
        return;
    }
    $rezultate = [];
    foreach ($startPoints as $statie) {
        $titlu = $statie['TITLE'] ?? '';
        $id    = $statie['COD'] ?? '';
        $titluNorm = mb_strtolower(removeDiacritics($titlu), 'UTF-8');

        if (strpos($titluNorm, $caut) === 0) {
            $rezultate[] = ['text' => "🚍 $titlu", 'callback_data' => 'fromid_' . $id];
        }
    }
    if (in_array(substr($caut, 0, 4), ['chis', 'chi', 'ch', 'c'])) {
        $chisinauGari = [
            ['COD'=>'318880','TITLE'=>'GARA SCULENI'],
            ['COD'=>'318440','TITLE'=>'GSA GARA de NORD'],
            ['COD'=>'319502','TITLE'=>'GARA FEROVIARA GSA'],
            ['COD'=>'317765','TITLE'=>'GARA IALOVENI'],
            ['COD'=>'317345','TITLE'=>'CHISINAU']
        ];
        $existente = array_column($rezultate, 'callback_data');
        foreach ($chisinauGari as $gara) {
            $callback = 'fromid_' . $gara['COD'];
            if (!in_array($callback, $existente)) {
                $rezultate[] = ['text' => "🚍 " . $gara['TITLE'], 'callback_data' => $callback];
            }
        }
    }

    if (count($rezultate) > 0) {
        sendMessage($chat_id, "📍 Rezultate pentru: <b>$text</b>\nSelectează o stație:", array_chunk($rezultate, 2));
    } else {
        sendMessage($chat_id, "❗️Nicio stație găsită care începe cu: <b>$text</b>");
    }
    return;
}
if (isset($text) && ($state['step'] ?? '') === 'to_city_text_bus') {
    file_put_contents(DEBUG_LOG, "[STEP: to_city_text_bus] Text primit: $text\n", FILE_APPEND);
    $caut = mb_strtolower(removeDiacritics(trim($text)), 'UTF-8');
    file_put_contents(DEBUG_LOG, "[NORMALIZAT] Caut: $caut\n", FILE_APPEND);

    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $state['startPoint']], true,
        'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $destinatii = $arr['content'] ?? [];
    $rezultate = [];
    foreach ($destinatii as $destPoint) {
        $titlu = $destPoint['TITLE'] ?? '';
        $id    = $destPoint['COD'] ?? '';
        $titluNorm = mb_strtolower(removeDiacritics($titlu), 'UTF-8');

        if (strpos($titluNorm, $caut) === 0) {
            $rezultate[] = ['text' => "🏁 $titlu", 'callback_data' => 'last_to_' . $titlu];
        }
    }
    if (count($rezultate) > 0) {
        sendMessage($chat_id, "📍 Rezultate pentru destinația <b>$text</b>:", array_chunk($rezultate, 2));
    } else {
        sendMessage($chat_id, "❌ Nu am găsit destinații care încep cu: <b>$text</b>");
    }
    return;
}
if (isset($text) && $state['step'] === 'to_city_text') {
    $state['last_filter_dest'] = $text;
    $cauta = mb_strtolower(removeDiacritics($text), 'UTF-8');
    $resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/getStations.php?org=all&api_type=bus',
        ['startPoint' => $state['startPoint']], true,
        'Unisimso', 's0ft2025Web'
    );
    $arr = json_decode($resp, true);
    $dest = $arr['content'] ?? [];
    function fuzzyMatch($input, $list, $threshold = 0.5) {
        $results = [];
        $input_norm = normalize($input);
        foreach ($list as $item) {
            $title_norm = normalize($item['TITLE']);
            $lev = levenshtein($input_norm, $title_norm);
            $max_len = max(strlen($input_norm), strlen($title_norm));
            $similarity = 1 - ($lev / $max_len);
            if ($similarity >= $threshold || strpos($title_norm, $input_norm) !== false) {
                $results[] = ['station' => $item, 'score' => $similarity];
            }
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 5);
    }
    $matches = fuzzyMatch($text, $dest);
    if (empty($matches)) {
        sendMessage($chat_id, "❌ Nu am găsit destinația '$text'. Încearcă altceva.");
        exit;
    }
    if (count($matches) === 1) {
        $d = $matches[0]['station'];
        $dest_id = $d['COD'];
        $state['destPoint'] = $dest_id;
        $state['destPointName'] = $d['TITLE'];
        $state['step'] = 'awaiting_date';
        saveState($chat_id, $state);
        showCursePeZi($chat_id, $state);
        exit;
    }
    $kb = [];
    foreach ($matches as $m) {
        $d = $m['station'];
        $kb[] = [['text' => $d['TITLE'], 'callback_data' => 'last_to_' . $d['TITLE']]];
    }
    sendMessage($chat_id, "Alege destinația corectă:", $kb);
    exit;
}
    if ($data && $state['step'] === 'to_city_pick_bus' && strpos($data, 'toid_') === 0) {
        
        $to_id = substr($data, strlen('toid_'));
        $state['dest_id'] = $to_id;

        $url = "https://gam2022.unisim-soft.com/widget_una/include/getRoutes.php"; 
        $response = file_get_contents($url);


        preg_match_all(
            '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*' .
            '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*' .
            '<td[^>]*>([^<]+)<\/td>\s*' .
            '<td[^>]*>([^<]+)<\/td>\s*' .
            '<td[^>]*>([^<]+)<\/td>\s*' .
            '<td[^>]*>([^<]+)<\/td>\s*' .
            '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/',
            $response, $matches, PREG_SET_ORDER
        );

        $plecare_user = normalize($state['last_filter']);
        $destinatie_user = normalize($state['last_filter_dest']);
        $curse_gasite = [];
        foreach ($matches as $match) {
            if (preg_match('/^(.+?)\s*=>\s*(.+?)\s+\d{2}:\d{2}/iu', $match[3], $cities)) {
                $plecare_curenta = normalize($cities[1]);
                $destinatie_curenta = normalize($cities[2]);
                if ($plecare_curenta === $plecare_user && $destinatie_curenta === $destinatie_user) {
                    $curse_gasite[] = $match;
                }
            }
        }
        if (empty($curse_gasite)) {
            sendMessage($chat_id, "❌ Nu există curse valabile pentru această rută ($plecare_user → $destinatie_user)!");
            return;
        }

        $keyboard = [];
        foreach ($curse_gasite as $cursa) {
            $label = "{$cursa[3]} | {$cursa[4]} {$cursa[5]} | {$cursa[7]}";
            $keyboard[] = [
                ['text' => $label, 'callback_data' => 'book_' . $cursa[1]]
            ];
        }
        sendMessage($chat_id, "🚌 Selectează o cursă disponibilă:", [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);

    }
if (
    isset($data) &&
    strpos($data, 'date_') === 0 &&
    ($state['step'] ?? '') === 'awaiting_date'
) {
    $dm = substr($data, 5);
    list($day, $mon) = explode('.', $dm, 2);
    $day = str_pad($day, 2, '0', STR_PAD_LEFT);
    $mon = str_pad($mon, 2, '0', STR_PAD_LEFT);
    $sel = date('Y-') . "$mon-$day";
    $progressTotal = 8;
    $loading = sendMessageReturn($chat_id, "🔄 Caut curse pentru $day.$mon...\n[⬜⬜⬜⬜⬜⬜⬜⬜]");
    $loading_mid = $loading['message_id'];
    for ($i = 0; $i < $progressTotal - 1; $i++) {
        usleep(80000);
        $progressCount = $i + 1;
        $progressFilled = str_repeat('🟦', $progressCount);
        $progressEmpty  = str_repeat('⬜', $progressTotal - $progressCount);
        $progressText = "🔄 Caut curse pentru $day.$mon...\n[{$progressFilled}{$progressEmpty}]";
        editMessageText($chat_id, $loading_mid, $progressText);
    }

    $params = [
        'startPoint'   => $state['startPoint'],
        'station'      => $state['destPoint'],
        'data'         => $sel,
        'connect_type' => 'web',
        'org'          => 'all',
        'ro'           => '',
        'api_type'     => ''
    ];

    $resp = post_api(
        "https://gam2022.unisim-soft.com/widget_una/include/do_search.php?",
        $params, true, 'Unisimso', 's0ft2025Web'
    );
    $progressText = "🔄 Caut curse pentru $day.$mon...\n[🟦🟦🟦🟦🟦🟦🟦🟦]";
    editMessageText($chat_id, $loading_mid, $progressText);
    usleep(150000);
    preg_match_all(
        '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*'.
        '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/is',
        $resp, $all, PREG_SET_ORDER
    );
    $toNorm = normalize(explode(' ', $state['last_filter_dest'])[0]);
    $matches = [];
    foreach ($all as $r) {
        $parts = explode('->', str_replace('=>', '->', $r[3]));
        $dst = isset($parts[1]) ? normalize(explode(' ', trim($parts[1]))[0]) : '';
        if ($dst === $toNorm) {
            $matches[] = $r;
        }
    }
    if (empty($matches)) {
        sendMessage(
            $chat_id,
            "🚫 Nu există curse filtrate pentru {$day}.{$mon}.\nDoriți să vedeți toate cursele (general)?",
            [['text'=>'Vezi curse generale','callback_data'=>'show_general']]
        );
        exit;
    }
    $state['curse_data'] = [];
    foreach ($matches as $i => $m) {
        $routeCode  = $m[1];
        $dateTd     = $m[2];
        $info       = $m[3];
        $oraPlecare = $m[5];
        $tarif      = $m[8];
        $distKm     = floatval(str_replace(',', '.', $m[7]));

        $placesResp = post_api(
            "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php",
            ['code'=>$routeCode, 'data'=>$dateTd, 'station'=>$state['destPoint']],
            true, 'Unisimso', 's0ft2025Web'
        );
        $jp = json_decode($placesResp, true);
        $freeCount = 0;
        if (!empty($jp['content']['place'])) {
            foreach ($jp['content']['place'] as $p) {
                if ($p['occupied'] === "0") $freeCount++;
            }
        }

        $minutes = intval($distKm / 60 * 60);
        $dt = DateTime::createFromFormat('H:i d.m.Y', "{$oraPlecare} {$dateTd}");
        $sosire = $dt ? $dt->modify("+{$minutes} minutes")->format('H:i') : '—';
        $state['curse_data'][$i] = [
            'code'           => $routeCode,
            'data_td'        => $dateTd,
            'ora'            => $oraPlecare,
            'plecare'        => trim($info),
            'dist'           => $distKm,
            'tarif'          => $tarif,
            'locuri_libere'  => $freeCount,
            'sosire'         => $sosire,
        ];
    }
    saveState($chat_id, $state);

    $txt = "";
    foreach ($state['curse_data'] as $idx => $d) {
        $txt .= "🚌 <b>{$d['plecare']}</b>\n";
        $txt .= "⏰ Plecare: {$d['ora']}, {$d['data_td']}\n";
        $txt .= "💳 Preț: {$d['tarif']}\n";
        $txt .= "💺 Locuri libere: {$d['locuri_libere']}\n";
        $txt .= "🔗 /Cumpara_bilet_{$idx}\n";
        $txt .= "──────────────\n\n";
    }
    deleteMessage($chat_id, $loading_mid);

    sendLongMessage($chat_id, $txt);
    exit;
}
if (
    isset($text) &&
    ($state['step'] ?? '') === 'awaiting_date'
) {
    if (!preg_match('/^(\d{1,2})\.(\d{2})/u', $text, $m)) {
        sendMessage($chat_id, "Format invalid! Folosește <b>zi.lună</b> (ex: 28.06)");
        exit;
    }
    $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
    $mon = str_pad($m[2], 2, '0', STR_PAD_LEFT);
    $sel = date('Y-') . "$mon-$day";
    $progressTotal = 8;
    $loading = sendMessageReturn($chat_id, "🔄 Caut curse pentru $day.$mon...\n[⬜⬜⬜⬜⬜⬜⬜⬜]");
    $loading_mid = $loading['message_id'];
    for ($i = 0; $i < $progressTotal - 1; $i++) {
        usleep(80000);
        $progressCount = $i + 1;
        $progressFilled = str_repeat('🟦', $progressCount);
        $progressEmpty  = str_repeat('⬜', $progressTotal - $progressCount);
        $progressText = "🔄 Caut curse pentru $day.$mon...\n[{$progressFilled}{$progressEmpty}]";
        editMessageText($chat_id, $loading_mid, $progressText);
    }
    $params = [
        'startPoint'   => $state['startPoint'],
        'station'      => $state['destPoint'],
        'data'         => $sel,
        'connect_type' => 'web',
        'org'          => 'all',
        'ro'           => '',
        'api_type'     => ''
    ];
    $resp = post_api(
        "https://gam2022.unisim-soft.com/widget_una/include/do_search.php?",
        $params, true, 'Unisimso', 's0ft2025Web'
    );
    $progressText = "🔄 Caut curse pentru $day.$mon...\n[🟦🟦🟦🟦🟦🟦🟦🟦]";
    editMessageText($chat_id, $loading_mid, $progressText);
    usleep(150000);

    preg_match_all(
        '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*'.
        '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/is',
        $resp, $all, PREG_SET_ORDER
    );
    $toNorm = normalize(explode(' ', $state['last_filter_dest'])[0]);
    $matches = [];
    foreach ($all as $r) {
        $parts = explode('->', str_replace('=>', '->', $r[3]));
        $dst = isset($parts[1]) ? normalize(explode(' ', trim($parts[1]))[0]) : '';
        if ($dst === $toNorm) {
            $matches[] = $r;
        }
    }
    if (empty($matches)) {
        sendMessage(
            $chat_id,
            "🚫 Nu există curse filtrate pentru {$day}.{$mon}.\nDoriți să vedeți toate cursele (general)?",
            [['text'=>'Vezi curse generale','callback_data'=>'show_general']]
        );
        exit;
    }

    $state['curse_data'] = [];
    foreach ($matches as $i => $m) {
        $routeCode  = $m[1];
        $dateTd     = $m[2];
        $info       = $m[3];
        $oraPlecare = $m[5];
        $tarif      = $m[8];
        $distKm     = floatval(str_replace(',', '.', $m[7]));

        $placesResp = post_api(
            "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php",
            ['code'=>$routeCode, 'data'=>$dateTd, 'station'=>$state['destPoint']],
            true, 'Unisimso', 's0ft2025Web'
        );
        $jp = json_decode($placesResp, true);
        $freeCount = 0;
        if (!empty($jp['content']['place'])) {
            foreach ($jp['content']['place'] as $p) {
                if ($p['occupied'] === "0") $freeCount++;
            }
        }

        $minutes = intval($distKm / 60 * 60);
        $dt = DateTime::createFromFormat('H:i d.m.Y', "{$oraPlecare} {$dateTd}");
        $sosire = $dt ? $dt->modify("+{$minutes} minutes")->format('H:i') : '—';

        $state['curse_data'][$i] = [
            'code'           => $routeCode,
            'data_td'        => $dateTd,
            'ora'            => $oraPlecare,
            'plecare'        => trim($info),
            'dist'           => $distKm,
            'tarif'          => $tarif,
            'locuri_libere'  => $freeCount,
            'sosire'         => $sosire,
        ];
    }
    saveState($chat_id, $state);

    $txt = "";
    foreach ($state['curse_data'] as $idx => $d) {
        $txt .= "🚌 <b>{$d['plecare']}</b>\n";
        $txt .= "⏰ Plecare: {$d['ora']}, {$d['data_td']}\n";
        $txt .= "💳 Preț: {$d['tarif']}\n";
        $txt .= "💺 Locuri libere: {$d['locuri_libere']}\n";
        $txt .= "🔗 /Cumpara_bilet_{$idx}\n";
        $txt .= "──────────────\n\n";
    }
    deleteMessage($chat_id, $loading_mid);
    sendLongMessage($chat_id, $txt);
    exit;
}
if (
    isset($data) &&
    $data === 'show_general' &&
    ($state['step'] ?? '') === 'awaiting_date'
) {
    if (isset($mid)) deleteMessage($chat_id, $mid);
    $loading = sendMessageReturn($chat_id, "🔄 Caut curse generale...\nProgress: [⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜]");
    $loading_mid = $loading['message_id'];
    $progressTotal = 10;
    $initial_steps = 8;
    for ($i = 0; $i < $initial_steps; $i++) {
        usleep(120000);
        $progressCount = $i + 1;
        $progressFilled = str_repeat('🟦', $progressCount);
        $progressEmpty  = str_repeat('⬜', $progressTotal - $progressCount);
        $progressText = "🔄 Caut curse generale...\nProgress: [{$progressFilled}{$progressEmpty}]";
        editMessageText($chat_id, $loading_mid, $progressText);
    }
    $selected_date = $state['selected_date'] ?? date('Y-m-d');
    $params_general = [
        'startPoint'   => $state['startPoint'],
        'station'      => $state['destPoint'],
        'data'         => $selected_date,
        'connect_type' => 'web',
        'org'          => 'all',
        'ro'           => '',
        'api_type'     => ''
    ];
    $response_general = post_api(
        "https://gam2022.unisim-soft.com/widget_una/include/do_search.php?",
        $params_general, true, 'Unisimso', 's0ft2025Web'
    );
    preg_match_all(
        '/<td><a class="show_info" route="([0-9]+)" data="([^"]+)" href="#">.*?<\/a><\/td>\s*'.
        '<td[^>]*class="t-left"[^>]*>\s*<a[^>]*>([^<]+)<\/a><\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*>([^<]+)<\/td>\s*'.
        '<td[^>]*class="text-nowrap"[^>]*>([^<]+)<\/td>/is',
        $response_general, $matches_general, PREG_SET_ORDER
    );
    if (empty($matches_general)) {
        for ($i = $initial_steps; $i < $progressTotal; $i++) {
            usleep(70000);
            $progressCount = $i + 1;
            $progressFilled = str_repeat('🟦', $progressCount);
            $progressEmpty  = str_repeat('⬜', $progressTotal - $progressCount);
            $progressText = "🔄 Caut curse generale...\nProgress: [{$progressFilled}{$progressEmpty}]";
            editMessageText($chat_id, $loading_mid, $progressText);
        }
        usleep(200000);
        deleteMessage($chat_id, $loading_mid);
        sendMessage(
            $chat_id,
            "🚫 Nu există curse disponibile pentru data <b>" . date('d.m', strtotime($selected_date))
            . "</b> pe ruta <b>{$state['last_filter']} → {$state['last_filter_dest']}</b>."
        );
        $state['step'] = 'awaiting_date';
        saveState($chat_id, $state);
        exit;
    }

    $state['curse_data'] = [];
    foreach ($matches_general as $i => $m) {
        $routeCode  = $m[1];
        $dateTd     = $m[2];
        $info       = $m[3];
        $oraPlecare = $m[5];
        $tarif      = $m[8];
        $distKm     = floatval(str_replace(',', '.', $m[7]));

        $placesResp = post_api(
            "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php",
            ['code'=>$routeCode, 'data'=>$dateTd, 'station'=>$state['destPoint']],
            true, 'Unisimso', 's0ft2025Web'
        );
        $jp = json_decode($placesResp, true);
        $freeCount = 0;
        if (!empty($jp['content']['place'])) {
            foreach ($jp['content']['place'] as $p) {
                if ($p['occupied'] === "0") $freeCount++;
            }
        }

        $minutes = intval($distKm / 60 * 60);
        $dt = DateTime::createFromFormat('H:i d.m.Y', "{$oraPlecare} {$dateTd}");
        $sosire = $dt ? $dt->modify("+{$minutes} minutes")->format('H:i') : '—';

        $state['curse_data'][$i] = [
            'code'           => $routeCode,
            'data_td'        => $dateTd,
            'ora'            => $oraPlecare,
            'plecare'        => trim($info),
            'dist'           => $distKm,
            'tarif'          => $tarif,
            'locuri_libere'  => $freeCount,
            'sosire'         => $sosire,
        ];
    }
    saveState($chat_id, $state);

    $txt = "";
    foreach ($state['curse_data'] as $idx => $d) {
        $txt .= "🚌 <b>{$d['plecare']}</b>\n";
        $txt .= "⏰ Plecare: {$d['ora']}, {$d['data_td']}\n";
        $txt .= "💳 Preț: {$d['tarif']}\n";
        $txt .= "💺 Locuri libere: {$d['locuri_libere']}\n";
        $txt .= "🔗 /Cumpara_bilet_{$idx}\n";
        $txt .= "──────────────\n\n";
    }
    for ($i = $initial_steps; $i < $progressTotal; $i++) {
        usleep(70000);
        $progressCount = $i + 1;
        $progressFilled = str_repeat('🟦', $progressCount);
        $progressEmpty  = str_repeat('⬜', $progressTotal - $progressCount);
        $progressText = "🔄 Caut curse generale...\nProgress: [{$progressFilled}{$progressEmpty}]";
        editMessageText($chat_id, $loading_mid, $progressText);
    }
    usleep(200000);
    deleteMessage($chat_id, $loading_mid);
    sendLongMessage($chat_id, $txt);
    $state['step'] = 'choose_cursa_list';
    saveState($chat_id, $state);
    exit;
}
    if($data && $state['step']=='choose_cursa' && strpos($data, 'cursa_')===0){
        $idx = (int)substr($data, 6);
        $detalii = $state['curse_data'][$idx] ?? null;
        if(!$detalii){
            sendMessage($chat_id, "Eroare la selectarea cursei.");
            clearState($chat_id);
            exit;
        }
        if (isset($mid)) deleteMessage($chat_id, $mid);
        $state['selectat_cursa'] = $idx;
        $state['places'] = 1;
        $state['step']='choose_places';
        $code_cursa = $detalii['code'] ?? $detalii['cod'] ?? null;
        $detalii['locuri_libere'] = $locuri_libere;
        $state['curse_data'][$idx] = $detalii;
        saveState($chat_id, $state);
        $date_cursa = $detalii['data'];
        $station    = $state['destPoint'];
        $locuri_libere = null; 
        $locuri_libere = null;
        if ($code_cursa && $date_cursa && $station) {
            $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$detalii['code']}&data={$detalii['data']}&station={$state['destPoint']}";
            $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
            if ($resp) {
                $json = json_decode($resp, true);
                if (isset($json['content']['place'])) {
                    $locuri_libere = 1;
                    foreach ($json['content']['place'] as $p) {
                        if ($p['occupied'] == "0") $locuri_libere++;
                    }
                    $state['locuri_libere'] = $locuri_libere;
                    $places_info = [];
                    foreach ($json['content']['place'] as $place) {
                        $places_info[] = "Nr: {$place['place_nr']} => " . ($place['occupied'] == "1" ? "liber" : "ocupat");
                    }
                    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API DEBUG]\n".implode("\n", $places_info)."\n", FILE_APPEND);
                    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API LIBERE] $locuri_libere din ".count($json['content']['place'])." locuri\n", FILE_APPEND);
                
                }
            }
        }
        $rez = sendMessageReturn(
        $chat_id,
        formatDetalii($detalii, $state['places'] ?? 0, $locuri_libere),
        makePlacesKeyboard($state['places'] ?? 0)
        );
        $state['places_msgid'] = $rez['message_id'];
        saveState($chat_id, $state);
        exit;
    }
    if (($data === 'plus_places' || $data === 'minus_places') && $state['step'] === 'choose_places') {
        $n = $state['places'] ?? 1;
        if ($data === 'plus_places') $n = min(5, $n + 1);
        if ($data === 'minus_places') $n = max(1, $n - 1);
        $state['places'] = $n;
        saveState($chat_id, $state);
        $idx = $state['selectat_cursa'];
        $detalii = $state['curse_data'][$idx] ?? null;
        $locuri_libere = $state['locuri_libere'] ?? null;
        $code_cursa = $detalii['code'] ?? $detalii['cod'] ?? null;
        $date_cursa = $detalii['data'];
        $station    = $state['destPoint'];
        if ($code_cursa && $date_cursa && $station) {
            $url = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php?code={$detalii['code']}&data={$detalii['data']}&station={$state['destPoint']}";
            $resp = post_api($url, [], true, 'Unisimso', 's0ft2025Web');
            if ($resp) {
                $json = json_decode($resp, true);
                if (isset($json['content']['place'])) {
                    $locuri_libere = 1;
                    foreach ($json['content']['place'] as $p) {
                        if ($p['occupied'] == "0") $locuri_libere++;
                    }
                    $state['locuri_libere'] = $locuri_libere;
                    $places_info = [];
                    foreach ($json['content']['place'] as $place) {
                        $places_info[] = "Nr: {$place['place_nr']} => " . ($place['occupied'] == "1" ? "liber" : "ocupat");
                    }
                    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API DEBUG]\n".implode("\n", $places_info)."\n", FILE_APPEND);
                    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') . "[LOCURI API LIBERE] $locuri_libere din ".count($json['content']['place'])." locuri\n", FILE_APPEND);
                }
            }
        }
        editMessageText($chat_id, $state['places_msgid'], formatDetalii($detalii, $n, $locuri_libere), makePlacesKeyboard($n));
        exit;
    }
    if($data === 'final_conf' && $state['step'] == 'choose_places'){
        $idx = $state['selectat_cursa'];
        $detalii = $state['curse_data'][$idx] ?? [];
        $nr_loc = $state['places'] ?? 1;
        $state['step'] = 'input_passenger_phone';
        $state['input_pass_idx'] = 0;
        $state['input_pass_data'] = [];
        saveState($chat_id, $state);
        sendMessage($chat_id, "✍️ Scrieți, vă rog, numărul de telefon pentru pasagerul de pe locul 1. Este necesar pentru informarea privind călătoria.");
        exit;
    }
    if($state['step'] == 'input_passenger_phone' && isset($text)){
        $idx = $state['input_pass_idx'];
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
        $state['step'] = 'input_passenger_email';
        saveState($chat_id, $state);
        sendMessage($chat_id, "✉️ Scrieți emailul pentru locul ".($idx+1).".\nExemplu: exemplu@email.com");
        exit;
    }
    if($state['step'] == 'input_passenger_email' && isset($text)){
        $idx = $state['input_pass_idx'];
        if(!filter_var($text, FILTER_VALIDATE_EMAIL)){
            sendMessage($chat_id, "⚠️ Email invalid! Te rugăm să introduci un email valid (ex: exemplu@email.com).");
            exit;
        }
        $state['input_pass_data'][$idx]['email'] = trim($text);
        $state['step'] = 'awaiting_payment';
        saveState($chat_id, $state);
        sendMessage($chat_id, "✅ Datele au fost salvate. Acum continuă cu plata biletului, apasă pe metoda de plată dorită!");
        $keyboard = [
            [
                ['text'=>'💳 Plată online (MIA/QR)','callback_data'=>'pay_qiwi'],
                ['text'=>'💳 Plată cu card','callback_data'=>'pay_card'],
            ],
            [
                ['text'=>'🏦 Plată cash la casa','callback_data'=>'pay_cash_casa'],
                ['text'=>'💸 Plată cash (QIWI)','callback_data'=>'pay_cash'],
            ],
            [['text'=>'❌ Anulează','callback_data'=>'cancel']]
        ];
        $payload = [
            'chat_id' => $chat_id,
            'text' => "Alege modalitatea de plată pentru a continua rezervarea:",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ];
        $ch = curl_init(API_URL.'sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);

        exit;
    }
    if($data==='pay_qiwi'){
        set_time_limit(180);
        ignore_user_abort(true);
        unset(
            $state['qr_msgid'],
            $state['qr_time'],
            $state['text_msgid1'],
            $state['text_msgid2'],
            $state['qr_merchant'],
            $state['qiwi_token']
        );
        saveState($chat_id, $state);
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
        $merchantID = "LNM_DYN_" . date('YmdHisv');
        $detalii = $state['curse_data'][$state['selectat_cursa']];
        $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 1);
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
        $msg1 = sendMessageReturn($chat_id, "Scanează QR-ul sau accesează linkul de mai jos pentru plată. Valabil 2 minute:\n{$qr['text']}");
        $textMsgId1 = $msg1['message_id'] ?? null;
        $img = base64_decode($qr['image']);
        $tmp = sys_get_temp_dir()."/qr_{$chat_id}.png";
        file_put_contents($tmp, $img);
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
        $msg2 = sendMessageReturn($chat_id, "Atenție! Dacă nu achiți în 2 minute, acest cod va expira și mesajul se va șterge automat.");
        $textMsgId2 = $msg2['message_id'] ?? null;
        $state['qr_msgid'] = $photoMessageId;
        $state['qr_time'] = time();
        $state['text_msgid1'] = $textMsgId1;
        $state['text_msgid2'] = $textMsgId2;
        $state['qr_merchant'] = $merchantID;
        $state['qiwi_token'] = $token;
        saveState($chat_id, $state);
        $timeout = 120;
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
                foreach(['qr_msgid','text_msgid1','text_msgid2'] as $k)
                    if(isset($state[$k])) deleteMessage($chat_id, $state[$k]);
                sendMessage($chat_id, "✅ Plata a fost efectuată cu succes!\nRezervarea ta este confirmată.");
                file_put_contents(DEBUG_LOG, "[DEBUG][PAID] Mesaj de confirmare trimis!\n", FILE_APPEND);
                $nr_locuri = $state['places'] ?? 1;
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
                                if ($p['occupied'] == "1") {
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
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') .
                "[DEBUG][pay_qiwi] Locuri rezervate înainte de saveState: " . implode(', ', $locuri_rezervate) . "\n",
                FILE_APPEND
            );
                $state['locuri_rezervate'] = $locuri_rezervate;
                file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') .
    "[DEBUG][pay_qiwi] Locuri rezervate înainte de saveState: " . implode(', ', $locuri_rezervate) . "\n",
    FILE_APPEND
    );
                saveState($chat_id, $state);
                require_once(__DIR__.'/fpdf/fpdf.php');
                $pdf = new FPDF('P', 'mm', [80, 120]);
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 8, 'Bilet / Bon pentru cursa', 0, 1, 'C');
                $pdf->Ln(1);
                $pdf->Cell(0, 7, 'Ruta: '.$detalii['plecare'], 0, 1);
                $pdf->Cell(0, 7, 'Data: '.$detalii['data_td'].' Ora: '.$detalii['ora'], 0, 1);
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
                function rezervareBilete($detalii, $locuri_rezervate, $state, $prenume = 'test', $nume = 'test', $telefon = '+37311111111', $email = 'aciobanu@unisim-soft.com') {
                    $url = "https://gam2022.unisim-soft.com/widget_una/include/do_search.php";                
                    file_put_contents(DEBUG_LOG, date('[Y-m-d H:i:s] ') .
                        "[DEBUG][rezervareBilete] Intrare params:\n" .
                        "route={$detalii['code']}, data={$detalii['data_td']}, locuri=[" . implode(',', $locuri_rezervate) . "], " .
                        "startPoint={$state['startPoint']}, station={$state['destPoint']}, prenume={$prenume}, nume={$nume}, phone={$telefon}, email={$email}\n",
                        FILE_APPEND
                    );
                    $params = [
                        'route'      => $detalii['code'],
                        'data'       => $detalii['data'],
                        'biletcount' => count($locuri_rezervate) ?: 1,
                        'locuri'     => implode(',', $locuri_rezervate),
                        'station'    => $state['destPoint'],
                        'startPoint' => $state['startPoint'],
                        'RouteCode'  => $detalii['code'],
                        'first_name' => $prenume,
                        'last_name'  => $nume,
                        'phone'      => $telefon,
                        'email'      => $email,
                    ];            
                    $resp = post_api($url, $params, true, 'Unisimso', 's0ft2025Web');
                    $json = json_decode($resp, true);
                    return $resp;
                }
                $rez = json_decode($response_rezervare, true);
                if(isset($rez['type']) && $rez['type'] === 'success') {
                    sendMessage($chat_id, "✔️ Locurile tale au fost bornate cu succes în sistem. Vezi biletul PDF trimis.");
                } else {
                    sendMessage($chat_id, "⚠️ Plata a fost acceptată, dar nu am reușit să bornezi automat locurile. Contactează suport dacă întâmpini probleme.");
                }
                clearState($chat_id);
                $paid = true;
                break;
            }
        }
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
        $state['qr_active'] = false;
        saveState($chat_id, $state);
        exit; 
    }
    }
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
        $amount = number_format($tarif * $nr_loc, 2, '.', '');
        $clientName = $state['input_pass_data'][0]['name'] ?? 'Pasager';
        $email      = $state['input_pass_data'][0]['email'] ?? 'nedefinit@bilete.md';
        $phone      = $state['input_pass_data'][0]['phone'] ?? '069000000';
        $orderId = uniqid("order_");
        $body = [
            "amount"      => $amount,
            "currency"    => "MDL",
            "clientIp"    => $_SERVER['REMOTE_ADDR'] ?? "127.0.0.1",
            "language"    => "ro",
            "description" => "Plata bilet autogara",
            "clientName"  => $clientName,
            "email"       => $email,
            "phone"       => $phone,
            "orderId"     => $orderId,
                "callbackUrl" => "https://exemplu.ro/maib_webhook.php",
                "okUrl" => "https://api.unisim-soft.com/telegram/botbiletegmd/maib_complete.php?chat_id={$chat_id}&payId={$payId}",
                "failUrl"     => "https://exemplu.ro/maib_fail.php?chat_id={$chat_id}"
        ];
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
            $state['maib_payId'] = $payId;
            $state['maib_orderId'] = $orderId;
            $state['maib_plata_in_asteptare'] = true;
            saveState($chat_id, $state);
            sendMessage($chat_id, 
                "Apasă butonul de mai jos pentru a achita online biletul cu cardul bancar MAIB.
                Vei fi redirecționat pe pagina oficială MAIB.",
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
    if ($data === 'pay_card_confirm' && !empty($state['maib_payId'])) {
        $now = time();
        if (isset($state['last_check_pay']) && $now - $state['last_check_pay'] < 5) {
            sendMessage($chat_id, "Te rugăm să mai aștepți câteva secunde înainte de a verifica din nou plata.");
            exit;
        }
        $state['last_check_pay'] = $now;
        saveState($chat_id, $state);
        $accessToken = maib_get_access_token();
        if (!$accessToken) {
            sendMessage($chat_id, "❌ Eroare: nu am putut obține token-ul MAIB!");
            exit;
        }
        $body = ['payId' => $state['maib_payId']];
        $ch = curl_init("https://api.maibmerchants.md/v1/complete");
        curl_setopt_array($ch, [
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($result, true);
        if (!empty($resp['result']['status']) && $resp['result']['status'] === 'OK') {
            sendMessage($chat_id, "✅ Plata a fost confirmată cu succes! Încep rezervarea locurilor…");
            $detalii    = $state['curse_data'][$state['selectat_cursa']];
            $code       = $detalii['code'];
            $date_cursa = $detalii['data_td'];       
            $station    = $state['destPoint'];
            $startPoint = $state['startPoint'];
            $nr_locuri  = $state['places'] ?? 1;
            $url_get      = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php"
                        . "?code={$code}&data={$date_cursa}&station={$station}";
            $resp_inainte = post_api($url_get, [], true, 'Unisimso','s0ft2025Web');
            $jp = json_decode($resp_inainte, true);
            $locuri_rez = [];
            if (!empty($jp['content']['place'])) {
                foreach ($jp['content']['place'] as $p) {
                    if ($p['occupied'] === "0") {
                        $locuri_rez[] = $p['place_nr'];
                        if (count($locuri_rez) >= $nr_locuri) break;
                    }
                }
            }
            if (count($locuri_rez) < $nr_locuri) {
                sendMessage($chat_id, "❌ Rezervarea a eșuat: Nu sunt suficiente locuri libere!");
                clearState($chat_id);
                exit;
            }
            $input = $state['input_pass_data'][0];
            list($first,$last) = array_pad(explode(' ', trim($input['name']),2), 2, '');
            $params = [
                'route'      => $code,
                'data'       => $date_cursa,
                'biletcount'=> $nr_locuri,
                'locuri'     => implode(',', $locuri_rez),
                'station'    => $station,
                'startPoint'=> $startPoint,
                'first_name'=> $first,
                'last_name' => $last,
                'phone'     => $input['phone'],
                'email'     => $input['email']
            ];
            $resp_bron = post_api(
                "https://gam2022.unisim-soft.com/widget_una/include/reserve.php",
                $params, true, 'Unisimso','s0ft2025Web'
            );
            $j = json_decode($resp_bron, true);
            if (empty($j['type']) || $j['type'] !== 'success') {
                $err = $j['error'] ?? 'Eroare necunoscută';
                sendMessage($chat_id, "❌ Rezervarea a eșuat: $err");
                clearState($chat_id);
                exit;
            }
            $state['locuri_rezervate']   = $locuri_rez;
            $state['reservation_code']   = $j['reservation_code'];
            saveState($chat_id, $state);
            require_once(__DIR__.'/fpdf/fpdf.php');
            $pdf = new FPDF('P','mm',[80,120]);
            $pdf->AddPage();
            $pdf->SetFont('Arial','',10);
            $pdf->Cell(0,8,'Bilet / Bon pentru cursa',0,1,'C');
            $pdf->Ln(2);
            $pdf->Cell(0,7,'Ruta: '.$detalii['plecare'],0,1);
            $pdf->Cell(0,7,'Data: '.$date_cursa.' Ora: '.$detalii['ora'],0,1);
            $pdf->Cell(0,7,'Locuri: '.implode(', ',$locuri_rez),0,1);
            $pdf->Ln(4);
            $pdf->Cell(0,6,'Vă dorim drum bun!',0,1,'C');
            $tmp = sys_get_temp_dir()."/bilet_{$chat_id}.pdf";
            $pdf->Output('F',$tmp);
            $cfile = new CURLFile($tmp,'application/pdf','Bilet.pdf');
            $ch2 = curl_init(API_URL.'sendDocument');
            curl_setopt_array($ch2, [
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => [
                    'chat_id'  => $chat_id,
                    'caption'  => 'Biletul tău electronic',
                    'document' => $cfile
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            curl_exec($ch2);
            curl_close($ch2);
            unlink($tmp);
            $buttons = [
                [
                    ['text'=>'❌ Anulează rezervarea','callback_data'=>'cancel_paid'],
                    ['text'=>'🔄 Retrimite bon','callback_data'=>'re_emit_bon']
                ],
                [
                    ['text'=>'🏠 Meniu principal','callback_data'=>'main_menu']
                ]
            ];
            sendMessage(
                $chat_id,
                "✅ Rezervarea a fost confirmată! Cod rezervare: <b>{$state['reservation_code']}</b>\n\nDacă dorești să anulezi rezervarea sau să retrimiți bonul, apasă unul din butoanele de mai jos.",
                $buttons
            );

            exit;
        } else {
            $retryKb = [
                [['text'=>'🔄 Verifică din nou','callback_data'=>'pay_card_confirm']],
                [['text'=>'🔙 Revin la meniu','callback_data'=>'main_menu']]
            ];
            sendMessage(
                $chat_id,
                "⚠️ Plata nu a fost confirmată încă. Încearcă din nou peste câteva secunde.",
                $retryKb
            );
            exit;
        }
    }
    if ($data === 'cancel_paid') {
        file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE_REZERVARE - INTRAT\n", FILE_APPEND);
        $reservation_code = $state['reservation_code'] ?? null;
        $payId = $state['maib_payId'] ?? null; 
        $amount = $state['refund_amount'] ?? null; 

        if (!$reservation_code) {
            sendMessage($chat_id, "❌ Nu găsesc codul de rezervare pentru anulare.");
            exit;
        }
        $cancel_url = "https://gam2022.unisim-soft.com/widget_una/include/cancel_reservation.php?reservation_code={$reservation_code}";
        $cancelResp = post_api($cancel_url, [], true, 'Unisimso', 's0ft2025Web');
        file_put_contents(DEBUG_LOG, "[DEBUG] ANULARE - RESP: $cancelResp\n", FILE_APPEND);
        $cres = json_decode($cancelResp, true);
        if (!empty($cres['type']) && $cres['type'] === 'success') {
            if ($payId) {
                $accessToken = maib_get_access_token();
                $refundBody = ['payId' => $payId, 'amount' => $amount];
                $ch = curl_init("https://api.maibmerchants.md/v1/refund");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refundBody));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json"
                ]);
                $result = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                file_put_contents(DEBUG_LOG, "[REFUND][MAIB]: $result\n", FILE_APPEND);
                $resp = json_decode($result, true);
                if ($http_code === 200 && !empty($resp['result']['status']) && $resp['result']['status'] === 'OK') {
                    $destination = $resp['result']['card'] ?? ($resp['result']['iban'] ?? 'necunoscut');
                    if (is_array($destination)) {
                        $destination = $destination['maskedPan'] ?? 'necunoscut';
                    }
                    $status_code   = $resp['result']['statusCode'] ?? 'n/a';
                    $status_msg    = $resp['result']['statusMessage'] ?? 'n/a';

                    file_put_contents(DEBUG_LOG,
                        "[DEBUG][REFUND] Suma returnată: {$refund_amount}\n" .
                        "Destinație: {$destination}\n" .
                        "StatusCode: {$status_code}\n" .
                        "StatusMessage: {$status_msg}\n",
                        FILE_APPEND
                    );
                    sendMessage($chat_id,
                        "✅ Rezervarea și plata au fost anulate cu succes.\n" .
                        "❕Atenție suma returnată se proceseaza în decurs de 1-5 zile lucrătoare\n" .
                        "💸 Suma returnată: <b> MDL</b>\n" .
                        "📄 Status: <b>{$status_msg}</b>",
                        [[['text' => '🏠 Meniu principal', 'callback_data' => 'main_menu']]]
                    );
                } else {
                    sendMessage($chat_id,
                        "❌ Rezervarea a fost anulată, dar returnarea banilor a eșuat!\n" .
                        "Contactează suportul.\n\n<pre>" . print_r($resp, 1) . "</pre>",
                        [[['text' => '🏠 Meniu principal', 'callback_data' => 'main_menu']]],
                        'HTML'
                    );
                }
            } else {
                sendMessage($chat_id, "✅ Rezervarea a fost anulată cu succes.");
            }
            clearState($chat_id);
        } else {
            sendMessage($chat_id, "❌ Eroare la anulare. Contactează suportul.");
        }

        exit;
    }
    if ($data === 're_emit_bon') {
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
if ($data === 'pay_cash_casa') {
    $idx         = $state['selectat_cursa'] ?? null;
    $detalii     = $state['curse_data'][$idx] ?? [];
    $nr_locuri   = $state['places'] ?? 1;
    $station     = $state['destPoint'];
    $startPoint  = $state['startPoint'];

    $first  = $state['fio']['first'] ?? 'Nume';
    $last   = $state['fio']['last'] ?? 'Prenume';
    $phone  = $state['phone'] ?? '';
    $email  = $state['email'] ?? '';
    $code        = $detalii['code'];
    $date_cursa  = $detalii['data_td'];
    $url_get = "https://gam2022.unisim-soft.com/widget_una/include/getPlaces.php"
        . "?code={$code}&data={$date_cursa}&station={$station}";
    $resp_inainte = post_api($url_get, [], true, 'Unisimso', 's0ft2025Web');
    $jp = json_decode($resp_inainte, true);

    $locuri_rez = [];
    if (!empty($jp['content']['place'])) {
        foreach ($jp['content']['place'] as $p) {
            if ($p['occupied'] === "0") {
                $locuri_rez[] = $p['place_nr'];
                if (count($locuri_rez) >= $nr_locuri) break;
            }
        }
    }

    if (count($locuri_rez) < $nr_locuri) {
        sendMessage($chat_id, "🚫 Nu mai sunt suficiente locuri libere pentru această cursă!");
        exit;
    }
    $params = [
        'route'      => $code,
        'data'       => $date_cursa,
        'biletcount' => $nr_locuri,
        'locuri'     => implode(',', $locuri_rez),
        'station'    => $station,
        'startPoint' => $startPoint,
        'first_name' => $first,
        'last_name'  => $last,
        'phone'      => $phone,
        'email'      => $email
    ];

    file_put_contents(DEBUG_LOG, "[RESERVE_PAYLOAD] " . json_encode($params) . "\n", FILE_APPEND);
    $api_resp = post_api(
        'https://gam2022.unisim-soft.com/widget_una/include/reserve.php',
        $params,
        true,
        'Unisimso',
        's0ft2025Web'
    );

    file_put_contents(DEBUG_LOG, "[RESERVE_API_RESP] $api_resp\n", FILE_APPEND);
    $resp = json_decode($api_resp, true);
    if (empty($resp['type']) || $resp['type'] !== 'success' || empty($resp['reservation_code'])) {
        $err = $resp['message'] ?? $api_resp ?? 'Eroare necunoscută de la API!';
        sendMessage($chat_id, "Eroare la rezervare! $err");
        exit;
    }
    $cod_rezervare = $resp['reservation_code'];
    $locuri_alocate = $locuri_rez; 
    $deadline_ts    = time() + 3 * 3600; 
    $deadline_txt   = date('d.m.Y H:i', $deadline_ts);
    $state['rezervare_pending'] = [
        'cod'         => $cod_rezervare,
        'bilete'      => $nr_locuri,
        'detalii'     => $detalii,
        'locuri'      => $locuri_alocate,
        'deadline'    => $deadline_ts,
        'deadline_txt'=> $deadline_txt,
        'created'     => time(),
        'status'      => 'neachitat'
    ];
    saveState($chat_id, $state);
    $locuri_txt = !empty($locuri_alocate) ? implode(', ', $locuri_alocate) : 'automat';

    $help_text = "<b>Rezervare temporară (maxim 5 bilete)</b>\n\n" .
        "🔒 Rezervarea ta a fost creată cu succes!\n" .
        "• Cod rezervare: <b>$cod_rezervare</b>\n" .
        "• Ruta: {$detalii['plecare']} ({$detalii['ruta']})\n" .
        "• Data: {$detalii['data_td']} Ora: {$detalii['ora']}\n" .
        "• Număr bilete: <b>$nr_locuri</b>\n" .
        "• Locuri rezervate: <b>$locuri_txt</b>\n" .
        "• Preț pe loc: <b>{$detalii['tarif']}</b> MDL\n" .
        "• Status: <b>NEACHITAT</b>\n" .
        "• Valabil până la: <b>$deadline_txt</b>\n\n" .
        "<b>Instrucțiuni:</b>\n" .
        "1️⃣ Mergi la orice casă cu codul rezervării.\n" .
        "2️⃣ Achită suma indicată până la data limită.\n" .
        "3️⃣ Dacă nu achiți până la <b>$deadline_txt</b>, rezervarea se anulează automat.\n\n" .
        "<i>Pentru detalii contactează casieria sau suportul: 022-222-222</i>";

    $kb = [
        [['text' => 'Revin la meniul principal', 'callback_data' => 'main_menu']]
    ];
    sendMessage($chat_id, $help_text, $kb);
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
            ,
            [
                ['text' => "🤖 Am nevoie de ajutor!", 'callback_data' => 'need_help']
            ]
        ];

        $state = ['step' => 'select_type', 'lang' => 'ro'];
        saveState($chat_id, $state);

        sendMessage($chat_id, $msg, $kb);
        exit;
    }
    function checkDemoCashPaid($code) {
        $codes = file_exists(__DIR__.'/paid_cash_codes.txt')
            ? file(__DIR__.'/paid_cash_codes.txt', FILE_IGNORE_NEW_LINES)
            : [];
        return in_array($code, $codes);
    }
    if ($data === 'pay_cash') {
        $detalii = $state['curse_data'][$state['selectat_cursa']];
        $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 1);
        $cod_cash = "CASH-" . rand(10000,99999); 
        $state['pay_cash_code'] = $cod_cash;
        saveState($chat_id, $state);
        $msg = "Plată DEMO cash:\n\n" .
            "1️⃣ Mergi la terminal DEMO sau <a href='https://83b9353ac5da.ngrok-free.app/plata-cash-demo.php?code=$cod_cash'>apasa aici pentru a marca ca platit</a>\n".
            "2️⃣ Codul tău: <b>$cod_cash</b>\n".
            "3️⃣ Suma: <b>$amount MDL</b>\n\n".
            "Așteaptă confirmarea automată a plății.";
        sendMessage($chat_id, $msg);
        $max_wait = 60; 
        $waited = 0;
        $interval = 3; 
        while ($waited < $max_wait) {
            sleep($interval);
            if (checkDemoCashPaid($cod_cash)) {
                sendMessage($chat_id, "✅ Plata cash a fost procesată cu succes! Rezervarea este confirmată.");
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
                require_once(__DIR__.'/fpdf/fpdf.php');
                $pdf = new FPDF('P', 'mm', [80, 120]);
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 8, 'Bilet / Bon pentru cursa', 0, 1, 'C');
                $pdf->Ln(1);
                $pdf->Cell(0, 7, 'Ruta: '.$detalii['plecare'], 0, 1);
                $pdf->Cell(0, 7, 'Data: '.$detalii['data_td'].' Ora: '.$detalii['ora'], 0, 1);
                $pdf->Cell(0, 7, 'Locuri: '.($state['places'] ?? 1), 0, 1);
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
                ['text' => "🤖 Am nevoie de ajutor!", 'callback_data' => 'need_help']
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


