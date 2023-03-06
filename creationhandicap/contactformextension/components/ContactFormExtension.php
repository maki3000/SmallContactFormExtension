<?php namespace CreationHandicap\ContactFormExtension\Components;

use Cms\Classes\ComponentBase;

use CreationHandicap\ContactFormExtension\Models\ContactFormExtension as ContactFormExtensionModel;

use JanVince\SmallContactForm\Components\SmallContactForm;
use JanVince\SmallContactForm\Models\Settings;
use JanVince\SmallContactForm\Models\Message;

use Validator;
use Illuminate\Support\MessageBag;
use Redirect;
use Request;
use Input;
use Session;
use Flash;
use Form;
use Log;
use ReCaptcha\ReCaptcha;

class ContactFormExtension extends SmallContactForm
{
    private $validationRules;
    private $validationMessages;
    private $validationReCaptchaServerName;

    private $postData = [];
    private $post;

    private $formDescription;
    private $formDescriptionOverride;

    private $formRedirect;
    private $formRedirectOverride;

    private function checkIp($currentIp)
    {
        $ipData = ContactFormExtensionModel::first();
        $failedMessage = 'Sending Message failed';
        if ($ipData->failed_message) {
            $failedMessage = $ipData->failed_message;
        }
        $failReturnData = [
            'redirect_url' => $ipData->redirect_url,
            'failed_message' => $failedMessage,
        ];

        if (isset($ipData) && $ipData->count() === 1) {
            // blacklisted
            if (isset($ipData->blacklisted) && $ipData->blacklisted !== '') {
                $ipsArray = array_map('trim', explode(',', $ipData->blacklisted));
                $blacklisted = in_array($currentIp, $ipsArray);
                return $blacklisted ? $failReturnData : true;
            }
            // whitelisted
            if (isset($ipData->whitelisted) && $ipData->whitelisted !== '') {
                $ipsArray = array_map('trim', explode(',', $ipData->whitelisted));
                $withelisted = in_array($currentIp, $ipsArray);
                return $withelisted ? true : $failReturnData;
            }
            // check with API
            if (isset($ipData->api_link) && $ipData->api_link !== '') {
                $apiUrl = $ipData->api_link . $currentIp;
                if ($ipData->api_link_params) {
                    $apiUrl = $apiUrl . $ipData->api_link_params;
                }
                $response = \Http::get($apiUrl);
                if (!is_object(json_decode($response->body))) {
                    return $failReturnData;
                }
                $decodedResponse = json_decode($response->body);
                if (isset($ipData->allowed_countries) && count($ipData->allowed_countries) > 0) {
                    $ipDataCountryArray = [];
                    foreach ($ipData->allowed_countries as $countryKey => $country) {
                        $ipDataCountryArray[$countryKey] = $country['country'];
                    }
                    $countryWithelisted = in_array($decodedResponse->country_name, $ipDataCountryArray);
                    return $countryWithelisted ? true : $failReturnData;
                }
            }
        }
        return true;
    }

