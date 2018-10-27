<?php

namespace demetrio77\multicurl\proxy;

use demetrio77\multicurl\BaseComponent;

abstract class BaseProxy extends BaseComponent
{
    public $username;
    public $password;
    public $modelClass;
    
    public function isPrivate()
    {
        return $this->username!='';
    }
    
    public function getCredentials()
    {
        return $this->username.':'.$this->password;
    }
    
    abstract public function start($threads);
    abstract public function get();
    abstract public function lock($adres);
    abstract public function unlock($adres);
    abstract public function end();
}