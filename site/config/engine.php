<?
	include(dirname(__FILE__)."/config.php");

	include(INCLUDE_PATH."_edbu2.php");
	include(INCLUDE_PATH."console.php");
	include(INCLUDE_PATH."fdbg.php");
	include(INCLUDE_PATH."utils.php");
	include(INCLUDE_PATH."session.php");
	include(INCLUDE_PATH.'db/mySQLProvider.php');

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