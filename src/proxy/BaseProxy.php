<?php

namespace demetrio77\multicurl\proxy;

use demetrio77\multicurl\BaseComponent;

abstract class BaseProxy extends BaseComponent
{
    public string $username = '';
    public string $password = '';

    /**
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->username !== '';
    }

    /**
     * @return string
     */
    public function getCredentials(): string
    {
        return $this->username.':'.$this->password;
    }

    abstract public function get(): string;
    abstract public function unlock(string $proxy);
    abstract public function getMaxThreads(int $threads);
}
