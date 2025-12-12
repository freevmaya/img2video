<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';
require dirname(__DIR__) . '/src/Vmaya/services/BaseKlingApi.php';
require dirname(__DIR__) . '/src/Vmaya/services/KlingApi.php';

use App\Services\API\KlingApi;

$dbp = new mySQLProvider(_dbhost, _dbname_default, _dbuser, _dbpassword);

$api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY);
$api->generateVideoFromImage('https://vmaya.ru/img2video/downloads/results/FaceGerl01.jpg', 'The girl smiles and then turns around as if someone called out to her from the left.');
$dbp->Close();
