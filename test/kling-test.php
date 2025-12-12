<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';
require dirname(__DIR__).'/src/Vmaya/services/BaseKlingApi.php';
require dirname(__DIR__).'/src/Vmaya/services/KlingApi.php';

use App\Services\API\KlingApi;

$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

$api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, new TaskModel());
$api->generateVideoFromImage('https://vmaya.ru/img2video/downloads/20220708_122639.jpg', 'The man in the photo smiles and then turns his back. In the background, lifts are working and moving.');
$dbp->Close();
