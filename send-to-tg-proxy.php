<?php
/**
 * Telegram Bot Proxy Server
 * v2.0 - Форма + Отзывы
 */

// === CORS для Render ===
$allowed_origin = 'https://manicure.ct.ws';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
}

// Preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

header('Content-Type: application/json');

// Получаем данные
$input = json_decode(file_get_contents('php://input'), true);

// === НАСТРОЙКИ БОТА ===
$token = "8517119171:AAHibMpoU5NPMRgOCkH9holkHIs0oZwMats";
$chat_id = "1089091335";

// === ТИП 1: ФОРМА ОБРАТНОЙ СВЯЗИ (ваш рабочий код) ===
// Проверяем по наличию полей формы (без type — для обратной совместимости)
if (isset($input['phone']) && isset($input['tg_username']) && isset($input['question'])) {
    
    $phone = $input['phone'];
    $tg_username = ltrim($input['tg_username'], '@');
    $question = $input['question'];
    
    if (!$phone || !$tg_username || !$question) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Заполните все поля']);
        exit;
    }
    
    $message = "📩 <b>Новый вопрос с сайта PickMeNails</b>\n\n";
    $message .= "📞 <b>Телефон:</b> $phone\n";
    $message .= "✈️ <b>Telegram:</b> @$tg_username\n";
    $message .= "❓ <b>Вопрос:</b>\n$question";
    
    sendTelegramMessage($token, $chat_id, $message);
    echo json_encode(['status' => 'success', 'message' => 'Отправлено!']);
    exit;
}

// === ТИП 2: НОВЫЙ ОТЗЫВ (по полю type) ===
if (isset($input['type']) && $input['type'] === 'review') {
    
    $name = $input['name'] ?? '';
    $rating = intval($input['rating'] ?? 5);
    $text = $input['text'] ?? '';
    $admin_url = $input['admin_url'] ?? '';
    
    if (!$name || !$text) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Имя и текст обязательны']);
        exit;
    }
    
    // Звёзды
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    
    $message = "📝 <b>Новый отзыв на сайте!</b>\n\n";
    $message .= "👤 <b>Имя:</b> $name\n";
    $message .= "⭐ <b>Оценка:</b> $rating/5 $stars\n";
    $message .= "💬 <b>Текст:</b>\n$text\n\n";
    $message .= "🔗 <a href=\"$admin_url\">Проверить в админке</a>";
    
    sendTelegramMessage($token, $chat_id, $message);
    echo json_encode(['status' => 'success', 'message' => 'Отправлено!']);
    exit;
}

// Если ничего не подошло
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Неизвестный тип запроса']);

// === ФУНКЦИЯ ОТПРАВКИ ===
function sendTelegramMessage($token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>
