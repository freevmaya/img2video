<?
    require __DIR__ . '/src/Vmaya/engine.php';
    $url = 'http://localhost:5000/chat';

    $ml_studio = new FlaskMLClient($url, "Ты классификатор");
    $ml_studio->Query(file_get_contents(CONFIG_PATH.'classification_promt.txt'));

    $result = $ml_studio->Send();

    $ml_studio->Query("text: \"Продаю крыло Gin Carrera + M. EN-B. 85-105, 2016г. С прекрасными ЛТХ, которые не уступают многим современным крыльям, даже тем, что выше классом. Налёт немного больше 200 часов. Состояние хорошее, есть несколько мелких латок. В прошлом году был сделан тримтюнинг с заменой нескольких строп вызывающих недоверие.  В комплекте крыло, концертина, рюкзак.
    Цена - 36 000₽\"");

    $result = $ml_studio->Send();
    print_r($result);
?>