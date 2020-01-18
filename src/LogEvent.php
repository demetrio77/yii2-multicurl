<?php
namespace demetrio77\multicurl;

use yii\base\Event;

class LogEvent extends Event
{
    const NAME = 'parsing.log.event';
    
    const TYPE_MESSAGE = 1;
    const TYPE_END = 3;
    const TYPE_ERROR = 2;
    
    public $message;
    public $type = self::TYPE_MESSAGE;
    public $url;
    public $siteId;
    public $uid;
    public $notes;
}