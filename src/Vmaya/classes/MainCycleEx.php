<?

use \Telegram\Bot\FileUpload\InputFile;
use \Telegram\Bot\Exceptions\TelegramResponseException;
use App\Services\API\cycle\MjCycle;
use App\Services\API\cycle\KlingCycle;
use App\Services\API\cycle\LeoCycle;

class MainCycleEx extends TelegramClient {

    private $lastMessageId;
    protected $user;
    protected $modelTask;
    protected $processors;
    protected $transactionModel;
    public $downloadClient;

    public function __construct($api, $file_settings = null)
    {
        parent::__construct($api, $file_settings);
        $this->modelTask    = new TaskModel();
        $this->transactionModel = new TransactionsModel();

        $this->processors 	= [
        	//'mj' => new MjCycle($this, $this->modelTask, new MJModel()),
        	'kling' => new KlingCycle($this, $this->modelTask, new KlingModel()),
            'leo' => new LeoCycle($this, $this->modelTask, new LeoTasksModel())
        ];

        $this->downloadClient = new DownloadClient();
    }

    public function ModelTask() {
        return $this->modelTask;
    }

    public function Update() {

        try {
            $tasks = $this->modelTask->getItems(['state'=>'active']);
            if (count($tasks) > 0) {
                foreach ($tasks as $task) {
                    $this->updateTask($task);
                }
            }

        } catch (Exception $e) {
            trace_error($e->getMessage());
        }

        $this->downloadClient->Run();
    }

    protected function updateTask($task) {
        if ($this->user = (new TGUserModel())->getItem($task['user_id'])) {
            $this->initLang($this->user['language_code']);
            $this->readSession($task['chat_id']);
            
            $idDo = false;
            foreach ($this->processors as $key=>$processor)
                if ($key == $task['service']) {
                    $processor->doServiceAction($task);
                    $idDo = true;
                    break;
                }

            if (!$idDo) $this->finishTask($task, 'failure');

            if ($this->isSessionChanged()) $this->saveSession();
        }
    }

    public function finishTask($task, $state='finished') {        
        $this->modelTask->Update([
            'id'=>$task['id'], 'state'=>$state
        ]);

        trace("finish task {$task['id']}: {$state}");
    }

    public function PayUpscale($user_id, $data) {
        $this->transactionModel->PayUpscale($user_id, $data);
    }

    public function PayVideo($user_id, $data) {
        $this->transactionModel->PayVideo($user_id, $data);
    }

    public function error($error) {
        $this->Message(ADMIN_USERID, $error);
    }
}