<?php

namespace demetrio77\multicurl;

use demetrio77\multicurl\proxy\ProxyStatic;

class Curl extends BaseComponent
{
    const MAX_ERROR_SERIE = 100;

    public $threads = 5;
    public $timeout = 1;
    public $options = [];
    public $proxy = true;
    public $headers = [];
    public $sleepOnErrorSerie = 60;
    public $maxSleepsOnErrorSeries = 5;
    public $sleepBetweenAttempts = 0;

    private $map=[];
    private $multiHandler;
    private $serieOfErrors=0;
    private $calculatedMaxErrorSerie = 0;
    private $proxyService;

    /**
     *
     * @var Session
     */
    private $session;

    public function init()
    {
        parent::init();

        $this->calculatedMaxErrorSerie = min(self::MAX_ERROR_SERIE, max($this->threads, 25));

        if ($this->proxy && isset(\Yii::$app->params['proxy'])) {
            $className = \Yii::$app->params['proxy']['className'] ?? ProxyStatic::class;
            $options   =\Yii::$app->params['proxy']['options'] ?? [];
            $this->proxyService = new $className($options);
        }
    }

    protected $defaultOptions = [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30
    ];

    protected function setOptions(Request $Request)
    {
        // options for this entire curl object
        $options = $this->defaultOptions + $this->options;

        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
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

        if ($Request->timeout){
            $options[CURLOPT_CONNECTTIMEOUT] = $Request->timeout;
            $options[CURLOPT_TIMEOUT] = $Request->timeout;
        }

        if ($Request->headers || $Request->cookies) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HEADEROPT] = CURLHEADER_SEPARATE;
            $options[CURLOPT_HTTPHEADER] = $Request->composeHeaderLines();
        }

        if ($this->proxy!==false && $Request->proxy) {
            $Request->proxyUsed = $this->proxyService->get();
            $options[CURLOPT_PROXY] = $Request->proxyUsed;

            if ($this->proxyService->isPrivate()){
                $options[CURLOPT_PROXYUSERPWD] = $this->proxyService->getCredentials();
            }
        }

        return $options;
    }

    public function run( Session $Session, $callback = null)
    {
        $this->session = $Session;

        if (!$this->session->getCounter()){
            return null;
        }

        if ($this->proxy !== false) {
            $this->proxyService->start($this->threads);
        }

        if ($this->threads > 1) {
            do {
                $this->multi();
            }
            while ($this->session->getCountReady() > 0);
        } else {
            $this->common();
        }

        if ($this->proxy !== false ) {
            $this->proxyService->end();
        }

        if ($callback && is_callable($callback)){
            return call_user_func($callback, $this->session);
        }

        return $this->session;
    }

    protected function common()
    {
        $ch = curl_init();

        while (($data = $this->session->get() )!==null){
            $Request = $data['request'];
            $key = $data['key'];
            $options = $this->setOptions($Request);
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);

            $response = new Response([
                'output' => $result,
                'info'   => curl_getinfo($ch),
                'request' => $Request,
                'key'    => $key,
                'session' => $this->session
            ]);

            $this->proceedResponse($response);
            if ($this->sleepBetweenAttempts) {
                sleep(rand(floor($this->sleepBetweenAttempts/2), $this->sleepBetweenAttempts));
            }
        }

        curl_close($ch);
        return true;
    }

    protected function fillQueue()
    {
        $active = count($this->map);

        while( ($active<$this->threads) && ($data = $this->session->get())!==null){
            $Request = $data['request'];
            $key = $data['key'];

            $ch = curl_init();
            $options = $this->setOptions($Request);
            curl_setopt_array($ch,$options);
            curl_multi_add_handle($this->multiHandler, $ch);
            $chKey = (string) $ch;

            $this->map[$chKey] = $data;
            $active++;
        }
    }

    protected function multi()
    {
        $this->multiHandler = curl_multi_init();
        curl_multi_setopt($this->multiHandler, CURLMOPT_MAXCONNECTS, $this->threads*4);
        $this->fillQueue();

        do {
            while(($execrun = curl_multi_exec($this->multiHandler, $stillRunning)) == CURLM_CALL_MULTI_PERFORM);

            if ($execrun != CURLM_OK) break;

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
        return true;
    }

    protected function proceedResponse(Response $response)
    {
        if ($response->request->proxyUsed){
            $this->proxyService->unlock($response->request->proxyUsed);
        }

        if ($response->isOk()) {
            return $this->session->setResult($response->key, $response->success());
        }

        if ($response->isCurlError()){
            $this->serieOfErrors++;

            if ($this->serieOfErrors > $this->calculatedMaxErrorSerie){
                $this->serieOfErrors = 0;
                if ($this->sleepOnErrorSerie) {
                    $this->trigger(LogEvent::NAME, new LogEvent([
                        'message' => 'Proxies are unreachable, so the script will be paused for '.$this->sleepOnErrorSerie.' seconds',
                    ]));
                    sleep($this->sleepOnErrorSerie);
                }
            }
        }
        else {
            $this->serieOfErrors = 0;
        }

        if ($response->isToUpdateRequest() || $response->isNotExpected() || $response->hasAttempt()) {
            return $this->session->update($response->key, $response->request);
        }

        return $response->error();
    }

    protected function done($ch, $multiCurl=true, $result='')
    {
        $chKey = (string)$ch;
        $data = $this->map[$chKey];
        unset($this->map[$chKey]);

        $response = new Response([
            'output' => curl_multi_getcontent($ch),
            'info'   => curl_getinfo($ch),
            'request' => $data['request'],
            'key'    => $data['key'],
            'session' => $this->session
        ]);

        return $this->proceedResponse($response);
    }
}
