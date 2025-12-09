<?

class ProcessLock {
    private $lockFile;
    private $pid;
    
    public function __construct($lockFile) {
        $this->lockFile = $lockFile;
        $this->pid = getmypid();
    }
    
    public function acquire() {
        // Проверяем существующий lock
        if (file_exists($this->lockFile)) {
            $existingPid = (int) file_get_contents($this->lockFile);
            
            // Проверяем, жив ли процесс
            if ($this->isProcessRunning($existingPid)) {
                return false; // Процесс уже запущен
            }
            
            // Процесс умер, удаляем старый lock
            unlink($this->lockFile);
        }
        
        // Создаем новый lock
        return file_put_contents($this->lockFile, $this->pid) !== false;
    }
    
    public function release() {
        if (file_exists($this->lockFile)) {
            $currentPid = (int) file_get_contents($this->lockFile);
            if ($currentPid === $this->pid) {
                unlink($this->lockFile);
            }
        }
    }
    
    private function isProcessRunning($pid) {
        if (PHP_OS === 'Linux' || PHP_OS === 'Darwin') {
            // Для Linux/Mac
            return posix_kill($pid, 0);
        } elseif (PHP_OS === 'WINNT') {
            // Для Windows
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
            return count($output) > 1;
        }
        return false;
    }
    
    public function __destruct() {
        $this->release();
    }
}