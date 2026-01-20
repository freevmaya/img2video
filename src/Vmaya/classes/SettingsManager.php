<?

class SettingsManager extends SessionManager {
    private $file_settings;
    private $time_settings;
    protected $settings;
    protected $settingsChange;

	function __construct($file_settings = null) {
		parent::__construct();
        $this->openSettings($file_settings);
    }

    protected function openSettings($file_settings)
    {
        if (!empty($file_settings) && is_string($file_settings)) {
            $this->file_settings = $file_settings;
            if (file_exists($file_settings)) {
                $this->setSettingsAll(json_decode(file_get_contents($this->file_settings), true));
                $this->time_settings = filemtime($this->file_settings);
            } else if (empty($this->settings)) {
                $this->setSettingsAll($this->getDefaultSettings());
            }
        } else trace_error("file_settings must be string, but given ".get_class($file_settings));
    }

    public function setSettingsAll($settings)
    {
        $this->settings = $settings;
        $this->time_settings = time();

        trace("Set settings: ".json_encode($settings, JSON_FLAGS));
    }

    public function getDefaultSettings()
    {
        return ['messageIndex' => 0, 'lastUpdateId' => 0, 'update_timeout' => 10, 'client_timeout' => 20];
    }

    public function getSetting($param_name, $default_value = null) {
        if (isset($this->settings[$param_name]))
            return $this->settings[$param_name];
        return $default_value;
    }

    public function setSetting($param_name, $value) {
        $this->settings[$param_name] = $value;
        $this->settingsChange = true;
    }

    protected function saveSettings() {
        if (!empty($this->file_settings) && !empty($this->settings)) {
            file_put_contents($this->file_settings, json_encode($this->settings, JSON_FLAGS));
            $this->time_settings = filemtime($this->file_settings);
            $this->settingsChange = false;
        }
    }

    public function checkAndUpdateSettings() {
        
        if (file_exists($this->file_settings) &&
            (filemtime($this->file_settings) != $this->time_settings))
            $this->openSettings($this->file_settings);
    }

    public function messageIndex() {
        return $this->getSetting('messageIndex', 0);
    }

    public function pushMessageImage($message_id, $image_id) {
        if (!($images = $this->getSession('messageImages'))) $images = [];

        array_add_limit($images, $message_id, $image_id, 10);
        $this->setSession('messageImages', $images);
    }

    public function pushMessageVideo($message_id, $file_id) {
        if (!($list = $this->getSession('messageVideos'))) $list = [];

        array_add_limit($list, $message_id, $file_id, 10);
        $this->setSession('messageVideos', $list);
    }

    public function getMessageImageUrl($message_id) {
        $images = $this->getSession('messageImages');
        if (DEV) {
            echo $message_id."\n";
        }

        if (isset($images[$message_id])) {

            return $this->GetFileUrl($images[$message_id]);
        }
        return null;
    }

    protected function afterSend($sendResult, $saveSessionImmediately = false) {

        if (isset($sendResult['message_id'])) {

        	$this->checkSessionId($sendResult['chat']['id']);

        	if ($this->sessionId()) {

	            $this->setSession('lastBotMessageId', $sendResult['message_id']);
	            $messageIndex = $this->messageIndex();

	            $history = $this->getSession('history', []);
	            if (!isAssoc($history)) $history = [];

	            array_add_limit($history, $messageIndex, $sendResult['message_id'], 20);

	            if (DEV) 
	                echo "Index: {$messageIndex}, MessageID: {$sendResult['message_id']}\n";

                if ($photos = $sendResult->getPhoto()) 
                    $this->pushMessageImage($messageIndex, $photos->last()->getFileId());

                if ($video = $sendResult->getVideo()) 
                    $this->pushMessageVideo($messageIndex, $video->getFileId());

	            $this->setSession('history', $history);
	            $this->setSetting('messageIndex', $messageIndex + 1);

	            if ($saveSessionImmediately) 
	            	$this->saveSession();
	            
	        } else trace_error('Empty session id');
        }

        return $sendResult;
    }

    public function applyPreset($pathImage, $preset_name) {
        $result = true;
        $preset = $this->getPreset($preset_name);
        if ($preset && isset($preset['overlays'])) {
            foreach ($preset['overlays'] as $overlayRelPath) {
                $result = $result &&
                    overlayImage(
                        $pathImage,
                        BASEPATH.$overlayRelPath,
                        $pathImage,
                        [
                            'scale_factor' => 1,
                            'position' => 'center',
                            'output_quality' => 95,
                        ]
                    );
            }
        }
        return $result;
    }

    protected function getPreset(&$presetName) {
        $presets = json_decode(file_get_contents(BASEPATH.'data/presets.json'), true);
        if (!empty($presetName))
            return isset($presets[$presetName]) ? $presets[$presetName] : null;
        else {
            $keys = array_keys($presets);
            if (count($keys) > 0) {
                $presetName = $keys[0];
                return $presets[$keys[0]];
            }
        }
        return null;
    }

    public function closeMessageButton($eng_caption = 'Cancel') {
        return ['text'=>Lang($eng_caption), 'callback_data' => "deleteMessage.{$this->messageIndex()}"];
    }
}