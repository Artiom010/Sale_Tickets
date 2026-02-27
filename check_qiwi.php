<?php
// fisier: check_qiwi.php (chemat de cron la fiecare 5 sec)
$files = glob(__DIR__ . '/state/*.json');
foreach ($files as $file) {
    $state = json_decode(file_get_contents($file), true);
    if (isset($state['qr_merchant'], $state['qiwi_token'], $state['step']) && $state['step']==='await_qiwi') {
        $merchantID = $state['qr_merchant'];
        $token = $state['qiwi_token'];
        $chat_id = basename($file, '.json');

        // Verifică status
        $opts = [
            "http" => [
                "header" => "Authorization: Bearer $token\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $status_resp = @file_get_contents('https://api-stg.qiwi.md/qr/get-qr-status-by-merchant-id?merchantID='.$merchantID, false, $context);
        $status_data = json_decode($status_resp, true);
        $status = $status_data['status'] ?? null;

        if ($status === 'Paid') {
            // Trimitere mesaj & PDF
            require_once('fpdf/fpdf.php');
            $detalii = $state['curse_data'][$state['selectat_cursa']];
            $amount = (float)preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $detalii['tarif'])) * ($state['places'] ?? 1);

            $pdf = new FPDF('P', 'mm', [80, 120]);
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, 'Bilet / Bon pentru cursa', 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->Cell(0, 7, 'Ruta: '.$detalii['plecare'], 0, 1);
            $pdf->Cell(0, 7, 'Data: '.$detalii['data_td'].' Ora: '.$detalii['ora'], 0, 1);
            $pdf->Cell(0, 7, 'Numar locuri: '.$state['places'], 0, 1);
            $pdf->Cell(0, 7, 'Total: '.number_format($amount,2,'.','').' MDL', 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, 'Va dorim drum bun!', 0, 1, 'C');
            $tmp_pdf = sys_get_temp_dir()."/bilet_{$chat_id}.pdf";
            $pdf->Output('F', $tmp_pdf);

            // Trimite PDF la Telegram
            $cfile = new CURLFile($tmp_pdf, 'application/pdf', "Bilet.pdf");
            $payload_pdf = [
                'chat_id' => $chat_id,
                'caption' => 'Biletul/bonul tău electronic',
                'document' => $cfile
            ];
            $ch = curl_init("https://api.telegram.org/bot7831703732:AAFMpmw8mG_IEfmCkQHgeG--XEyyDX7R854/sendDocument");
            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
            unlink($tmp_pdf);

            // Șterge state după succes
            unlink($file);
        }
    }
}
