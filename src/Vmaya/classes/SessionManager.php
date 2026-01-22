<?

class SessionManager {
    private $session;
    private $sessionChanged;
    private $sessionModel;
    private $sessionId;

	function __construct() {
        $this->initialize();
    }

    protected function isSessionChanged() {
    	return $this->sessionChanged;
    }

    protected function initialize() {
        $this->sessionModel = new SessionsModel();
    }

    public function sessionId() {
        return $this->sessionId;
    }

    protected function checkSessionId($sessionId) {
        if ($sessionId && ($sessionId != $this->sessionId)) {

            if ($this->isSessionChanged())
                $this->saveSession();

            $this->readSession($sessionId);
            return true;
        }
        return false;
    }

    public function setSession($name, $value) {
        $this->session[$name] = $value;
        $this->sessionChanged = true;
    }

    protected function saveSession() {
        if ($this->sessionId)
            $this->sessionModel->Update([
                'chat_id' => $this->sessionId,
                'data' => json_encode($this->session, JSON_FLAGS)
            ], 'chat_id');
    }

    public function readSession($sessionId) {
        $result = [];

        if ($sessionId) {
	        if ($item = $this->sessionModel->getItem($sessionId, 'chat_id'))
	            $result = json_decode($item['data'], true);
	        else $this->sessionModel->Update(['chat_id'=>$sessionId, 'data'=>'{}']);
	    }

        $this->sessionId = $sessionId;
    	$this->sessionChanged = false;
    	$this->session = $result;
    }

    public function hasSession($name) {
        return isset($this->session[$name]);
    }

    public function getSession($name, $default = false) {
        return $this->hasSession($name) ? $this->session[$name] : $default;
    }

    public function unsetSessions($names) {
        foreach ($names as $name)
            if (isset($this->session[$name])) {
                unset($this->session[$name]);
                $this->sessionChanged = true;
            }
    }

    public function popSession($name) {

        $result = null;       
        if (isset($this->session[$name])) {
            $result = $this->session[$name];
            unset($this->session[$name]);
            $this->sessionChanged = true;
        }

        return $result;
    }

    public function pushImage($image_id) {
        if (!($images = $this->getSession('images', []))) $images = [];
        $images[] = $image_id;
        $this->setSession('images', $images);
    }

    public function getImagesUrl() {
        $images = array_reverse(array_values($this->getSession('images', [])));
        $images_url = [];
        if (!empty($images))
            foreach ($images as $image_id)
                $images_url[] = $this->GetFileUrl($image_id);
        return $images_url;
    }
}