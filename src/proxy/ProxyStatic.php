<?php

namespace demetrio77\multicurl\proxy;

class ProxyStatic extends BaseProxy
{
    public $address;

    public function start($threads)
    {
        return true;
    }
    
    public function get()
    {
        return $this->address;
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