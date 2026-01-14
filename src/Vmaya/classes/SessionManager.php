<?

class SessionManager {
    private $session;
    private $sessionChanged;
    private $sessionModel;

	function __construct() {
        $this->initialize();
    }

    protected function isSessionChanged() {
    	return $this->sessionChanged;
    }

    protected function initialize() {
        $this->sessionModel = new SessionsModel();
    }

    protected function setSession($name, $value) {
        $this->session[$name] = $value;
        $this->sessionChanged = true;
    }

    protected function saveSession($chatId) {
    	trace($this->session);
        $this->sessionModel->Update([
            'chat_id' => $chatId,
            'data' => json_encode($this->session, JSON_FLAGS)
        ], 'chat_id');
    }

    protected function readSession($chatId) {
        $result = [];

        if ($chatId) {
	        if ($item = $this->sessionModel->getItem($chatId, 'chat_id'))
	            $result = json_decode($item['data'], true);
	        else $this->sessionModel->Update(['chat_id'=>$chatId, 'data'=>'{}']);

	        if ($chatId != ADMIN_USERID)
	            trace("Attempt read session: {$chatId}. Result: ".json_encode($item, JSON_FLAGS));
	    }

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