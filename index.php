<?
	header('Content-Type: application/json');
	error_reporting(E_ALL);
	session_start();
	include("site/config/engine.php");
	Page::Run(array_merge($_POST, $_GET));
?>