<?php

namespace demetrio77\multicurl\proxy;

class ProxyRotated extends BaseProxy
{
    public $modelClass;
    private $proxies = [];

    public function start($threads)
    {
        $this->proxies = [];
        $this->proxies = $this->modelClass::find()->all();
    }
    
    public function get()
    {
        return $this->proxies[rand(0,count($this->proxies)-1)]->adres;
    }
    
    public function lock($adres)
    {
         return true;
    }
    
    public function unlock($adres)
    {
        return true;
    }
    
    public function end()
    {
        return true;
    }
}