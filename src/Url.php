<?php
namespace demetrio77\multicurl;

use demetrio77\multicurl\proxy\BaseProxy;
use SimpleXMLElement;

class Url extends BaseComponent
{
    public int $expect = Response::EXPECT_EXACT;
    public int $attempts = 10;
    public ?BaseProxy $proxy;

    /**
     * @param string $url
     * @return mixed
     * @throws Exception
     */
    public function response(string $url)
    {
        $Session = new Session();

        $Session->add(new Request([
            'url' => $url,
            'expect' => $this->expect,
            'attempts' => $this->attempts
        ]));

        $Curl = new Curl([
            'proxy' => $this->proxy,
            'threads' => 1,
        ]);

        return $Curl->run($Session, function($Session) {
            $request = array_shift($Session->requests);
            return $request['response'];
        });
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    public function raw(string $url): string
    {
        return $this->response($url)->raw;
    }

    /**
     * @param string $url
     * @return mixed
     * @throws Exception
     */
    public function output(string $url)
    {
        return $this->response($url)->output;
    }

    /**
     * @param string $url
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function xml(string $url): SimpleXMLElement
    {
        $this->expect = Response::EXPECT_XML;
        return $this->output($url);
    }

    /**
     * @param string $url
     * @return array
     * @throws Exception
     */
    public function json(string $url): array
    {
        $this->expect = Response::EXPECT_JSON;
        return $this->output($url);
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    public function html(string $url): string
    {
        $this->expect = Response::EXPECT_HTML;
        return $this->output($url);
    }
}
