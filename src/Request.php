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
    public string $url = '';

    /**
     * Request method
     * @var string
     */
    public string $method = 'GET';

    /**
     * Sending data
     * @var array|null
     */
    public ?array $postData = null;

    /**
     * Sending headers
     * @var array|null
     */
    public ?array $headers = null;

    /**
     * Sending cookies
     * @var array|null
     */
    public ?array $cookies = null;

    /**
     * Curl options
     * @var array|null
     */
    public ?array $options = null;

    /**
     * Callback function to process success response
     * @var callable
     */
    public $success = null;

    /**
     * Callback function to process error response
     * @var callable
     */
    public $error = null;

    /**
     * The number of attempts to get data by curl
     * @var integer
     */
    public int $attempts = 5;

    /**
     * Current number of attempts
     * @var integer
     */
    public int $tries = 0;

    /**
     * Override curl's property if needed to set request's property instead of common for all request
     * @var bool
     */
    public bool $proxy = true;

    /**
     * Current number of curl errors
     * @var integer
     */
    public int $curlErrors = 0;

    /**
     * Used proxy
     * @var string|null
     */
    public ?string $proxyUsed = null;

    /**
     * Here one can specify the data needed to the callback
     * @var array|null
     */
    public ?array $requestData=null;

    /**
     *
     * @var integer
     */
    public int $timeout = 30;

    /**
     * Expecting type of response's output
     *
     * @var int
     */
    public int $expect = 0;

    /**
     * @return string[]
     */
    public function composeHeaderLines(): array
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
            $headers[] = $this->getCookieHeader($this->cookies);
        }
        return $headers;
    }

    /**
     * @return string cookie header value.
     */
    protected function getCookieHeader(array $cookies = []): string
    {
        $parts = [];
        foreach ($cookies as $cookie) {
            $parts[] = $cookie->name . '=' . $cookie->value;
        }
        return 'Cookie: ' . implode(';', $parts);
    }
}
