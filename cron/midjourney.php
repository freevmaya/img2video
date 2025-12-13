<?
	require dirname(__DIR__).'/vendor/autoload.php';
	require dirname(__DIR__).'/src/Vmaya/engine.php';

	include(SERVICES_PATH.'APIInterface.php');
	include(SERVICES_PATH.'MidjourneyAPI.php');

	use \App\Services\API\MidjourneyAPI;

	$mj = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH);

	$result = $mj->generateImage("Cyberpunk samurai meditating in a neon-lit, rain-soaked Tokyo alley");

	//$result = $mj->Upscale('abd18130-6e70-4cb2-b652-4804f474d01f', 2);

	print_r(json_encode($result));