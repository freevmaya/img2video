<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';
require dirname(__DIR__) . '/src/Vmaya/services/BaseKlingApi.php';

use App\Services\API\BaseKlingApi;

$api = new BaseKlingApi(KL_ACCESS_KEY, KL_SECRET_KEY);
$api->generateVideoFromImage('https://vmaya.ru/img2video/downloads/results/FaceGerl01.jpg', 'The girl smiles and then turns around as if someone called out to her from the left.');
