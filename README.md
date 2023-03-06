# SmallContactFormExtension
Draft of an extension of the SmallContactForm plugin from Jan Vince for October CMS.

## Use disclaimer
- Only tested with OC 2.x
- blacklisted and whitelisted (which actually make not much sense, but it's there anyway 🤷‍♂️) are only roughly tested with a small number of IPs
- If you intend to use another IP-service than ip2location.io for a country restriction, you must certainly have to adjust `country_name` in components/ContactFormExtension.php on line 78. Different APIs use different keys for the country.
- Features like: let the user stay on the same page after sending the form or Google Captcha aren't implemented yet
- If both 'Redirect URL' and 'Failed message' aren't provided, this extension spews the message "Thank you very much for your message!" out in JSON, which isn't optimal, I think

## Installation
1. Copy this plugin to inside your plugin folder
2. Install the plugin as described here: https://docs.octobercms.com/2.x/packages/publishing-packages.html#private-plugins-and-themes
3. On your first visit to the plugin in the backend, you'll need to change the end of the URL from `/update/1` to `create` (this is my way to create an OC plugin without a list. If there's a better way to do that, I'm happy to hear) and save it
4. Since I didn't had time to implement a version, where the user stays on the same page after sending the form, the field `redirect_url` is required. Just put any URL in there, if you want to use only e.g. blacklisted
5. Go to the place, where you embeded the original SmallContactForm plugin component and replace it with this component
6. Add following script to your page or partial, where the redirect URL points:
```
function onStart()
{
    $sendIpFail = Session::get('sendIpFail');
    if ($sendIpFail) {
        $this->addJs('assets/js/send-ip-fail.js');
        \Flash::error($sendIpFail);
    }
}
```
7. On the same page or partial, add this HTML snippet:
```
<div id="send-ip-fail" style="display: none;">
    {% partial 'flash-message-form-fail' %}
</div>
```
8. Put the partial `flash-message-form-fail.htm`, which is in the assets folder in this plugin, in the place you want to have it (I have it inside my `themes/theme-name/partials/` folder) and maybe adjust the path
9. Put the JS `send-ip-fail.js`, which is in the assets folder in this plugin, in the place you want to have it (I have it in `themes/theme-name/assets/js/`) and maybe adjust the path
10. TEST!

Sorry, step 6. to 9. are implemented very sloppy, like some other parts of this extension, and I'm sure, that could be done better, but I didn't had the time to make it nice...
