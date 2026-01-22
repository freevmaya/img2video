<?php
namespace App\Services\API;

abstract class BaseApi implements APIInterface
{
    private $defaultModels;
    private $modelList;
    private $defaultModel;
    protected $bot;

    public function __construct($modes_file = null, $bot = null)
    {
    	if (!empty($modes_file)) {
    		$data = json_decode(file_get_contents(__DIR__."/models/{$modes_file}.json"), true);
    		$this->modelList 	    = $data['list'];
            $this->defaultModels    = $data['defaultModelName'];
	        $this->defaultModel    = $data['default'];
    	}
        $this->bot = $bot;
    }

    public function getModelUrl($type, $model_name = null) {
        $model_name = empty($model_name) ? $this->getDefaultModelName($type) : $model_name;

        return isset($this->modelList[$model_name]['url']) ? $this->modelList[$model_name]['url'] : $this->defaultUrl();
    }

    public function getDefaultModelName($type) {
        return isset($this->defaultModels[$type]) ? $this->defaultModels[$type] : false;
    }

    public function AccountInfo() {
        return [];
    }

    protected function makeRequest($url, $data, $preset_name=null, $task_data = null) {
        return null;
    }

    protected function requireTranslate($info) {
        return true;
    }

    protected function prepareTaskData($task_data, $model_name, $preset_name = null) {
        $task_data = array_merge(['model'=>$model_name], $task_data ? $task_data : []);
        if ($preset_name) $task_data['preset'] = $preset_name;
        return $task_data;
    }

    public function Generate($type, $images, $prompt, $model_name = null, $task_data = null)
    {
        $result = false;
        $data   = $this->PrepareRequestData($type, $images, $prompt, $model_name);

        if ($data) {
            $model_name = empty($model_name) ? $this->getDefaultModelName($type) : $model_name;

            $url = $this->getModelUrl($type, $model_name);
            $result = $this->makeRequest($url, $data, null, $this->prepareTaskData($task_data, $model_name));
        }
        return $result;
    }

    public function GeneratePreset($model_name, $preset_name, $presetOptions, $images, $task_data = null)
    {
        $result     = false;

        if ($options = $this->preparePresetOptions($model_name, $presetOptions, $images)) {
            $info    = $this->getModelInfo($model_name);
            if ($info) {
                
                $result = $this->makeRequest($info['url'], $options, $preset_name, $this->prepareTaskData($task_data, $model_name, $preset_name));
            }
        }
        return $result;
    }

    protected function preparePresetOptions($model_name, $presetOptions, $images) {

        $result = array_merge([], $this->defaultModel[$model_name], $presetOptions);

        if ($this->preparePresetImages($result, $images))
            return $result;
        else return false;
    }

    protected function prepareImage($value) {
        return $value;
    }

    protected function preparePresetImages(&$presetOptions, $images) {
        if (!empty($images))
            for ($i=0; $i<count($images); $i++)
                if ($image_id = $this->prepareImage($images[$i]['value']))
                    setArrayValueByPath($presetOptions, $images[$i]['path'], $image_id);
                else return false;
                
        return $presetOptions;
    }

    protected function prepareDefaultOptions($data, $model_name, $images, $prompt) {

        $info = $this->getModelInfo($model_name);
        if ($this->requireTranslate($info))
            $prompt = checkRusAndTranslate($prompt);

        if (!$this->setPrompt($model_name, $data, $prompt)) {
            trace_error("Error set prompt model: '{$model_name}'");
            return false;
        }
        
        if (!$this->setImages($model_name, $data, $images)) {
            trace_error("Error set images model: '{$model_name}'");
            return false;
        }

        return $data;
    }

    public function PrepareRequestData($type, $images, $prompt, $model_name = null) {
        $model_name = empty($model_name) ? $this->getDefaultModelName($type) : $model_name;

        if (!empty($model_name) && isset($this->defaultModel[$model_name])) {
            $info = $this->getModelInfo($model_name);
            if (!$info['enabled']) {
                trace_error("Model disabled: {$model_name}");
                return false;
            }

            $defaultOptions = isset($this->defaultModel[$model_name]) ? $this->defaultModel[$model_name] : null;
            if ($defaultOptions) {
                return $this->prepareDefaultOptions(array_merge([], $defaultOptions), $model_name, $images, $prompt);

                /*
                $data = array_merge([], $defaultOptions);

                if ($this->requireTranslate($info))
                    $prompt = checkRusAndTranslate($prompt);

                if (!$this->setPrompt($model_name, $data, $prompt)) {
                    trace_error("Error set prompt model: '{$model_name}'");
                    return false;
                }
                
                if (!$this->setImages($model_name, $data, $images)) {
                    trace_error("Error set images model: '{$model_name}'");
                    return false;
                }

                return $data;*/
            } else trace_error("Not found default options for model: '{$model_name}'");
        } else trace_error("Unknown model: {$model_name}");
        return false;
    }

    protected function setImages($model_name, &$options, $images) {

        if (count($images) == 0)
            return false;

        foreach ($options as $key=>$rec)
            if ($key == 'image') {
                $options['image'] = $images[0];
                return true;
            }
            else if (is_array($rec)) {
                if ($this->setImages($model_name, $options[$key], $images))
                    return true;
            }
        return false;
    }

    protected function setPrompt($model_name, &$options, $prompt) {
        foreach ($options as $key=>$rec)
            if ($key == 'prompt') {
                $options['prompt'] = $prompt;
                return true;
            }
            else if (is_array($rec)) {
                if ($this->setPrompt($model_name, $options[$key], $prompt))
                    return true;
            }
        return false;
    }

    public function getModelInfo($model_name) {
        return $this->hasModel($model_name) ? $this->modelList[$model_name] : null;
    }

    public function hasModel($model_name) {
        return isset($this->modelList[$model_name]);
    }

    public function getDefaultOptions($model_name) {
        return $this->hasModel($model_name) ? $this->defaultModel[$model_name] : null;
    }

    public function getActualyModelsInfo($enabledOnly = true) {
        return $enabledOnly ? array_filter($this->modelList, function($model) {
        	return $model['enabled'];
        }) : array_merge([], $this->modelList);
    }

    public function getModelOptions($model_name) {
    	return isset($this->defaultModel[$model_name]) ? $this->defaultModel[$model_name] : [];
    }

    public function Answer($content) {
        if ($this->bot)
            $this->bot->Answer(null, $content);
    }

    public function SendToAdmin($content) {
        if ($this->bot)
            $this->bot->Answer(ADMIN_USERID, $content);
    }

    public function Wrong() {
        if ($this->bot)
            $this->bot->Wrong();
    }
}