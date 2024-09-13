<?php

namespace Prasso\Messaging\Support\Facades;

use Illuminate\Support\Facades\Facade;

class MessagingPanel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'messaging';
    }
}
