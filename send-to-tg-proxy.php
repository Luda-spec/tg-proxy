<?php
$allowed_origins = [
    'https://manicure.ct.ws',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$phone = $input['phone'] ?? '';
$tg_username = $input['tg_username'] ?? '';
$question = $input['question'] ?? '';

if (!$phone || !$tg_username || !$question) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Заполните все поля']);
    exit;
}

$tg_username = ltrim($tg_username, '@');

$token = "8517119171:AAHibMpoU5NPMRgOCkH9holkHIs0oZwMats";
$chat_id = "6769371325";

$message = "📩 Новый вопрос с сайта PickMeNails\n";
$message .= "📱 Телефон: $phone\n";
$message .= "✈️ Telegram: @$tg_username\n";
$message .= "💬 Вопрос: $question";

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
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo json_encode(['status' => 'success', 'message' => 'Отправлено!']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка Telegram API']);
}
?>
