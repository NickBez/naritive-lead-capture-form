Naritive Lead Capture
A Web Story lead capture form that submits to Salesforce via a custom WordPress REST API plugin.
What it does
A user views a Google AMP Web Story, taps "Fill in the Form", fills in their details, and a Lead record is created in Salesforce.
Setup

Clone the repo and place contents into a WordPress installation
Activate the Naritive Salesforce Integration plugin
Copy config.sample.php to config.php and add your Salesforce credentials:

phpdefine( 'NARITIVE_SF_CLIENT_ID',     '' );
define( 'NARITIVE_SF_CLIENT_SECRET', '' );
define( 'NARITIVE_SF_INSTANCE_URL',  '' );
Stack

WordPress + Web Stories plugin
Google AMP (amp-form, amp-mustache)
Salesforce REST API (OAuth2 Client Credentials)

Notes

config.php is gitignored
The story HTML reference is in story-export/
The REST endpoint lives at /wp-json/naritive-salesforce/v1/leads
