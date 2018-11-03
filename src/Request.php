<?php

namespace demetrio77\multicurl;

/**
 * Class that represent a single curl request
 */
class Request extends BaseComponent
{
    const MAX_CURL_ERRORS = 3;
    
    /**
     * Requested url
     * @var string
     */
    public $url = false;
    
    /**
     * Request method
     * @var string
     */
    public $method = 'GET';
    
    /**
     * Sending data
     * @var unknown
     */
    public $postData = null;
    
    /**
     * Sending headers
     * @var unknown
     */
    public $headers = null;
    
    /**
     * Sending cookies
     * @var unknown
     */
    public $cookies = null;
    
    /**
     * Curl options
     * @var unknown
     */
    public $options = null;
    
    /**
     * Callback function to process success response
     * @var unknown
     */
    public $success = null;
    
    /**
     * Callback function to process error response
     * @var unknown
     */
    public $error = null;
    
    /**
     * The number of attempts to get data by curl
     * @var integer
     */
    public $attempts = 3;
    
    /**
     * Current number of attempts
     * @var integer
     */
    public $tries = 0;
    
    /**
     * Override curl's property if needed to set request's property instead of common for all request
     * @var unknown
     */
    public $proxy = true;
    
    /**
     * Current number of curl errors
     * @var integer
     */
    public $curlErrors = 0;
    
    /**
     * Used proxy
     * @var string
     */
    public $proxyUsed = null;
    
    /**
     * Here one can specify the data needed to the callback
     * @var unknown
     */
    public $requestData=null;
    
    /**
     * 
     * @var integer
     */
    public $timeout = 30;
    
    /**
     * Expecting type of response's output
     *
     * @var string
     */
    public $expect = Response::EXPECT_HTML;
    
    public function composeHeaderLines()
    {
        if (!$this->headers) {
            return [];
        }
        $headers = [];
        foreach ($this->headers as $name => $values) {
            $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
            foreach ($values as $value) {
                $headers[] = "$name: $value";
            }
        }
        
        if ($this->cookies) {
            $headers[] = $this->getCookieHeader();
        }
        return $headers;
    }
    
    /**
     * @return string cookie header value.
     */
    protected function getCookieHeader()
    {
        $parts = [];
        foreach ($this->cookies as $cookie) {
            $parts[] = $cookie->name . '=' . $cookie->value;
        }
        return 'Cookie: ' . implode(';', $parts);
    }
}