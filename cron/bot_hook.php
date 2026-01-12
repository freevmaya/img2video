<?
	error_reporting(E_ALL);
	require dirname(__DIR__).'/vendor/autoload.php';
	require dirname(__DIR__).'/src/Vmaya/engine.php';

	use Telegram\Bot\Api;

	$telegram = new Api(BOTTOKEN);
	echo BASEURL."\n";

	$telegram->deleteWebhook();

	// 2. Устанавливаем новый вебхук
	$response = $telegram->setWebhook([
	    'url' => BASEURL,
	    //'certificate' => '/path/to/your/certificate.pem', // Опционально для HTTPS
	    'max_connections' => 40
	]);

	print_r($response);
	echo "\n";
?>