<?
abstract class BaseMLStudioClient {

	protected $url;

    private $history = [];
    private $maxLength = 20; // Макс пар вопрос-ответ

    abstract public function Send();

	function __construct($url, $systemPrompt) {
        $this->url = $url;
		$this->addMessage('system', $systemPrompt);
    }

    public function Query($content) {
    	$this->addMessage('user', $content);
    }

    protected function addMessage($role, $content) {
        $this->history[] = ['role' => $role, 'content' => $content];
        $this->trimHistory();
    }
    
    protected function getHistory() {
        return $this->history;
    }
    
    protected function trimHistory() {
        if (count($this->history) > $this->maxLength * 2 + 1) {
            $this->history = array_merge(
                [ $this->history[0] ], // Системное сообщение
                array_slice($this->history, -$this->maxLength * 2)
            );
        }
    }
}
?>