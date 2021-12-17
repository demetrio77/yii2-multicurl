<?php
namespace demetrio77\multicurl;

use common\models\Test;

class Curl extends BaseComponent
{
    const MAX_ERROR_SERIES = 100;

    public int $threads = 5;
    public int $timeout = 1;
    public array $options = [];
    public array $headers = [];
    public int $sleepOnErrorSeries = 60;
    public int $maxSleepsOnErrorSeries = 5;
    public int $sleepBetweenAttempts = 0;
    public ?object $proxy = null;

    protected string $proxyUsed;
    private array $map=[];
    private $multiHandler;
    private int $seriesOfErrors = 0;
    private int $calculatedMaxErrorSeries = 0;

    /**
     *
     * @var Session
     */
    private Session $session;

    public function init()
    {
        parent::init();

        if (!empty($this->proxy)) {
            $this->threads = $this->proxy->getMaxThreads($this->threads);
        }

        $this->calculatedMaxErrorSeries = min(self::MAX_ERROR_SERIES, max($this->threads, 25));
    }

    protected array $defaultOptions = [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30
    ];

    /**
     * @param Request $Request
     * @return array
     * @throws Exception
     */
    protected function setOptions(Request $Request): array
    {
        // options for this entire curl object
        $options = $this->defaultOptions + $this->options;

        if (ini_get('safe_mode') === 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }

        // append custom options for this specific request
        if ($Request->options) {
            $options = $Request->options + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $Request->url;

        //User-agent header
        $options[CURLOPT_USERAGENT] = UserAgent::get();

        // posting data with this request?
        if ($Request->postData) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $Request->postData;
        }

        if ($Request->timeout) {
            $options[CURLOPT_CONNECTTIMEOUT] = $Request->timeout;
            $options[CURLOPT_TIMEOUT] = $Request->timeout;
        }

        if ($Request->headers || $Request->cookies) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HEADEROPT] = CURLHEADER_SEPARATE;
            $options[CURLOPT_HTTPHEADER] = $Request->composeHeaderLines();
        }

        if (!empty($this->proxy) && $Request->proxy) {
            if (!method_exists($this->proxy, 'get')
                || !method_exists($this->proxy, 'isPrivate')
                || !method_exists($this->proxy, 'getCredentials')
            ) {
                throw new Exception('Proxy service does not support required method');
            }

            $Request->proxyUsed = $this->proxy->get();
            $options[CURLOPT_PROXY] = $Request->proxyUsed;

            if ($this->proxy->isPrivate()){
                $options[CURLOPT_PROXYUSERPWD] = $this->proxy->getCredentials();
            }
        }

        return $options;
    }

    /**
     * @param Session $Session
     * @param callable|null $callback
     * @return mixed
     * @throws Exception
     */
    public function run( Session $Session, ?callable $callback = null)
    {
        $this->session = $Session;

        if (!$this->session->getCounter()){
            return null;
        }

        if ($this->threads > 1) {
            do {
                $this->multi();
            }
            while ($this->session->getCountReady() > 0);
        } else {
            $this->common();
        }

        if ($callback && is_callable($callback)){
            return call_user_func($callback, $this->session);
        }

        return $this->session;
    }

    /**
     * @throws Exception
     */
    protected function common(): void
    {
        $ch = curl_init();

        while (($data = $this->session->get() )!==null){
            /** @var Request $Request */
            $Request = $data['request'];
            $key = $data['key'];
            $options = $this->setOptions($Request);
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);

            $response = new Response([
                'output'  => $result,
                'info'    => curl_getinfo($ch),
                'request' => $Request,
                'key'     => $key,
                'session' => $this->session
            ]);

            $this->proceedResponse($response);

            if ($this->sleepBetweenAttempts) {
                sleep(rand(floor($this->sleepBetweenAttempts/2), $this->sleepBetweenAttempts));
            }
        }

        curl_close($ch);
    }

    /**
     * @throws Exception
     */
    protected function fillQueue(): void
    {
        $active = count($this->map);

        while( ($active < $this->threads) && ($data = $this->session->get()) !== null ) {
            $Request = $data['request'];

            $ch = curl_init();
            $options = $this->setOptions($Request);
            curl_setopt_array($ch,$options);
            curl_multi_add_handle($this->multiHandler, $ch);
            $chKey = (string) $ch;

            $this->map[$chKey] = $data;
            $active++;
        }
    }

    /**
     * @throws Exception
     */
    protected function multi(): void
    {
        $this->multiHandler = curl_multi_init();
        curl_multi_setopt($this->multiHandler, CURLMOPT_MAXCONNECTS, $this->threads*4);
        $this->fillQueue();

        do {
            while (($execrun = curl_multi_exec($this->multiHandler, $stillRunning)) == CURLM_CALL_MULTI_PERFORM);

            if ($execrun !== CURLM_OK) break;

            // a request was just completed - find out which one
            while ($done = curl_multi_info_read($this->multiHandler, $currentMessagesInQueue)) {
                $ch = $done['handle'];

                // get the info and content returned on the request
                $this->done($ch);

                // start a new request (it's important to do this before removing the old one)
                if ($stillRunning) {
                    $this->fillQueue();
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($this->multiHandler, $ch);
            }

            // Block for data in/output; error handling is done by curl_multi_exec
            if ($stillRunning) {
                curl_multi_select($this->multiHandler, $this->timeout);
            }
        }
        while ($stillRunning);

        curl_multi_close($this->multiHandler);
        $this->map = [];
    }

    /**
     * @param Response $response
     * @return mixed
     */
    protected function proceedResponse(Response $response)
    {
        if ($response->request->proxyUsed) {
            $this->proxy->unlock($response->request->proxyUsed, $response->isOk());
        }

        if ($response->isCurlError()){
            $this->seriesOfErrors ++;

            if ($this->seriesOfErrors > $this->calculatedMaxErrorSeries){
                $this->seriesOfErrors = 0;
                if ($this->sleepOnErrorSeries) {
                    $this->trigger(LogEvent::NAME, new LogEvent([
                        'message' => 'Proxies are unreachable, so the script will be paused for '.$this->sleepOnErrorSeries.' seconds',
                    ]));
                    sleep($this->sleepOnErrorSeries);
                }
            }
        }
        else {
            $this->seriesOfErrors = 0;
        }

        if ($response->isOk()) {
            return $this->session->setResult($response->key, $response->success());
        }

        if (($response->isToUpdateRequest() || $response->isNotExpected() || $response->isCurlError()) && $response->hasAttempt()) {
            return $this->session->update($response->key, $response->request);
        }

        return $response->error();
    }

    /**
     * @param $ch
     * @return mixed
     */
    protected function done($ch)
    {
        $chKey = (string)$ch;
        $data = $this->map[$chKey];
        unset($this->map[$chKey]);

        $response = new Response([
            'output'  =>  curl_multi_getcontent($ch),
            'info'    =>  curl_getinfo($ch),
            'request' =>  $data['request'],
            'key'     =>  $data['key'],
            'session' =>  $this->session,
        ]);

        return $this->proceedResponse($response);
    }
}
