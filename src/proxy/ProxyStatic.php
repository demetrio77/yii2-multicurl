<?php

namespace demetrio77\multicurl\proxy;

class ProxyStatic extends BaseProxy
{
    public string $address;

    /**
     * @return string
     */
    public function get(): string
    {
        return $this->address;
    }

    /**
     * @param int $threads
     * @return int
     */
    public function getMaxThreads(int $threads): int
    {
        return $threads;
    }

    /**
     * @param string $proxy
     */
    public function unlock(string $proxy)
    {
        //Nothing happened needed
    }
}
