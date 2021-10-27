<?php
namespace demetrio77\multicurl;

use yii\base\Event;

class LogEvent extends Event
{
    const NAME = 'parsing.log.event';
    const TYPE_MESSAGE = 1;
    const TYPE_END = 9;
    const TYPE_ERROR = 100;

    public string $message;
    public int $type = self::TYPE_MESSAGE;
    public string $url;
    public ?int $siteId;
    public string $uid;
    public $notes;
}
