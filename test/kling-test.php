<?
declare(ticks = 1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Vmaya/engine.php';

use App\Services\API\KlingApi;

$api = new KlingApi(KL_ACCESS_KEY, KL_SECRET_KEY, null);

$videoURL = BASEURL.'/videos/Multi-Image-01.mp4';
echo $videoURL."\n";

$api->prepareVideoMultiElement('840792910093221947', [
	[
		['x'=>0.41, 'y'=>0.24],		
		['x'=>0.41, 'y'=>0.24]
	]
]);

$api->baseRequest('https://api-singapore.klingai.com/v1/videos/multi-elements/', json_decode('{
	"model_name": "kling-v1-6",
	"session_id": "",
	"edit_mode": "swap",
	"image_list": [
		{
			"image":"image_url"
		}
	],
	"prompt": "",
	"negative_prompt": "",
	"mode": "std",
	"duration": 5
}'));

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
