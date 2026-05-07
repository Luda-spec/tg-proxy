```php id="h5xghn"
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$bot_token = 'ТВОЙ_ТОКЕН';
$admin_chat_id = '1089091335';

// ======================================
// ЧИТАЕМ RAW JSON
// ======================================

$raw = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/debug.log',
    $raw . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$input = json_decode($raw, true);

// ======================================
// TELEGRAM WEBHOOK — ПЕРВЫМ!
// ======================================

if (isset($input['message'])) {

    $chat_id = $input['message']['chat']['id'];

    $text = $input['message']['text'] ?? '';

    // /start
    if ($text === '/start') {

        sendTelegramMessage(
            $bot_token,
            $chat_id,
            "✅ Бот работает!\n\n/start получен"
        );

        exit;
    }

    // обычные сообщения
    sendTelegramMessage(
        $bot_token,
        $chat_id,
        "Получено: $text"
    );

    exit;
}

// ======================================
// CALLBACK BUTTONS
// ======================================

if (isset($input['callback_query'])) {

    $callback = $input['callback_query'];

    $chat_id = $callback['message']['chat']['id'];

    sendTelegramMessage(
        $bot_token,
        $chat_id,
        "Нажата кнопка"
    );

    exit;
}

// ======================================
// ФОРМА САЙТА
// ======================================

if (
    isset($input['phone']) &&
    isset($input['question'])
) {

    $phone = $input['phone'];
    $question = $input['question'];

    sendTelegramMessage(
        $bot_token,
        $admin_chat_id,
        "📩 Новый вопрос\n\nТелефон: $phone\n\nВопрос:\n$question"
    );

    echo json_encode(['status' => 'success']);

    exit;
}

// ======================================
// ОТЗЫВЫ
// ======================================

if (
    isset($input['name']) &&
    isset($input['text'])
) {

    $name = $input['name'];
    $text = $input['text'];

    sendTelegramMessage(
        $bot_token,
        $admin_chat_id,
        "📝 Новый отзыв\n\n$name\n\n$text"
    );

    echo json_encode(['status' => 'success']);

    exit;
}

// ======================================
// DEFAULT
// ======================================

echo json_encode([
    'status' => 'ok'
]);

// ======================================
// SEND MESSAGE
// ======================================

function sendTelegramMessage(
    $token,
    $chat_id,
    $text
) {

    $url = "https://api.telegram.org/bot$token/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        http_build_query($data)
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    file_put_contents(
        __DIR__ . '/send-log.txt',
        $result . PHP_EOL,
        FILE_APPEND
    );

    curl_close($ch);
}
```
