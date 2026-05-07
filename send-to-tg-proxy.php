<?php

$bot_token = "8517119171:AAHibMpoU5NPMRgOCkH9holkHIs0oZwMats";
$admin_chat_id = "1089091335";  // Ваш личный Chat ID
$allowed_origin = 'https://manicure.ct.ws';


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

$db_file = __DIR__ . '/subscribers.db';
try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id TEXT UNIQUE NOT NULL,
        username TEXT,
        first_name TEXT,
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
}

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['name']) && isset($input['text'])) {
    handleSiteNotification($input, $bot_token, $admin_chat_id);
    exit;
}

if (isset($input['message']) || isset($input['callback_query'])) {
    handleTelegramWebhook($input, $bot_token, $admin_chat_id, $db);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);


function handleSiteNotification($input, $token, $chat_id) {
    header('Content-Type: application/json');
    
    if (isset($input['name']) && isset($input['text']) && isset($input['rating'])) {
        $name = htmlspecialchars($input['name']);
        $rating = intval($input['rating']);
        $text = htmlspecialchars($input['text']);
        $admin_url = htmlspecialchars($input['admin_url'] ?? '');
        
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        
        $message = "📝 <b>Новый отзыв на сайте!</b>\n\n";
        $message .= "👤 <b>Имя:</b> $name\n";
        $message .= "⭐ <b>Оценка:</b> $rating/5 $stars\n";
        $message .= "💬 <b>Текст:</b>\n$text\n\n";
        $message .= "🔗 <a href=\"$admin_url\">Проверить в админке</a>";
        
        sendTelegramMessage($token, $chat_id, $message);
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    if (isset($input['phone']) && isset($input['question'])) {
        $phone = htmlspecialchars($input['phone']);
        $tg = htmlspecialchars(ltrim($input['tg_username'] ?? '', '@'));
        $question = htmlspecialchars($input['question']);
        
        $message = "📩 <b>Новый вопрос с сайта!</b>\n\n";
        $message .= "📞 <b>Телефон:</b> $phone\n";
        $message .= "✈️ <b>Telegram:</b> @$tg\n";
        $message .= "❓ <b>Вопрос:</b>\n$question";
        
        sendTelegramMessage($token, $chat_id, $message);
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}

function handleTelegramWebhook($input, $token, $admin_chat_id, $db) {
    // Callback query (нажатие на кнопки)
    if (isset($input['callback_query'])) {
        handleCallback($input['callback_query'], $token, $admin_chat_id, $db);
        return;
    }
    
    if (isset($input['message'])) {
        handleMessage($input['message'], $token, $admin_chat_id, $db);
        return;
    }
}

function handleMessage($message, $token, $admin_chat_id, $db) {
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';
    
    if ($text === '/start') {
        subscribeUser($db, $chat_id, $username, $first_name);
        
        if ($chat_id == $admin_chat_id) {
            sendTelegramMessage($token, $chat_id, 
                "👋 <b>Добро пожаловать, Админ!</b>\n\n" .
                "Я бот салона маникюра PickMeNails 💅\n\n" .
                "Выберите действие:",
                'HTML', getAdminKeyboard());
        } else {
            sendTelegramMessage($token, $chat_id, 
                "👋 <b>Добро пожаловать в PickMeNails!</b>\n\n" .
                "Вы подписаны на наши новости и акции 🎉\n\n" .
                "Мы публикуем:\n" .
                "• Акции и скидки 💰\n" .
                "• Новые работы мастеров 💅\n" .
                "• Полезные советы по уходу 📚\n\n" .
                "Чтобы отписаться, нажмите /unsubscribe",
                'HTML', getUserKeyboard());
        }
        return;
    }

    if ($text === '/stats' && $chat_id == $admin_chat_id) {
        $count = getSubscribersCount($db);
        sendTelegramMessage($token, $chat_id, 
            "📊 <b>Статистика бота:</b>\n\n" .
            "👥 Подписчиков: <b>$count</b>\n" .
            "📅 Сегодня: " . date('d.m.Y H:i'),
            'HTML');
        return;
    }
    
    if ($text === '/subscribers' && $chat_id == $admin_chat_id) {
        $subs = getSubscribersList($db, 10);
        $count = getSubscribersCount($db);
        
        $message = "👥 <b>Подписчики (всего: $count)</b>\n\n";
        foreach ($subs as $sub) {
            $user = $sub['username'] ? "@{$sub['username']}" : $sub['first_name'];
            $message .= "• $user\n";
        }
        
        sendTelegramMessage($token, $chat_id, $message, 'HTML');
        return;
    }
    
    if ($text === '/broadcast' && $chat_id == $admin_chat_id) {
        sendTelegramMessage($token, $chat_id, 
            "📢 <b>Рассылка подписчикам</b>\n\n" .
            "Отправьте сообщение, которое нужно разослать всем подписчикам.\n\n" .
            "⚠️ <b>Внимание:</b> Сообщение будет отправлено всем!",
            'HTML', [
                'inline_keyboard' => [
                    [['text' => '❌ Отмена', 'callback_data' => 'cancel_broadcast']]
                ]
            ]);
        return;
    }
    
    if ($text === '/unsubscribe') {
        unsubscribeUser($db, $chat_id);
        sendTelegramMessage($token, $chat_id, 
            "🔕 Вы отписаны от рассылки.\n\n" .
            "Чтобы подписаться снова, нажмите /start",
            'HTML');
        return;
    }
    
    if ($chat_id != $admin_chat_id) {
        sendTelegramMessage($token, $chat_id, 
            "Спасибо за сообщение! 🙏\n" .
            "Мастер скоро ответит вам в личном чате.",
            'HTML');
        // Пересылаем админу
        sendTelegramMessage($token, $admin_chat_id, 
            "📩 <b>Сообщение от пользователя</b>\n\n" .
            "👤: @{$username}\n" .
            "💬: {$text}",
            'HTML');
        return;
    }
}

function handleCallback($callback, $token, $admin_chat_id, $db) {
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];
    
    if ($chat_id != $admin_chat_id) {
        answerCallback($token, $callback['id'], '❌ Доступ запрещён');
        return;
    }
    
    switch ($data) {
        case 'admin_broadcast':
            sendTelegramMessage($token, $chat_id, 
                "📢 <b>Рассылка подписчикам</b>\n\n" .
                "Отправьте сообщение для рассылки:",
                'HTML');
            answerCallback($token, $callback['id']);
            break;
            
        case 'admin_subscribers':
            $count = getSubscribersCount($db);
            $subs = getSubscribersList($db, 10);
            $message = "👥 <b>Подписчики: $count</b>\n\n";
            foreach ($subs as $sub) {
                $user = $sub['username'] ? "@{$sub['username']}" : $sub['first_name'];
                $message .= "• $user\n";
            }
            sendTelegramMessage($token, $chat_id, $message, 'HTML');
            answerCallback($token, $callback['id']);
            break;
            
        case 'admin_stats':
            $count = getSubscribersCount($db);
            sendTelegramMessage($token, $chat_id, 
                "📊 <b>Статистика:</b>\n\n" .
                "👥 Подписчиков: <b>$count</b>\n" .
                "📅 Дата: " . date('d.m.Y H:i'),
                'HTML');
            answerCallback($token, $callback['id']);
            break;
            
        case 'cancel_broadcast':
            sendTelegramMessage($token, $chat_id, "❌ Рассылка отменена");
            answerCallback($token, $callback['id'], 'Отменено');
            break;
            
        default:
            answerCallback($token, $callback['id']);
    }
}


function sendTelegramMessage($token, $chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function answerCallback($token, $callback_id, $text = '') {
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['callback_query_id' => $callback_id, 'text' => $text]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function getAdminKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '📢 Сделать рассылку', 'callback_data' => 'admin_broadcast']],
            [['text' => '👥 Подписчики', 'callback_data' => 'admin_subscribers'], 
             ['text' => '📊 Статистика', 'callback_data' => 'admin_stats']]
        ]
    ];
}

function getUserKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '🔕 Отписаться', 'callback_data' => 'unsubscribe']]
        ]
    ];
}

function subscribeUser($db, $chat_id, $username, $first_name) {
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO subscribers (chat_id, username, first_name) VALUES (?, ?, ?)");
        $stmt->execute([$chat_id, $username, $first_name]);
    } catch (PDOException $e) {
        error_log('Subscribe error: ' . $e->getMessage());
    }
}

function unsubscribeUser($db, $chat_id) {
    try {
        $stmt = $db->prepare("DELETE FROM subscribers WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
    } catch (PDOException $e) {
        error_log('Unsubscribe error: ' . $e->getMessage());
    }
}

function getSubscribersCount($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM subscribers");
    return $stmt->fetchColumn();
}

function getSubscribersList($db, $limit = 10) {
    $stmt = $db->prepare("SELECT * FROM subscribers ORDER BY subscribed_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