    /**
    * Form handler
    */
    public function onFormSendExtend() {
        /**
         * Validation
        */
        $this->setFieldsValidationRules();
        $this->validationReCaptchaServerName = $_SERVER['SERVER_NAME'] == "127.0.0.1" ? "localhost" : $_SERVER['SERVER_NAME'];
        $errors = [];

        $this->post = Input::all();

        // IP protection is enabled (has highest priority)
        // But privacy must allow messages saving
        if( Settings::getTranslated('add_ip_protection') and !Settings::getTranslated('privacy_disable_messages_saving') ) {

            $max = ( Settings::getTranslated('add_ip_protection_count') ? intval(Settings::getTranslated('add_ip_protection_count')) : intval(e(trans('janvince.smallcontactform::lang.settings.antispam.add_ip_protection_count_placeholder'))) );

            if( empty($max) ) {
                $max = 3;
            }

            $currentIp = Request::ip();
            $ipCheckResult = $this->checkIp($currentIp);
            if ($ipCheckResult !== true) {
                if ($ipCheckResult['redirect_url'] !== '' && $ipCheckResult['failed_message'] !== '') {
                    return \Redirect::to($ipCheckResult['redirect_url'])->with('sendIpFail', $ipCheckResult['failed_message']);
                } else {
                    // try to fool the bot 🤷‍♂️
                    return ['success' => 'Thank you very much for your message!'];
                }
            }

            if( empty($currentIp) ) {
                Log::error('SMALL CONTACT FORM ERROR: Could not get remote IP address!');
                $errors[] = e(trans('janvince.smallcontactform::lang.settings.antispam.add_ip_protection_error_get_ip'));
            } else {

                $message = new Message;

                if($message->testIPAddress($currentIp) >= $max) {
                    $errors[] = ( Settings::getTranslated('add_ip_protection_error_too_many_submits') ? Settings::getTranslated('add_ip_protection_error_too_many_submits') : e(trans('janvince.smallcontactform::lang.settings.antispam.add_ip_protection_error_too_many_submits_placeholder')) );
                }

            }

        }

        // Antispam validation if allowed
        if( Settings::getTranslated('add_antispam')) {
            $this->validationRules[('_protect-' . $this->alias)] = 'size:0';

            if( !empty($this->post['_form_created']) ) {

                try {
                    $delay = ( Settings::getTranslated('antispam_delay') ? intval(Settings::getTranslated('antispam_delay')) : intval(e(trans('janvince.smallcontactform::lang.settings.antispam.antispam_delay_placeholder'))) );

                    if(!$delay) {
                        $delay = 5;
                    }

                    $formCreatedTime = strtr(Input::get('_form_created'), 'jihgfedcba', '0123456789');

                    $this->post['_form_created'] = intval($formCreatedTime) + $delay;

                    $this->validationRules['_form_created'] = 'numeric|max:' . time();
                }
                catch (\Exception $e)
                {
                    Log::error($e->getMessage());
                    $errors[] = e(trans('janvince.smallcontactform::lang.settings.antispam.antispam_delay_error_msg_placeholder'));
                }
            }

        }

        //  reCaptcha validation if enabled
        if(Settings::getTranslated('add_google_recaptcha')) 
        {
            try {
                /**
                 * Text if allow_url_fopen is disabled
                 */
                if (!ini_get('allow_url_fopen')) 
                {
                    $recaptcha = new ReCaptcha(Settings::get('google_recaptcha_secret_key'), new \ReCaptcha\RequestMethod\SocketPost());
                }
                else {
                // allow_url_fopen = On
                    $recaptcha = new ReCaptcha(Settings::get('google_recaptcha_secret_key'));
                }

                $response = $recaptcha->setExpectedHostname($this->validationReCaptchaServerName)->verify(post('g-recaptcha-response'), $_SERVER['REMOTE_ADDR']);
            } 
            catch(\Exception $e) 
            {
                Log::error($e->getMessage());
                $errors[] = e(trans('janvince.smallcontactform::lang.settings.antispam.google_recaptcha_error_msg_placeholder'));
            }

            if(!$response->isSuccess()) {
                $errors[] = ( Settings::getTranslated('google_recaptcha_error_msg') ? Settings::getTranslated('google_recaptcha_error_msg') : e(trans('janvince.smallcontactform::lang.settings.antispam.google_recaptcha_error_msg_placeholder')));
            }

        }

        // Validate sent data
        $validator = Validator::make($this->post, $this->validationRules, $this->validationMessages);
        $validator->valid();
        $this->validationMessages = $validator->messages();
        $this->setPostData($validator->messages());

        if($validator->failed() or count($errors)) {

            // Form main error msg (can be overriden by component property)
            if ( parent::property('form_error_msg') ) {

                $errors[] = parent::property('form_error_msg');

            } else {

                $errors[] = ( Settings::getTranslated('form_error_msg') ? Settings::getTranslated('form_error_msg') : e(trans('janvince.smallcontactform::lang.settings.form.error_msg_placeholder')));

            }

            // Validation error msg for Antispam field
            if( empty($this->postData[('_protect' . $this->alias)]['error']) && !empty($this->postData['_form_created']['error']) ) {
                $errors[] = ( Settings::getTranslated('antispam_delay_error_msg') ? Settings::getTranslated('antispam_delay_error_msg') : e(trans('janvince.smallcontactform::lang.settings.antispam.antispam_delay_error_msg_placeholder')));
            }

            Flash::error(implode(PHP_EOL, $errors));
                    
            if (Request::ajax()) {
                
                $this->page['formSentAlias'] = $this->alias;
                $this->page['formError'] = true;
                $this->page['formSuccess'] = null;

            } else {
                
                Session::flash('formSentAlias', $this->alias);
                Session::flash('formError', true);

            }

            // Fill hidden fields if request has errors to maintain
            $this->formDescriptionOverride = post('_form_description');
            $this->formRedirectOverride = post('_form_redirect');

        } else {

            // Form main success msg (can be overriden by component property)
            if (parent::property('form_success_msg')) {
                
                $successMsg = parent::property('form_success_msg');
                
            } else {
                
                $successMsg = ( Settings::getTranslated('form_success_msg') ? Settings::getTranslated('form_success_msg') : e(trans('janvince.smallcontactform::lang.settings.form.success_msg_placeholder')) );
                
            }

            $message = new Message;
            $formNotes = ( parent::property('form_notes') ? parent::property('form_notes') : Settings::getTranslated('form_notes') );

            // Store data in DB
            $formDescription = !empty($this->post['_form_description']) ? e($this->post['_form_description']) : parent::property('form_description');
            $messageObject = $message->storeFormData($this->postData, $this->alias, $formDescription, $formNotes);
            
            // Send autoreply
            $message->sendAutoreplyEmail($this->postData, parent::getProperties(), $this->alias, $formDescription, $messageObject, $formNotes);

            // Send notification
            $message->sendNotificationEmail($this->postData, parent::getProperties(), $this->alias, $formDescription, $messageObject, $formNotes);

            /**
             * Flash messages
             */
            Flash::success($successMsg);

            if (Request::ajax()) {

                $this->postData = [];
                $this->page['formSentAlias'] = $this->alias;
                $this->page['formSuccess'] = true;
                $this->page['formError'] = null;

            } else {

                Session::flash('formSentAlias', $this->alias);
                Session::flash('formSuccess', true);

            }

            /**
             * Keep properties overrides after Ajax request (onRender method is not called)
             */
            if (Request::ajax()) {

                $this->formDescriptionOverride = post('_form_description');
                $this->formRedirectOverride = post('_form_redirect');

            }

            /**
             *  Redirects
             *  
             * Redirect to defined page or to prevent repeated sending of form
             * Clear data after success AJAX send
             */
            if( Settings::getTranslated('allow_redirect') or parent::property('allow_redirect') ) {

                // Component markup parameter (eg. {{ component 'contactForm' redirect_url = '/form-success-'~page.id }} ) overrides component property
                if(!empty($this->post['_form_redirect'])) {

                    $propertyRedirectUrl = e($this->post['_form_redirect']);

                } else {

                    $propertyRedirectUrl = parent::property('redirect_url');

                }

                // If redirection is allowed but no URL provided, just refresh (if not AJAX)
                if(empty($propertyRedirectUrl) and empty(Settings::getTranslated('redirect_url'))) {
                
                Log::warning('SCF: Form redirect is allowed but no URL was provided!');

                if (!Request::ajax()) {

                    return Redirect::refresh();
                    
                } else {

                    return;

                }

            }

            // Overrides take precedence
            if( !empty(Settings::getTranslated('redirect_url_external')) and !empty(parent::property('redirect_url_external')) ) {

                $path = $propertyRedirectUrl ? $propertyRedirectUrl : Settings::getTranslated('redirect_url');

            } else {

                $path = $propertyRedirectUrl ? url($propertyRedirectUrl) : url(Settings::getTranslated('redirect_url'));

            }

            return Redirect::to($path);

            } else {

                if (!Request::ajax()) {

                    return Redirect::refresh();

                }

            }

        }

    }

