<?php

namespace demetrio77\multicurl;

class Process
{
    /**
     * @var string
     */
    private string $pid;

    /**
     * @param int $pid
     */
    public function __construct(int $pid = 0)
    {
        $this->pid = $pid;
    }

    /**
     * @param string $command
     * @return int
     */
    public static function run(string $command): int
    {
        $command = 'nohup '.$command.' > /dev/null 2>&1 & echo $!';
        exec($command ,$op);
        return (int)$op[0];
    }

    /**
     * @return int
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * @return bool
     */
    public function status(): bool
    {
        $command = 'ps -p '.$this->pid;
        exec($command,$op);
        return isset($op[1]);
    }

    /**
     * @return bool
     */
    public function stop(): bool
    {
        $command = 'kill '.$this->pid;
        exec($command);
        return !$this->status();
    }
}
