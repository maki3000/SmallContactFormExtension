<?php namespace CreationHandicap\ContactFormExtension\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

use CreationHandicap\ContactFormExtension\Models\ContactFormExtension as ContactFormExtensionModel;

class ContactFormExtensions extends Controller
{
    public $implement = [
        'Backend\Behaviors\FormController'
    ];
    
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = [
        'contactFormExtension' 
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('CreationHandicap.ContactFormExtension', 'main-menu-item');
    }

    public function index()
    {
        $firstEntry = ContactFormExtensionModel::first();
        if (isset($firstEntry)) {
            return \Backend::redirect('creationhandicap/contactformextension/contactformextensions/update/1');
        }
    }

    public function update($recordId = null, $context = null)
    {
        parent::update($recordId, $context);

        if ($recordId != '1') {
            return \Backend::redirect('creationhandicap/contactformextension/contactformextensions/update/1');
        }
    }
    
}
