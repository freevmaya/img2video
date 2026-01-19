<?	
	error_reporting(E_ALL);
	session_start();
	include(dirname(__FILE__, 3)."/src/Vmaya/site_engine.php");
	
	Page::Run(new TGUserModel(), array_merge($_POST, $_GET));
?>