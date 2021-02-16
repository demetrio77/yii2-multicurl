<?php 

namespace clcagency\scraper\components;

class Process
{
    private $pid;

    public function __construct($pid=0)
    {
        if ($this->pid) {
            $this->pid = $pid;
        }
    }
    
    public static function run($command)
    {
        $command = 'nohup '.$command.' > /dev/null 2>&1 & echo $!';
        exec($command ,$op);
        $pid = (int)$op[0];
        return $pid;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function status()
    {
        $command = 'ps -p '.$this->pid;
        exec($command,$op);
        return isset($op[1]);
    }

    public function stop()
    {
        $command = 'kill '.$this->pid;
        exec($command);
        return !$this->status();        
    }
}