    /**
     * Get form attributes
    */
    public function getFormAttributesExtend(){

        $attributes = [];

        $attributes['request'] = $this->alias . '::onFormSendExtend';
        $attributes['files'] = true;
        
        // Disabled hard coded hash URL in 1.41.0 as dynamic redirect is now available
        // $attributes['url'] = '#scf-' . $this->alias;
        
        $attributes['method'] = 'POST';
        $attributes['class'] = null;
        $attributes['id'] = 'scf-form-id-' . $this->alias;

        if( Settings::getTranslated('form_allow_ajax', 0) ) {

            $attributes['data-request'] = $this->alias . '::onFormSendExtend';
            $attributes['data-request-validate'] = 'data-request-validate';
            $attributes['data-request-files'] = 'data-request-files';
            $attributes['data-request-update'] = "'". $this->alias ."::scf-message':'#scf-message-". $this->alias ."','". $this->alias ."::scf-form':'#scf-form-". $this->alias ."'";
            
            if( Settings::get('add_google_recaptcha') ) {
                    $attributes['data-request-complete'] = "onloadCallback_" . $this->alias . '();';
            }

        }

        if( Settings::getTranslated('form_css_class') ) {
            $attributes['class'] .= Settings::getTranslated('form_css_class');
        }

        if( !empty(Input::all()) ) {
            $attributes['class'] .= ' was-validated';
        }

        if( Settings::getTranslated('form_send_confirm_msg') and Settings::getTranslated('form_allow_confirm_msg') ) {

            $attributes['data-request-confirm'] = Settings::getTranslated('form_send_confirm_msg');

        }

        // Disable browser validation if enabled
        if(!empty(Settings::getTranslated('form_disable_browser_validation'))){
         $attributes['novalidate'] = "novalidate";
        }

        return $attributes;
    }

