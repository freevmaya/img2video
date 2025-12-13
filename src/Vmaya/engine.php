<?
	$is_dev = PHP_OS == "WINNT";

	include(dirname(__DIR__, 3).'/config/config'.($is_dev ? '-local' : '').'.php');

	define('RESULT_PATH', BASEPATH.'downloads'.DS.'results'.DS);
	define('PROCESS_PATH', BASEPATH.'downloads'.DS.'progress'.DS);
	define('RESULT_URL', BASEURL.DS.'downloads'.DS.'results'.DS);
	define('PROCESS_URL', BASEURL.DS.'downloads'.DS.'progress'.DS);
	define('ADMIN_USERID', 1573356581);
	define('SUPPORT_USERID', 1573356581);
	define("NUMBER_DOWNLOAD_ATTEMPTS", 8);

	include(INCLUDE_PATH.DS."_edbu2.php");
	include(INCLUDE_PATH.DS."console.php");
	include(INCLUDE_PATH.DS."fdbg.php");
	include(INCLUDE_PATH.DS."utils.php");
	include(INCLUDE_PATH.DS."session.php");
	include(INCLUDE_PATH.DS.'db/mySQLProvider.php');

	include(SERVICES_PATH.'APIInterface.php');
	include(SERVICES_PATH.'MidjourneyAPI.php');
	include(SERVICES_PATH.'BaseKlingApi.php');
	include(SERVICES_PATH.'KlingApi.php');

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