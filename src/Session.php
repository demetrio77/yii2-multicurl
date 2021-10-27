<?php

namespace demetrio77\multicurl;

/**
 *
 * @author dk
 * @property int $counter the number of requests
 * @property int $countReady the number of ready to curl requests
 */
class Session extends BaseComponent
{
    /**
     * Whether to mix or not requests
     * @var boolean
     */
    public bool $shuffle = false;

    /**
     * All requests of session
     * @var Request[]
     */
    public array $requests = [];

    /**
     * The keys of requests not already been used
     * @var int[]
     */
    private array $preparedRequests = [];

    /**
     * The counter of requests
     * @var int
     */
    private int $counter = 0;

    /**
     * @return int
     */
    public function getCounter(): int
    {
        return $this->counter;
    }

    /**
     * @return int
     */
    public function getCountReady(): int
    {
        return count($this->preparedRequests);
    }

    /**
     * @param Request $Request
     */
    public function add(Request $Request): void
    {
        $this->requests[] = [
            'request' => $Request,
            'response' => null
        ];

        $this->preparedRequests[] = $this->counter++;
    }

    /**
     * @param int $key
     * @param Request $Request
     * @return bool
     */
    public function update(int $key, Request $Request): bool
    {
        $this->requests[$key] = [
            'request' => $Request,
            'response' => null
        ];

        if (!in_array($key, $this->preparedRequests)){
            $this->preparedRequests[] = $key;
        }

        return true;
    }

    /**
     * @return array|null
     */
    public function get(): ?array
    {
        if (!$this->getCountReady()) {
            return null;
        }

        if ($this->shuffle) {
            $v = array_rand($this->preparedRequests);
            $key = $this->preparedRequests[$v];
            unset($this->preparedRequests[$v]);
        }
        else {
            $key = array_shift($this->preparedRequests);
        }

        return [
            'request' => $this->requests[$key]['request'],
            'key' => $key
        ];
    }

    /**
     * @param int $key
     * @param $data
     * @return bool
     */
    public function setResult(int $key, $data): bool
    {
        if (!isset($this->requests[$key])){
            return false;
        }

        $this->requests[$key]['response'] = $data;
        return true;
    }
}
