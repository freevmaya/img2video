<?
	$url = "https://cdn.midjourney.com/video/043f5f8c-913e-45a5-a87e-9efc8bc866ef/2.mp4";
	$file_path = "/home/vmaya/www/img2video/downloads/2.mp4";
	$output = null;
    $command = "python3 scraper_download.py \"{$url}\" \"{$file_path}\"";

    exec($command, $output);
    
    print_r($command."\\nResult: ".json_encode($output));