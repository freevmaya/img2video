<?
	require __DIR__ . '/vendor/autoload.php';
	require __DIR__ . '/src/Vmaya/engine.php';

	include('src/Vmaya/services/APIInterface.php');
	include('src/Vmaya/services/MidjourneyAPI.php');

	use \App\Services\API\MidjourneyAPI;

	$mj = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_TOKEN);

	$result = $mj->generateImage("A cinematic photorealistic portrait of an old Norwegian sailor with a grey beard and wrinkled face, wearing a raincoat");

	print_r(json_encode($result));