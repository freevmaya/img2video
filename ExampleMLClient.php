<?php
require __DIR__ . '/src/Vmaya/engine.php';

$lmStudioUrl = 'http://localhost:1234/v1/chat/completions'; // API-адрес LM Studio
$modelName = 'yandexgpt-5-lite-8b-instruct'; // Точное имя из LM Studio
//$modelName = 'YandexGPT-5-Lite-8B-instruct-Q4_K_M'; // Точное имя из LM Studio

$ml_studio = new MLStudioClient($lmStudioUrl, $modelName, "Ты классификатор");
/*
$dbp = new mySQLProvider('localhost', _dbname_default, _dbuser, _dbpassword);

$promt = $dbp ? html::RenderFile(TEMPLATES_PATH.'observer_promt2.php') : file_get_contents(CONFIG_PATH.'classification_promt.txt');
*/

$promt = file_get_contents(CONFIG_PATH.'classification_promt.txt');
$ml_studio->Query($promt);

echo "PROMT: ".$promt."\n";


$ml_studio->Query("text: \"Продаю крыло Gin Carrera + M. EN-B. 85-105, 2016г. С прекрасными ЛТХ, которые не уступают многим современным крыльям, даже тем, что выше классом. Налёт немного больше 200 часов. Состояние хорошее, есть несколько мелких латок. В прошлом году был сделан тримтюнинг с заменой нескольких строп вызывающих недоверие.  В комплекте крыло, концертина, рюкзак.
Цена - 36 000₽\"");

$result = $ml_studio->Send();
if (isset($result['choices'][0]['message']))
	print_r($result['choices'][0]['message']);
?>