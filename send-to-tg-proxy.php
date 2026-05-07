
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// ======================================
// НАСТРОЙКИ
// ======================================

$bot_token = 'NEW_BOT_TOKEN';
$admin_chat_id = '1089091335';
$allowed_origin = 'https://manicure.ct.ws';

// ======================================
// CORS
// ======================================

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ======================================
// HEALTH CHECK
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    echo json_encode([
        'status' => 'ok',
        'time' => date('Y-m-d H:i:s')
    ]);

    exit;
}

// ======================================
// ЧИТАЕМ ВХОДЯЩИЙ JSON
// ======================================

$raw = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/debug.log',
    date('Y-m-d H:i:s') . PHP_EOL .
    $raw . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$input = json_decode($raw, true);

if (!$input) {
    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON'
    ]);

    exit;
}

// ======================================
// 1. TELEGRAM WEBHOOK
// ======================================

if (isset($input['message'])) {

    $message = $input['message'];

    $chat_id = $message['chat']['id'];
    $text = trim($message['text'] ?? '');

    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';

    // =========================
    // /start
    // =========================

    if ($text === '/start') {

        if ((string)$chat_id === (string)$admin_chat_id) {

            sendTelegramMessage(
                $bot_token,
                $chat_id,
                "👋 <b>Админ-панель PickMeNails</b>\n\nВыберите действие:",
                [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '📊 Статистика',
                                'callback_data' => 'stats'
                            ]
                        ],
                        [
                            [
                                'text' => '👥 Подписчики',
                                'callback_data' => 'subs'
                            ]
                        ]
                    ]
                ]
            );

        } else {

            sendTelegramMessage(
                $bot_token,
                $chat_id,
                "👋 Добро пожаловать в PickMeNails 💅\n\nСпасибо за подписку!"
            );
        }

        echo json_encode(['status' => 'ok']);
        exit;
    }

    // =========================
    // /stats
    // =========================

    if (
        $text === '/stats' &&
        (string)$chat_id === (string)$admin_chat_id
    ) {

        sendTelegramMessage(
            $bot_token,
            $chat_id,
            "📊 Бот работает нормально"
        );

        echo json_encode(['status' => 'ok']);
        exit;
    }

    // =========================
    // ОБЫЧНЫЕ СООБЩЕНИЯ
    // =========================

    sendTelegramMessage(
        $bot_token,
        $chat_id,
        "✅ Получено сообщение:\n\n$text"
    );

    echo json_encode(['status' => 'ok']);
    exit;
}

// ======================================
// 2. CALLBACK КНОПКИ
// ======================================

if (isset($input['callback_query'])) {

    $callback = $input['callback_query'];

    $chat_id = $callback['message']['chat']['id'];
    $callback_id = $callback['id'];
    $data = $callback['data'];

    if ((string)$chat_id !== (string)$admin_chat_id) {

        answerCallback(
            $bot_token,
            $callback_id,
            'Нет доступа'
        );

        exit;
    }

    if ($data === 'stats') {

        sendTelegramMessage(
            $bot_token,
            $chat_id,
            "📊 Статистика:\n\n✅ Бот активен"
        );
    }

    if ($data === 'subs') {

        sendTelegramMessage(
            $bot_token,
            $chat_id,
            "👥 Подписчики:\n\nПока SQLite отключён"
        );
    }

    answerCallback($bot_token, $callback_id);

    echo json_encode(['status' => 'ok']);
    exit;
}

// ======================================
// 3. ЗАПРОСЫ С САЙТА
// ======================================

// Вопрос с формы
if (
    isset($input['phone']) &&
    isset($input['question'])
) {

    $phone = htmlspecialchars($input['phone']);
    $question = htmlspecialchars($input['question']);

    $tg = htmlspecialchars(
        ltrim($input['tg_username'] ?? '', '@')
    );

    $message =
        "📩 <b>Новый вопрос с сайта</b>\n\n" .
        "📞 Телефон: $phone\n" .
        "✈️ Telegram: @$tg\n\n" .
        "❓ Вопрос:\n$question";

    sendTelegramMessage(
        $bot_token,
        $admin_chat_id,
        $message
    );

    echo json_encode([
        'status' => 'success'
    ]);

    exit;
}

// Отзыв
if (
    isset($input['name']) &&
    isset($input['text']) &&
    isset($input['rating'])
) {

    $name = htmlspecialchars($input['name']);
    $text = htmlspecialchars($input['text']);

    $rating = intval($input['rating']);

    $stars =
        str_repeat('⭐', $rating);

    $message =
        "📝 <b>Новый отзыв</b>\n\n" .
        "👤 Имя: $name\n" .
        "⭐ Оценка: $stars\n\n" .
        "💬 Отзыв:\n$text";

    sendTelegramMessage(
        $bot_token,
        $admin_chat_id,
        $message
    );

    echo json_encode([
        'status' => 'success'
    ]);

    exit;
}

// ======================================
// НЕИЗВЕСТНЫЙ ЗАПРОС
// ======================================

http_response_code(400);

echo json_encode([
    'status' => 'error',
    'message' => 'Unknown request'
]);

// ======================================
// ФУНКЦИИ
// ======================================

function sendTelegramMessage(
    $token,
    $chat_id,
    $text,
    $reply_markup = null
) {

    $url =
        "https://api.telegram.org/bot$token/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($reply_markup) {
        $data['reply_markup'] =
            json_encode($reply_markup);
    }

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
        __DIR__ . '/telegram-send.log',
        $result . PHP_EOL,
        FILE_APPEND
    );

    curl_close($ch);

    return $result;
}

function answerCallback(
    $token,
    $callback_id,
    $text = ''
) {

    $url =
        "https://api.telegram.org/bot$token/answerCallbackQuery";

    $data = [
        'callback_query_id' => $callback_id,
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

    curl_exec($ch);

    curl_close($ch);
}

