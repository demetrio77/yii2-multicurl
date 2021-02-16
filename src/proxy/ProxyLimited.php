<?php

namespace demetrio77\multicurl\proxy;

class ProxyLimited extends BaseProxy
{
    public $modelClass;
    private $proxies = [];

    public function start($threads)
    {
        $this->proxies = [];
        $proxies = $this->modelClass::find()->where('(busy is NULL) or (busy < :outOfTime)', [':outOfTime' => time()-60*60*5])->limit(ceil($threads/$this->maxConnectionsPerProxy))->all();

        foreach ($proxies as $proxy) {
            $proxy->busy = time();
            $proxy->save();
            $this->proxies[$proxy->adres] = [
                'id' => $proxy->id,
                'busy' => 0
            ];
        }
    }

    public function get()
    {
        $min = false;
        $minAdres = false;
        foreach ($this->proxies as $adres => $proxy) {
            if ($proxy['busy']==0){
                $this->lock($adres);
                return $adres;
            }
            elseif (!$min || ($min>$proxy['busy'])) {
                $min = $proxy['busy'];
                $minAdres = $adres;
            }
        }

        $this->lock($minAdres);
        return $minAdres;
    }

    public function lock($adres)
    {
         $this->proxies[$adres]['busy']++;
    }

    public function unlock($adres)
    {
        $this->proxies[$adres]['busy']--;
    }

    public function end()
    {
        foreach ($this->proxies as $proxy){
            $model = $this->modelClass::findOne($proxy['id']);
            if ($model){
                $model->busy = null;
                $model->save(false);
            }
        }
    }
}
