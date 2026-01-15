<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use App\Services\API\KlingApi;

$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

$api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, new TaskModel());

$api->prepareVideoMultiElement(BASEURL.'data/videos/Multi-Image-01.mp4', [
	[
		[0.35, 0.17],
		[0.52, 0.17],
		[0.52, 0.30],
		[0.35, 0.30]
	],[
		[0.35, 0.17],
		[0.52, 0.17],
		[0.52, 0.30],
		[0.35, 0.30]
	]
]);

$dbp->Close();
