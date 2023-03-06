<?php namespace CreationHandicap\ContactFormExtension;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    /**
     * @var array Plugin dependencies
     */
    public $require = ['JanVince.SmallContactForm'];

    public function register()
    {
       
    }

    public function boot()
    {
        
    }

    public function registerComponents()
    {
        return [
            'CreationHandicap\ContactFormExtension\Components\ContactFormExtension'     => 'contactformextension',
        ];
    }

    public function registerSettings()
    {
    }
}
