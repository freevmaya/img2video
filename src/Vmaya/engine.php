<?
	include(dirname(__DIR__, 3).'/config/config.php');

	define('DOWNLOADS_PATH', BASEPATH.'downloads'.DS);
	define('DOWNLOADS_URL', BASEURL.'/downloads/');

	define('RESULT_PATH', DOWNLOADS_PATH.'results'.DS);
	define('PROCESS_PATH', DOWNLOADS_PATH.'progress'.DS);
	define('USER_PATH', DOWNLOADS_PATH.'users'.DS);
	
	define('RESULT_URL', DOWNLOADS_URL.'results/');
	define('PROCESS_URL', DOWNLOADS_URL.'progress/');
	define('USER_URL', DOWNLOADS_URL.'users/');

	define('IMAGES_PATH', BASEPATH.'public'.DS.'images'.DS);
	define('IMAGES_URL', BASEURL.DS.'public'.DS.'images'.DS);

	define('VIDEOS_PATH', BASEPATH.'public'.DS.'videos'.DS);
	define('VIDEOS_URL', BASEURL.'/public/videos/');

	define('ADMIN_USERID', 1573356581);
	define('SUPPORT_USERID', 1573356581);
	define("NUMBER_DOWNLOAD_ATTEMPTS", 8);
	define("JSON_FLAGS", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);

	define('MJ_BASE_URL', 'https://cdn.midjourney.com/');

	define('CURRENCY_LIST', [
		'ru'=>'RUB',
		'en'=>'USD'
	]);

	include(INCLUDE_PATH.DS."_edbu2.php");
	include(INCLUDE_PATH.DS."console.php");
	include(INCLUDE_PATH.DS."fdbg.php");
	include(INCLUDE_PATH.DS."utils.php");
	include(INCLUDE_PATH.DS."session.php");
	include(INCLUDE_PATH.DS.'db/mySQLProvider.php');

	include(SERVICES_PATH.'APIInterface.php');
	include(SERVICES_PATH.'BaseApi.php');
	include(SERVICES_PATH.'MidjourneyApi.php');
	include(SERVICES_PATH.'LeonardoApi.php');
	include(SERVICES_PATH.'BaseKlingApi.php');
	include(SERVICES_PATH.'KlingApi.php');
	include(SERVICES_PATH.'cycle/BaseCycle.php');
	include(SERVICES_PATH.'cycle/KlingCycle.php');
	include(SERVICES_PATH.'cycle/LeoCycle.php');
	include(SERVICES_PATH.'cycle/MjCycle.php');
	include(SERVICES_PATH.'utils.php');

	define("AUTOLOAD_PATHS", [INCLUDE_PATH, CLASSES_PATH, MODELS_PATH]);
	spl_autoload_register(function ($class_name) {

		foreach (AUTOLOAD_PATHS as $path) {
			$pathFile = $path.DS.$class_name.".php";
			if (file_exists($pathFile)) {
			    	include_once($pathFile);
			    	return true;
			}
		}

		//throw new Exception("Can't load class {$class_name}", 1);
	});

	function exception_handler(Throwable $exception) {
		$error_msg = $exception->getFile().' '.$exception->getLine().': '.$exception->getMessage();
		echo $error_msg;
		trace_error($error_msg);
	}

	set_exception_handler('exception_handler');
?>