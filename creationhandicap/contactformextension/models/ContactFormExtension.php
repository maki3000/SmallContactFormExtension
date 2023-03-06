<?php namespace CreationHandicap\ContactFormExtension\Models;

use Model;

/**
 * Model
 */
class ContactFormExtension extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'creationhandicap_contactformextension_ext';

    public $jsonable = ['allowed_countries'];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'blacklisted'                   => 'nullable|string',
        'whitelisted'                   => 'nullable|string',
        'api_link'                      => 'nullable|url',
        'api_link_params'               => 'nullable|string',
        'allowed_countries.*.country'   => 'nullable|string',
        'redirect_url'                  => 'required|string', // IP validation without redirect isn't imlpemented yet
        'failed_message'                => 'nullable|string',
    ];

    private function ipValidation($ip)
    {
        $ipObject = ['ip_address' => trim($ip)];
        $validator = \Validator::make($ipObject, [
            'ip_address' => 'required|ip'
        ]);
        if ($validator->fails()) {
            return $ip;
        }
        return true;
    }

    private function checkIpEntries($ips)
    {
        if (isset($ips) && $ips !== '') {
            $ipsArray = explode(',', $ips);
            if (count($ipsArray) > 0) {
                foreach ($ipsArray as $ipKey => $ip) {
                    $validationResult = $this->ipValidation($ip);
                    if (gettype($validationResult) === 'string') {
                        return $validationResult;
                    }
                }
            }
        }
        return true;
    }

    public function beforeSave()
    {
        $blacklisted = $this->blacklisted;
        $blacklistedResult = $this->checkIpEntries($blacklisted);
        if (gettype($blacklistedResult) === 'string' && $blacklistedResult !== '') {
            throw new \ApplicationException($blacklistedResult . ' is not an IP address.');
            return false;
        }
        $whitelisted = $this->whitelisted;
        $whitelistedResult = $this->checkIpEntries($whitelisted);
        if (gettype($whitelistedResult) === 'string' && $whitelistedResult !== '') {
            throw new \ApplicationException($whitelistedResult . ' is not an IP address.');
            return false;
        }
    }
}
