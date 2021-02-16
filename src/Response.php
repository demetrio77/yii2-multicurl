<?php

namespace demetrio77\multicurl;

use yii\helpers\Json;

class Response extends BaseComponent
{
    const EXPECT_HTML = 1;
    const EXPECT_XML = 2;
    const EXPECT_JSON = 3;
    const EXPECT_EXACT = 4;

    const STATUS_CURL_ERROR = -1;
    const STATUS_HTTP_ERROR = 0;
    const STATUS_OK = 1;
    const STATUS_NOT_EXPECTED = -2;
    const STATUS_TO_UPDATE = 2;

    /**
     *
     * @var array
     */
    public $info;

    /**
     *
     * @var Request
     */
    public $request;

    /**
     *
     * @var string
     */
    public $output;

    /**
     *
     * @var string
     */
    public $raw;

    /**
     *
     * @var int
     */
    public $key;

    /**
     *
     * @var int
     */
    public $status;

    /**
     *
     * @var Session;
     */
    public $session;

    public $errorCode = null;

    public function init()
    {
        parent::init();

        if (!isset($this->info['http_code']) || !$this->info['http_code']){
            $this->errorCode = 'No HTTP code returned';
            $this->status = self::STATUS_CURL_ERROR;
            $this->trigger(LogEvent::NAME, new LogEvent([
                'type' => LogEvent::TYPE_ERROR,
                'url' => $this->request->url,
                'message' => $this->errorCode
            ]));
        }
        elseif ($this->info['http_code']>='300'){
            $this->errorCode = 'Error response code '.$this->info['http_code'];

            if ($this->info['http_code']=='403'){
                $this->status = self::STATUS_CURL_ERROR;
            }
            else {
                $this->status = self::STATUS_HTTP_ERROR;
            }

            $this->trigger(LogEvent::NAME, new LogEvent([
                'type' => LogEvent::TYPE_ERROR,
                'url' => $this->request->url,
                'message' => $this->errorCode
            ]));
        }
        elseif (!$this->output){
            $this->errorCode = 'Empty output';
            $this->status = self::STATUS_CURL_ERROR;
            $this->trigger(LogEvent::NAME, new LogEvent([
                'type' => LogEvent::TYPE_ERROR,
                'url' => $this->request->url,
                'message' => $this->errorCode
            ]));
        }
        elseif ($this->checkExpectation()) {
            $this->status = self::STATUS_OK;
        }
    }

    public function isCurlError()
    {
        return $this->status == self::STATUS_CURL_ERROR;
    }

    public function isOk()
    {
        return $this->status == self::STATUS_OK;
    }

    public function isToUpdateRequest()
    {
        return $this->status == self::STATUS_TO_UPDATE;
    }

    public function isNotExpected()
    {
        return $this->status == self::STATUS_NOT_EXPECTED;
    }

    protected function textExpected($expected)
    {
        switch ($expected) {
            case self::EXPECT_EXACT:
                return 'bynary';
            case self::EXPECT_HTML:
                return 'html';
            case self::EXPECT_JSON:
                return 'json';
            case self::EXPECT_XML:
                return 'xml';
        }
    }

    protected function checkExpectation()
    {
        switch ($this->request->expect) {
            case self::EXPECT_EXACT:
                $requestData = $this->request->requestData;
                $hash = hash('md5', $this->output);
                if (!isset($requestData['hashes']) || !in_array($hash, $requestData['hashes'])){
                    $this->request->requestData['hashes'][] = $hash;
                    $this->status = self::STATUS_TO_UPDATE;
                    return false;
                }
                break;

            case self::EXPECT_HTML:
                if (strpos($this->output, '</html>')===false && strpos($this->output, '</body>')===false) {
                    $this->status = self::STATUS_NOT_EXPECTED;
                    $this->trigger(LogEvent::NAME, new LogEvent([
                        'type' => LogEvent::TYPE_ERROR,
                        'url' => $this->request->url,
                        'message' => 'Not expected '.$this->textExpected($this->request->expect).' format'
                    ]));
                    return false;
                }
                break;

            case self::EXPECT_JSON:
                try {
                    $this->raw = $this->output;
                    $this->output = Json::decode($this->output);
                } catch (\Exception $e){
                    $this->status = self::STATUS_NOT_EXPECTED;
                    $this->trigger(LogEvent::NAME, new LogEvent([
                        'type' => LogEvent::TYPE_ERROR,
                        'url' => $this->request->url,
                        'message' => 'Not expected '.$this->textExpected($this->request->expect).' format'
                    ]));
                    return false;
                }
                break;

            case self::EXPECT_XML:
                try {
                    $this->raw = $this->output;
                    $this->output = new \SimpleXMLElement($this->output);
                } catch (\Exception $e){
                    $this->status = self::STATUS_NOT_EXPECTED;
                    $this->trigger(LogEvent::NAME, new LogEvent([
                        'type' => LogEvent::TYPE_ERROR,
                        'url' => $this->request->url,
                        'message' => 'Not expected '.$this->textExpected($this->request->expect).' format'
                    ]));
                    return false;
                }
                break;
        }

        return true;
    }

    public function hasAttempt()
    {
        if (!$this->isCurlError()){
            $this->request->tries++;
            $this->request->curlErrors = 0;
        }
        else {
            $this->request->curlErrors++;
        }

        return ($this->request->tries < $this->request->attempts && $this->request->curlErrors < Request::MAX_CURL_ERRORS );
    }

    public function success()
    {
        try {
            if (!$this->isOk()) return ;
            if (!$this->request->success || !is_callable($this->request->success)) return $this;
            return call_user_func($this->request->success, $this);
        }
        catch (\Exception $e){
            $this->trigger(LogEvent::NAME, new LogEvent([
                'type' => LogEvent::TYPE_ERROR,
                'message' => 'Callback error: '.$e->getMessage(),
                'url' => $this->request->url
            ]));
            return $this->error();
        }
    }

    public function error()
    {
        try {
            if ($this->request->error && is_callable($this->request->error)) {
                return call_user_func($this->request->error, $this);
            }
            return;
        }
        catch (\Exception $e){
            $this->trigger(LogEvent::NAME, new LogEvent([
                'type' => LogEvent::TYPE_ERROR,
                'message' => 'Callback error: '.$e->getMessage(),
                'url' => $this->request->url
            ]));
            return false;
        }
    }
}
