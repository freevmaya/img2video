<?
	error_reporting(E_ALL);
	require '../vendor/autoload.php';
	require '../src/Vmaya/engine.php';

	use Telegram\Bot\Api;

	$telegram = new Api(BOTTOKEN);

	if (isset($argv[1]) && ($argv[1] == 'reset')) {

		echo BASEURL."\n";

		$telegram->deleteWebhook();

		// 2. Устанавливаем новый вебхук
		$response = $telegram->setWebhook([
		    'url' => BASEURL,
		    //'certificate' => '/path/to/your/certificate.pem', // Опционально для HTTPS
		    'max_connections' => 40,
		    'allowed_updates' => ['message', 'callback_query'] // Какие обновления получать
		]);

		print_r($response);
	} else {

		$dbp = new mySQLProvider('localhost', _dbname_default, _dbuser, _dbpassword);

		$bot = new Image2VideoBot($telegram, $dbp);
		$bot->GetWebhookUpdates();

		$dbp->Close();
	}
?>