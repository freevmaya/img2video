<?php
$apiUrl = 'http://localhost:8080/chat';

// Инициализация сессии (для хранения user_id)
session_start();

if (isset($_GET['clear']))
    unset($_SESSION['chat_history']);

// Обработка отправки сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'] ?? '';

    if (!isset($_SESSION['chat_history']))
        $_SESSION['chat_history'] = [['role'=>'system', 'content'=>"Ты - полезный AI-ассистент. Ты гуру в парапланеризме. Отвечай на русском языке."]];

    $_SESSION['chat_history'][] = ['role'=>'user', 'content'=>$message];
    
    $data = [
        'messages' => $_SESSION['chat_history']
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response === FALSE) {
        die('Ошибка соединения с сервером');
    }
    
    $result = json_decode($response, true);

    if ($result['response'])
        $_SESSION['chat_history'][] = ['role'=>'assistant', 'content'=>$result['response']]; // Сохраняем историю
}
?>

<!-- HTML-форма -->
<form method="post">
    <input type="text" name="message" placeholder="Ваше сообщение..." required>
    <button type="submit">Отправить</button>
</form>

<!-- Вывод истории -->
<div class="chat-history">
    <?php if (!empty($_SESSION['chat_history'])): ?>
        <?php foreach ($_SESSION['chat_history'] as $msg): ?>
            <p><strong><?=$msg['role']?>:</strong> 
               <?= htmlspecialchars($msg['content']) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>