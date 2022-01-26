<?php


namespace Oasin\Theme\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Theme
 * @package Oasin\Theme\Facades
 */
class ThemeFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Oasin\Theme\Theme::class;
    }
}
