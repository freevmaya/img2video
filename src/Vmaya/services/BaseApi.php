<?php
namespace App\Services\API;

abstract class BaseApi implements APIInterface
{
    private $modelList;
    private $defaultModel;
    protected $bot;

    public function __construct($modes_file = null, $bot = null)
    {
    	if (!empty($modes_file)) {
    		$data = json_decode(file_get_contents(__DIR__."/models/{$modes_file}.json"), true);
    		$this->modelList 	= $data['list'];
	        $this->defaultModel = $data['default']; 
    	}
        $this->bot = $bot;
    }

    public function getModelUrl($model_name) {
        return isset($this->modelList[$model_name]['url']) ? $this->modelList[$model_name]['url'] : $this->defaultUrl();
    }

    public function defaultUrl() {
        return "";
    }

    public function getModels() {
        return array_filter($this->modelList, function($model) {
        	return $model['enabled'];
        });
    }

    public function getModelOptions($model_name) {
    	return isset($this->defaultModel[$model_name]) ? $this->defaultModel[$model_name] : [];
    }

    public function setModelPrompt($model_name, $prompt) {
    	if (!empty($prompt) && isset($this->defaultModel[$model_name])) {
    		if (BaseApi::SetPrompt($this->defaultModel[$model_name], $prompt))
    			return $this->defaultModel[$model_name];
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

    public function Answer($content) {
        if ($this->bot)
            $this->bot->Answer(null, $content);
    }

    public function Wrong() {
        if ($this->bot)
            $this->bot->Wrong();
    }
}