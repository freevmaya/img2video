<?
	$url = "https://cdn.midjourney.com/465acea3-64cc-45ab-81dd-501533b489f5/0_0.png";
	$file_path = "/home/vmaya/www/img2video/downloads/1.png";
	$output = null;
    $command = 'python3 '.BASEPATH."scraper_download.py \"{$url}\" \"{$file_path}\"";

    exec($command, $output);
    
    print_r($command."\\nResult: ".json_encode($output));