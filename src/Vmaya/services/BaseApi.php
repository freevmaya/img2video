<?php
namespace App\Services\API;

abstract class BaseApi implements APIInterface
{
    protected $models;

    public function __construct($modes_file = null)
    {
    	if (!empty($modes_file)) {
	        $this->models = json_decode(file_get_contents(__DIR__."/models/{$modes_file}.json"), true);
    	}
    }

    public function getModels() {
        return $this->models;
    }

    public function getModelOptions($model_name) {
    	return isset($this->models[$model_name]) ? $this->models[$model_name] : [];
    }

    public function setModelPrompt($model_name, $prompt) {
    	if (!empty($prompt) && isset($this->models[$model_name])) {
    		if (BaseApi::SetPrompt($this->models[$model_name], $prompt))
    			return $this->models[$model_name];
    	}
    	return false;
    }

    public static function SetPrompt(&$list, $prompt) {
    	foreach ($list as $key=>$rec)
    		if ($key == 'prompt') {
    			$list['prompt'] = $prompt;
    			return true;
    		}
    		else if (is_array($rec)) {
    			if (BaseApi::SetPrompt($list[$key], $prompt))
    				return true;
    		}
    	return false;
    }
}