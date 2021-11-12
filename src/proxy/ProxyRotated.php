<?php
namespace demetrio77\multicurl\proxy;

class ProxyRotated extends BaseProxy
{
    /**
     * @var array
     */
    protected array $proxies = [];

    /**
     * @var array
     */
    protected array $free = [];

    /**
     *
     */
    public function init()
    {
        parent::init();

        $this->free = $this->proxies;
    }

    /**
     * @return string
     */
    public function get(): string
    {
        return array_shift($this->free);
    }

    /**
     * @param string $proxy
     * @param bool $isOk
     */
    public function unlock(string $proxy, bool $isOk = false): void
    {
        $this->free[] = $proxy;
    }

    /**
     * @param int $threads
     * @return int
     */
    public function getMaxThreads(int $threads): int
    {
        return min($threads, count($this->proxies));
    }
}