    /**
     * Generate validation rules and messages
     */
    private function setFieldsValidationRules() {

        $fieldsDefinition = parent::fields();

        $validationRules = [];
        $validationMessages = [];
        foreach($fieldsDefinition as $field){
        
        if(!empty($field['validation'])) {
            $rules = [];
            
            foreach($field['validation'] as $rule) {
            
                if( $rule['validation_type']=='custom' && !empty($rule['validation_custom_type']) ){

                    if(!empty($rule['validation_custom_pattern'])) {
                        
                        switch ($rule['validation_custom_type']) {

                            //
                            //Keep regex pattern in an array
                            //
                            case "regex":

                            $rules[] = [$rule['validation_custom_type'], $rule['validation_custom_pattern']];

                            break;

                            default:

                            $rules[] = $rule['validation_custom_type'] . ':' . $rule['validation_custom_pattern'];

                            break;

                        }
                        
                        
                        
                        } else {
                        
                            $rules[] = $rule['validation_custom_type'];

                        }

                        if(!empty($rule['validation_error'])){

                            $validationMessages[($field['name'] . '.' . $rule['validation_custom_type'] )] = Settings::getDictionaryTranslated($rule['validation_error']);
                        }  

                    } else {

                        $rules[] = $rule['validation_type']; 

                            if(!empty($rule['validation_error'])){

                                $validationMessages[($field['name'] . '.' . $rule['validation_type'] )] = Settings::getDictionaryTranslated($rule['validation_error']);
                            }  
                    }
                }

                $validationRules[$field['name']] = $rules;
            }
        }

        $this->validationRules = $validationRules;
        $this->validationMessages = $validationMessages;

    }


    /**
     * Generate post data with errors
    */
    private function setPostData(MessageBag $validatorMessages){

        foreach( parent::fields() as $field){

            $this->postData[ $field['name'] ] = [
                'value' => e(Input::get($field['name'])),
                'error' => $validatorMessages->first($field['name']),
            ];

        }

    }

}
