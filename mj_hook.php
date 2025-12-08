<?
	require __DIR__ . '/vendor/autoload.php';
	require __DIR__ . '/src/Vmaya/engine.php';

	$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

	$model = new CommModel();

	$model->Update([
		'data'=>json_encode(array_merge($_POST, $_GET))
	]);

	$dbp->Close();