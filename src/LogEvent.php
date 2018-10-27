<?php
namespace demetrio77\multicurl;

use yii\base\Event;

class LogEvent extends Event
{
    const NAME = 'parsing.log.event';
    
    const TYPE_MESSAGE = 'message';
    const TYPE_ERROR = 'error';
    
    public $message;
    public $type = self::TYPE_MESSAGE;
    public $url;
    public $siteId;
    public $uid;
    public $notes;
}