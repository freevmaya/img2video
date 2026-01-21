<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use App\Services\API\KlingApi;

$api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, null);

$videoURL = BASEURL.'/videos/Sorceress-full.mp4';

//$result = $api->initSession($videoURL);


$result = $api->AccountInfo();

/*


$session_id = '841048603464040512';

$api->clearVideoSelection($session_id);
$result = $api->addVideoSelection($session_id, 0, [
	['x'=>0.41, 'y'=> 1 - 0.26]
]);
$result = $api->deleteVideoSelection($session_id, 0, [
	['x'=>0.5, 'y'=> 1 - 0.5]
]);

file_put_contents('kling-test.png', base64_decode($result['data']['res']['rle_mask_list'][0]['png_mask']['base64']));
*/

/*
[
	['x'=>0, 'y'=>0],
	['x'=>1, 'y'=>0],
	['x'=>1, 'y'=>1],
	['x'=>0, 'y'=>1]
]

$api->addVideoSelectionArea($session_id, 0, [
	['x'=>0.34, 'y'=>0.23],
	['x'=>0.56, 'y'=>0.34],
	['x'=>0.42, 'y'=>0.31]
]);*/

/*
$result = $api->baseRequest('https://api-singapore.klingai.com/v1/videos/multi-elements/', '{
	"model_name": "kling-v1-6",
	"session_id": "840792910093221947",
	"edit_mode": "swap",
	"image_list": [
		{
			"image":"https://vmaya.ru/img2video/public/images/sveta.jpg"
		}
	],
	"prompt": "Swap [face] from <<<image_1>>> for [face] from <<<video_1>>>",
	"negative_prompt": "",
	"callback_url": "'.KL_HOOK_URL.'",
	"mode": "std",
	"duration": 5
}');
*/

print_r($result);

/*
[
	[
		['x'=>0.35, 'y'=>0.17],
		['x'=>0.52, 'y'=>0.17],
		['x'=>0.52, 'y'=>0.30],
		['x'=>0.35, 'y'=>0.30]
	],[
		['x'=>0.35, 'y'=>0.17],
		['x'=>0.52, 'y'=>0.17],
		['x'=>0.52, 'y'=>0.30],
		['x'=>0.35, 'y'=>0.30]
	]
]
*/
