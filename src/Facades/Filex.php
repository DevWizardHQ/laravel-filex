<?php

namespace DevWizard\Filex\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DevWizard\Filex\Filex
 */
class Filex extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DevWizard\Filex\Filex::class;
    }
}
