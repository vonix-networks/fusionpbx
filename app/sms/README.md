# Install HOW TO

This is the Vonix fork of the fusionpbx-apps SMS app, tracking
https://github.com/fusionpbx/fusionpbx-apps. It ships in the tree, so
there is nothing to clone or symlink: the lua lives at
app/switch/resources/scripts/app/sms/index.lua and the chatplan at
app/switch/resources/conf/chatplan/default.xml, matching how 5.5 ships
the other apps' switch scripts.

Differences from the upstream app:
- questblue carrier (hook + outbound send)
- bandwidth inbound handles the array-of-events callback payload the v2
  messaging api posts, and outbound sends the required applicationId
- outbound bodies for bandwidth, questblue and thinq are JSON- and
  shell-escaped before being handed to curl

1. Go to GUI
2. Upgrades -> SCHEMA; APP DEFAULTS; MENU DEFAULTS; PERMISSION DEFAULTS
3. Log out and back in
4. Advanced -> Default Settings -> SMS
5. Set CARRIER_access_key and CARRIER_secret_key for whatever carrier you want to use, confirm CARRIER_api_url is correct
6. Go to Apps -> SMS and add the DID's that are allowed to send outgoing SMS messages
7. Go to Accounts -> Extensions
8. For each extension that should be allowed to send SMS messages, set the "Outbound Caller ID Number" field to the respective DID from step 6
    - Note: Your outbound Caller ID should match the DID you placed in Apps -> SMS DID list
9. Make sure you have Destinations that match the DID's in Apps -> SMS in order to receive SMS messages at those DID's
    - Note: The Destination's action should be a regular extension (for one internal recipient) or a ring group (for multiple internal recipients)
10. Add your carrier's IPs in an ACL
11. Add your callback URL on your carrier to IE for twillio it would be: https://YOURDOMAIN/app/sms/hook/sms_hook_twilio.php
    - Note: You will need to have a valid certificate to use Twilio. If you need a certificate, consider using Let's Encrypt and certbot. It’s fast and free. 
12. For email delivery support, it uses the default setting email->smtp_from, so make sure that this is set appropriately.
13. For MMS email delivery, it will use the default setting sms->mms_attachment_temp_path, if this is set.  If not, it will try to use '/var/www/fusionpbx/app/sms/tmp/'
    as the temporary storage for the attachments.  Please make sure that you create the appropriate temp folder and change ownership to www-data/www-data.

Send and receive!

NOTE: It is not recommended to use this app with versions of Freeswitch prior to 1.8 if you are installing in a clustered environment.  
There is a bug in earlier versions of Freeswitch that can cause it to crash in certain situation when using SMS.
