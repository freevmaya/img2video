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

    protected function setSession($name, $value) {
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

    protected function readSession($sessionId) {
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

    protected function hasSession($name) {
        return isset($this->session[$name]);
    }

    protected function getSession($name, $default = false) {
        return $this->hasSession($name) ? $this->session[$name] : $default;
    }

    protected function unsetSessions($names) {
        foreach ($names as $name)
            if (isset($this->session[$name])) {
                unset($this->session[$name]);
                $this->sessionChanged = true;
            }
    }

    protected function popSession($name) {

        $result = null;       
        if (isset($this->session[$name])) {
            $result = $this->session[$name];
            unset($this->session[$name]);
            $this->sessionChanged = true;
        }

        return $result;
    }
}