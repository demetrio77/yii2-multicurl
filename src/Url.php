<?php

namespace demetrio77\multicurl;

class Url extends BaseComponent
{
    public $expect = Response::EXPECT_EXACT;
    public $attempts = 10;
    public $proxy = true;

    protected function run($url)
    {
        $Session = new Session();

        $Session->add(new Request([
            'url' => $url,
            'expect' => $this->expect,
            'attempts' => $this->attempts
        ]));

        $Curl = new Curl([
            'proxy' => $this->proxy,
            'threads' => 1
        ]);

        return $Curl->run($Session, function($Session){
            $request = array_shift($Session->requests);
            return $request['response'];
        });
    }

    public function raw($url)
    {
        return $this->run($url)->raw;
    }

    public function output($url)
    {
        return $this->run($url)->output;
    }

    public function xml($url)
    {
        $this->expect = Response::EXPECT_XML;
        return $this->output($url);
    }

    public function json($url)
    {
        $this->expect = Response::EXPECT_JSON;
        return $this->output($url);
    }

    public function html($url)
    {
        $this->expect = Response::EXPECT_HTML;
        return $this->output($url);
    }
}
