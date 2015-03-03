clearwater-magento
==================

Clearwater Magento Module - Version 1.3

Tested versions..

Magento 1.4.2.0 -> 1.9.0.1

How To use
================
Login to your Magento Admin Panel.
Go to System -> Configuration.
On the left menu scroll down to the sales section, and click Worldpay Payments.

Add your keys, which you can find in your Worldpay dashboard (Settings -> API keys). Change Enabled to Yes, set your title and payment descriptions to what you would like the user to see.

In your Worldpay dashboard, (Settings -> Webhooks) add a webhook to your Magento URL.
For example;
http://www.mywebsite.com/index.php/worldpay/notification/

Changing www.mywebsite.com to your website URL. Visiting this URL should show OK is similar. Your URL most be externally accessible.

For testing, sometimes your local server isn't setup correctly to validate SSL certificates. To disable SSL checks in test mode, set 'Disable SSL check in test mode' to Yes.

If you change your API keys in future, you may need to clear the Magento cache for it to take affect immediately.

Configuration options
================

Environment Mode 
=====
Test Mode / Live Mode - 
Which set of service and client keys to use on the site

Test, Live - Service & Client keys
=====
Your keys, which can be found in your Worldpay dashboard.

Enabled
=====
Enable / Disable the module

Enable Logging
=====
This should be set to 'no' in normal circumstances. If you need support we may ask you to enable this.

Store customers card on file
=====
A reusable token will be generated for the customer which will then be stored. This will allow the customer to reuse cards they've used in the past. They simply need to re-enter their CVC to verify.

Disable SSL check in test mode
=====
Disables the checking of the SSL certificate whilst in test mode. This is useful for testing locally where certificates cannot be validated.

New order status
=====
Your Magento order status when payment has been successfully taken

Title
=====
The title of the module, as appears in the payment methods list to your customer. You can set this to blank to show no title.

Payment Description
=====
Payment description to send to Worldpay.


Troubleshooting
=================
I cannot find 'Worldpay Payments' in the configuration page.
--- Make sure you have uploaded the module into the root directory
--- Clear Magento cache
--- Resave your user
--- Logout and log back in

When I click 'Worldpay Payments' it responds with a 404 error.
--- Clear Magento cache
--- Resave your user
--- Logout and log back in

How to resave user
System -> Permissions -> Users -> Click your user -> Click save user

How to clear Magento cache
System -> Cache Management -> Click Flush Cache Storage


Changelog
================
1.3
Change integration type to template form
IE8 Support

1.2
Add compatibility with IWD one page checkout

1.1
Update PHP Lib

1.0
Initial Release
