* NOTE: This plugin is old and had to be corrected.

# Contact Form 7 email verification
Contributors: magician11, bcworkz, gonzomir
Requires at least: 3.6.1
Tested up to: 3.9.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extends Contact Form 7 to allow for email addresses to be verified.

## Description

This "Contact Form 7 email verification" plugin extends the "Contact Form 7" plugin to automatically verify email addresses for all form submissions.

After this plugin is activated, on a form submission..

1. The form submission does not get sent but instead saved temporarily (including attachments).
2. The sender gets sent an email with a link to click on to confirm their email address.
3. Once the link is clicked, the user gets sent back to the website with the form on, and
4. the previously submitted form gets sent as per usual for CF7 functionality.

For those interested, you can check out the code on GitHub [here](https://github.com/mikifus/wpcf7-email-verification "WP CF7 email verification code on GitHub").

## TODOs

There are things to still be done..

1. Allow verification to be set per form
2. Custom from email and name fields per form

## Installation

Just install and activate as per usual.

There are no settings in the current stable version to change.

## Frequently Asked Questions

### This is great, but it would be even better if it ...

I would love to hear how you would like to improve it. Let me know [here](https://github.com/mikifus/wpcf7-email-verification/issues "Issues").

### I've found a bug with it. Who can I tell?

Awesome! As above, just [get in touch](https://github.com/mikifus/wpcf7-email-verification/issues "Issues"). Or submit a message on the Support forum.

## Changelog

### 0.6
* Removed attachments, they allow hacks to get in easily.

### 0.55
* fixed plugin to work with latest version of CF7

### 0.47
* changed verification email from name to site title and email to admin_email

### 0.44
* Changed hook for cleaning up attachments to only check on form submissions

### 0.38
* Added support for attachments

### 0.22
* Fixed bug on double calling the plugin
* Info message displayed now on clicking the verification link

### 0.11
* The first stable release.
