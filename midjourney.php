<?
	require __DIR__ . '/vendor/autoload.php';
	require __DIR__ . '/src/Vmaya/engine.php';

	include('src/Vmaya/services/APIInterface.php');
	include('src/Vmaya/services/MidjourneyAPI.php');

	use \App\Services\API\MidjourneyAPI;

	$mj = new MidjourneyAPI(MJ_APIKEY, MJ_HOOK_URL, MJ_ACCOUNTHASH);

	//$result = $mj->generateImage("A majestic white wolf with blue eyes standing on a cliff under the aurora borealis");

	$result = $mj->Upscale('abd18130-6e70-4cb2-b652-4804f474d01f', 2);

	print_r(json_encode($result));