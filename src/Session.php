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
    public $shuffle = false;

    /**
     * All requests of session
     * @var Request[]
     */
    public $requests;

    /**
     * The keys of requests not already been used
     * @var int[]
     */
    private $preparedRequests = [];

    /**
     * The counter of requests
     * @var int
     */
    private $counter = 0;

    public function getCounter()
    {
        return $this->counter;
    }

    public function getCountReady()
    {
        return count($this->preparedRequests);
    }

    public function add($Request)
    {
        $this->requests[] = [
            'request' => $Request,
            'response' => null
        ];

        $this->preparedRequests[] = $this->counter++;
    }

    public function update($key, $Request)
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

    public function get()
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

    public function setResult($key, $data)
    {
        if (!isset($this->requests[$key])){
            return false;
        }

        $this->requests[$key]['response'] = $data;
        return true;
    }
}
