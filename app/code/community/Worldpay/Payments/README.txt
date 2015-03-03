Worldpay Payments Magento Module


-- INSTALLATION --
1. Back up your existing Magento installation and database
2. From the zipped file, duplicate the folder structure from the zip, into you're Magento installation.


-- CONFIGURATION --
1. Enable the Worldpay_Payments module from Admin->System->Configuration->Advanced
2. Once enabled, click the WorldPay tab within the Sales section.


Environment Configuration
--------------------------
1. For development, set Environment Mode to Test Mode this will ensure the WorldPay test environment is used, change this to Live Mode when testing actual transactions.

Environment Mode can be toggled at any time and all requests will be routed to the appropriate environment.


General Configuration
---------------------
1. Enabled: Set to Yes
2. Title: Leave blank
3. Enable logging: If the developers wish to use logging to track any issues in sending requests or XML responses, set this to Yes
4. New Order Status: We recommend this should be set to Processing

