<?php

/**
 * Plugin Name: Contact Form 7 email verification
 * Plugin URI: http://andrewgolightly.com/contact-form-7-email-verification/
 * Description: Extends Contact Form 7 to allow for email addresses to be verified by getting a user to click on a link that is sent to their email address.
 * Version: 0.1
 * Author: Andrew Golightly
 * Author URI: http://andrewgolightly.com
 * License: GPL2
 */

/*  Copyright 2013  Andrew Golightly  (email : support@andrewgolightly.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'wpcf7_before_send_mail', 'wpcf7ev_verify_email_address' );

function wpcf7ev_verify_email_address( &$wpcf7_form )
{
    // get the email address it's being sent from
    $submittersEmailAddress = wpcf7ev_get_senders_email_address($wpcf7_form);
    wp_mail('support@andrewgolightly.com', 'Form notice', 'Hi Andrew,
    We have had a form submission from' . $submittersEmailAddress . '. We are waiting for them to confirm their email address');
    
    //create hash code
    $random_hash = substr(md5(uniqid(rand(), true)), -16, 16);
    
    // save submitted form as a transient object
//    wpcf7ev_debug($wpcf7_form);
    wpcf7ev_save_form_submission(array($wpcf7_form, $random_hash));
    
    // send email to the submitter with a verification link to click on
    wp_mail($submittersEmailAddress , 'Verify your email address', "Please verify your email address by clicking " . 
            get_site_url() . "/?email-verification-key={$random_hash}");
    
    // prevent the form being sent as per usual
    $wpcf7_form->skip_mail = true;
}

// debug function that emails me human-readable information about a variable
function wpcf7ev_debug( $message ) {
    wp_mail( 'support@andrewgolightly.com', 'Debug code', print_r($message, true));
}

/*
    get the tags from the senders field in Contact Form 7 and return the actual
    post_data for those tags
    
    e.g. "[your-name] <[your-email]>" --> "Harry <harry@potter.com>"
*/
function wpcf7ev_get_senders_email_address($wpcf7_form)
{    
    // grab sender's tags
    $senderTags = $wpcf7_form->mail['sender'];
    
    // replace tag names with posted_data using regex
    return $sendersEmailAddress = preg_replace_callback('/\[(.+?)\]/',
                                 function ($matches) use ($wpcf7_form)
                                 {
                                     return $wpcf7_form->posted_data[$matches[1]];
                                 },
                                 $senderTags
                                 );
}



// save the Contact Form 7 object as transient data (lifespan = 4 hours). The object is automatically serialized.
function wpcf7ev_save_form_submission($dataToSave) {

    //todo: create a unique slug    
    $result = set_transient( 'test_slug', $dataToSave , 4 * HOUR_IN_SECONDS );
  //  wpcf7ev_debug("Result of attempting to store array data of size " . count($dataToSave) . " is: {$result}");
    //wpcf7ev_debug($dataToSave);
}

add_action( 'template_redirect', 'check_for_verifier' );

function check_for_verifier() {
    
    if(isset($_GET['email-verification-key']))
    {
        $verification_key = $_GET['email-verification-key'];
    
        if(!empty($verification_key))
        {
            if(false === ($storedValue = get_transient('test_slug')))
            {
                wpcf7ev_debug("Could not find stored value.");
            }
            else
            {
                if($storedValue[1] == $verification_key)
                {
                    wpcf7ev_debug("We have a match!!");
                }
            }
        }
    }
}

?>