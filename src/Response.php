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
    public array $info;

    /**
     *
     * @var Request
     */
    public Request $request;

    /**
     *
     * @var string
     */
    public $output;

    /**
     *
     * @var string
     */
    public string $raw;

    /**
     *
     * @var int
     */
    public int $key;

    /**
     *
     * @var int
     */
    public int $status;

    /**
     *
     * @var Session;
     */
    public Session $session;

    /**
     * @var string|null
     */
    public ?string $errorCode = null;

    /**
     *
     */
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
        elseif ((int)$this->info['http_code'] >= 300 ){
            $this->errorCode = 'Error response code '.$this->info['http_code'];

            if ((int)$this->info['http_code'] === 403 ){
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

    /**
     * @return bool
     */
    public function isCurlError(): bool
    {
        return $this->status === self::STATUS_CURL_ERROR;
    }

    /**
     * @return bool
     */
    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * @return bool
     */
    public function isToUpdateRequest(): bool
    {
        return $this->status === self::STATUS_TO_UPDATE;
    }

    /**
     * @return bool
     */
    public function isNotExpected(): bool
    {
        return $this->status === self::STATUS_NOT_EXPECTED;
    }

    /**
     * @param int $expected
     * @return string
     */
    protected function textExpected(int $expected): string
    {
        switch ($expected) {
            case self::EXPECT_EXACT:
                return 'binary';
            case self::EXPECT_HTML:
                return 'html';
            case self::EXPECT_JSON:
                return 'json';
            case self::EXPECT_XML:
                return 'xml';
        }

        return 'none';
    }

    /**
     * @return bool
     */
    protected function checkExpectation(): bool
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
                if (strpos($this->output, '</html>') === false && strpos($this->output, '</body>') === false) {
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
                } catch (\Exception $exception){
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
                } catch (\Exception $exception){
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

    /**
     * @return bool
     */
    public function hasAttempt(): bool
    {
        if (!$this->isCurlError()){
            $this->request->tries ++;
            $this->request->curlErrors = 0;
        }
        else {
            $this->request->curlErrors ++;
        }

        return ($this->request->tries < $this->request->attempts && $this->request->curlErrors < Request::MAX_CURL_ERRORS );
    }

    public function success()
    {
        try {
            if (!$this->isOk()) return null;
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
            return null;
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
