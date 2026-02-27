<?php

?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Plata efectuată cu succes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {background: #f6fff4; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: Montserrat, Arial, sans-serif; margin: 0;}
    .card {background: #fff; border-radius: 1.5em; box-shadow: 0 6px 32px #0f61261a; padding: 2.5em 2em 2em; text-align: center; max-width: 350px;}
    .check {width: 60px; height: 60px; border-radius: 50%; background: #22c55e11; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2em;}
    .check svg { color: #22c55e; width: 34px; height: 34px;}
    h1 { color: #1b4224; font-size: 1.3em; margin-bottom: .7em; }
    p { color: #164225; margin-bottom: 2em; font-size: 1.07em;}
    .button {background: #22c55e; color: #fff; font-weight: 500; padding: .8em 1.7em; border: none; border-radius: 999px; font-size: 1.07em; text-decoration: none; box-shadow: 0 1px 8px #22c55e22; cursor: pointer; transition: background .2s; display: inline-block;}
    .button:hover { background: #16a34a; }
    .loader { border: 4px solid #e1ffe7; border-top: 4px solid #22c55e; border-radius: 50%; width: 36px; height: 36px; animation: spin 1s linear infinite; margin: 1em auto .6em;}
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .timer { color: #82b58f; font-size: .93em; margin-top: 1em;}
  </style>
  <script>
    let sec = 5;
    setInterval(function() {
      if(--sec>0) document.getElementById('timer').innerText = sec;
    }, 1000);
    setTimeout(function() { window.location = "https://t.me/BIL_GARA_MDbot"; }, 5000);
  </script>
</head>
<body>
  <div class="card">
    <div class="check">
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
      </svg>
    </div>
    <h1>Plata efectuată cu succes!</h1>
    <div class="loader"></div>
    <p>Mulțumim pentru achitare.<br>Vei fi redirecționat către botul Telegram în <span id="timer">5</span> secunde.</p>
    <a class="button" href="https://t.me/BIL_GARA_MDbot">Înapoi la bot</a>
  </div>
</body>
</html>
