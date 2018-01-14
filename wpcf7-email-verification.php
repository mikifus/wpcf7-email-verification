<?php

/**
 * Plugin Name: Contact Form 7 email verification
 * Plugin URI: http://golightlyplus.com/code/#contact-form-7-email-verification
 * Description: Extends Contact Form 7 to allow for email addresses to be verified via a link sent to the sender's email address. There is currently no settings page for this plugin.
 * Version: 0.55
 * Author: Andrew Golightly
 * Author URI: http://www.golightlyplus.com
 * License: GPL2
 */

/*  Copyright 2014  Andrew Golightly  (email : andrew@golightlyplus.com)

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
/**
 * Globals
 */

define('WPCF7EV_UPLOADS_DIR', ABSPATH . 'wp-content/uploads/wpcf7ev_files/');
define('WPCF7EV_STORAGE_TIME', 16 * HOUR_IN_SECONDS);

/**
 * Setup plugin
 */
function wpcf7ev_plugin_setup() {

    // Make plugin available for translation
    // Translations can be filed in the /languages/ directory
    load_plugin_textdomain( 'wpcf7ev', false, basename( dirname( __FILE__ ) ) . '/languages/' );

}
add_action( 'plugins_loaded', 'wpcf7ev_plugin_setup' );



/**
 * Intercept Contact Form 7 forms being sent by first verifying the senders email address.
 */

function wpcf7ev_skip_sending($f) {

    $submission = WPCF7_Submission::get_instance();
    return true; //Set $skip_mail to true

}


/**
 * Request the email address to be verified and save the submission as a transient
 */
add_action( 'wpcf7_before_send_mail', 'wpcf7ev_verify_email_address' );

function wpcf7ev_verify_email_address( $wpcf7_form ) {

    // Check form setings and skip early if form is not set to verify emails.
    $verify = (bool)get_post_meta( $id, 'wpcf7_verify_email', true );

    if( !$verify ) return;

    // first prevent the emails being sent as per usual
    add_filter( 'wpcf7_skip_mail', 'wpcf7ev_skip_sending' );

    // fetch the submitted form details
    $mail_tags = $wpcf7_form->prop( 'mail' );
    $mail_fields = wpcf7_mail_replace_tags( $mail_tags );
    $senders_email_address = $mail_fields['sender'];

    // save any attachments to a temp directory
    $mail_string = trim( $mail_fields['attachments'] );
    if ( strlen ( $mail_string ) > 0 and ! ctype_space( $mail_string ) ) {
        $mail_attachments = explode(" ", $mail_string);
        foreach ( $mail_attachments as $attachment ) {
            $uploaded_file_path = ABSPATH . 'wp-content/uploads/wpcf7_uploads/' . $attachment;
            $new_filepath = WPCF7EV_UPLOADS_DIR . $attachment;
            rename( $uploaded_file_path, $new_filepath );
        }
    }

    // send an email to the recipient to let them know verification is pending
    wp_mail(
        $mail_fields['recipient'],
        __( 'Form notice', 'wpcf7ev' ),
        sprintf(
            __( "Hi,\n\nYou've had a form submission on %1$s from %2$s.\n\nWe are waiting for them to confirm their email address." ),
            get_option('blogname'),
            $senders_email_address
        )
    );

    //create hash code for verification key
    $random_hash = substr(md5(uniqid(rand(), true)), -16, 16);

    // save submitted form as a transient object
    $data_to_save = array($mail_fields, $random_hash);
    set_transient( wpcf7ev_get_slug($random_hash), $data_to_save , WPCF7EV_STORAGE_TIME );

    // send email to the sender with a verification link to click on
    wp_mail(
        $senders_email_address,
        __( 'Verify your email address', 'wpcf7ev' ),
        sprintf(
            __( "Hi,\n\nThanks for your your recent submission on %1$s\n\nIn order for your submission to be processed, please verify this is your email address by clicking on the following link:\n\n%2$s\n\nThanks.", 'wpcf7ev' ),
            get_option( 'blogname' ),
            admin_url( 'admin-ajax.php?action=wpcf7ev&email-verification-key='.$random_hash )
        )
    );
}

add_action('wpcf7_mail_sent', 'wpcf7ev_cleanup');
add_action('wpcf7_mail_failed', 'wpcf7ev_cleanup');

function wpcf7ev_cleanup() {
    // remove the action that triggers this plugin's code
    remove_action( 'wpcf7_before_send_mail', 'wpcf7ev_verify_email_address' );
    remove_filter( 'wpcf7_skip_mail', 'wpcf7ev_skip_sending' ); // allow mail to be sent as per usual
}

/**
 * Create the slug key for the transient CF7 object
 */

function wpcf7ev_get_slug( $random_hash ) {

    return 'wpcf7ev_' . $random_hash;
}

/**
 * Process the clicked link sent to the sender's email address.
 * If the verification key exists in the query string and it is found in the database,
 * the saved form submission gets sent out as per usual.
 */

add_action( 'wp_ajax_wpcf7ev', 'wpcf7ev_check_verifier' );
add_action( 'wp_ajax_nopriv_wpcf7ev', 'wpcf7ev_check_verifier' );

// check the verification key
function wpcf7ev_check_verifier() {

    set_current_screen( 'wpcf7ev' );

    // output the header of the theme being used
    status_header( 200 );
    get_header();

    if ( isset( $_GET['email-verification-key'] ) ) {
        $verification_key = $_GET['email-verification-key'];

        if( ! empty( $verification_key ) ) {
            $slug = wpcf7ev_get_slug( $verification_key );

            // if the stored data is not found, send out an error message
            if( false === ( $storedValue = get_transient( $slug ) ) ) {
                wp_mail(
                    get_settings( 'admin_email' ),
                    __( 'Something went wrong', 'wpcf7ev' ),
                    sprintf(
                        __( 'Someone attempted to verify a link for a form submission and the corresponding key and transient CF7 object could not be found. The verification key used was %s', 'wpcf7ev' ),
                        $verification_key
                    )
                );
                ?>
                <h1><?php _e( 'Whoops! Something went wrong.', 'wpcf7ev' ); ?></h1>
                <ul>
                    <li><?php _e( 'Did you make sure you clicked on the link and not copy-and-pasted it incorrectly?', 'wpcf7ev' ); ?></li>
                    <li><?php _e( "Otherwise it's most likely you took more than a few hours to click the verification link?", 'wpcf7ev' ); ?></li>
                </ul>
                <p><?php _e( 'No problem, please submit your form again.', 'wpcf7ev' ); ?></p>
                <?php
            }
            else {
                $cf7_mail_fields = $storedValue[0]; // get the saved CF7 object
                // create an array of the temp location of any attachments
                $mail_string = trim( $cf7_mail_fields['attachments'] );
                $mail_attachments = ( strlen( $mail_string ) > 0 and !ctype_space( $mail_string ) ) ? array_map( function( $attachment ) {
                    return WPCF7EV_UPLOADS_DIR . $attachment;
                }, explode( " ", $mail_string ) ) : ' ';
                // send out the email as per usual
                wp_mail( $cf7_mail_fields['recipient'], $cf7_mail_fields['subject'], $cf7_mail_fields['body'], '', $mail_attachments );

                // display a confirmation message then redirect back to the homepage after 8 seconds
                ?>
                <h1><?php _e( "Thank you. Verification key accepted.", 'wpcf7ev' ); ?></h1>
                <p><?php _e( "Your form submission will now be processed.", 'wpcf7ev' ); ?></p>
                <p><?php printf( __( 'If you are not redirected back to the homepage in 8 seconds, <a href="%s">click here</a>.', 'wpcf7ev' ), esc_url( get_site_url() ) ); ?></p>
                <script> setTimeout(function () { window.location.href = "<?php echo esc_url( get_site_url() ); ?>"; }, 8000); </script>
                <?php
                delete_transient( $slug );
            }
        }
    }

    get_footer();
    wp_die();

}

/**
 * Clean up any attachments that are older than the transient storage time.
 */

// this hook gets called everytime a form submission is made (verified or not)

add_action( 'wpcf7_mail_sent', 'wpcf7ev_cleanup_attachments' );

function wpcf7ev_cleanup_attachments() {

    if ( $handle = @opendir( WPCF7EV_UPLOADS_DIR ) ) {

        while ( ( $file = readdir( $handle ) ) !== false ) {

            // if the current file is any of these, skip it
            if ( $file == "." || $file == ".." || $file == ".htaccess" )
                continue;

            $file_info = stat( WPCF7EV_UPLOADS_DIR . $file );
            if ( $file_info['mtime'] + WPCF7EV_STORAGE_TIME < time() ) {
                @unlink( WPCF7EV_UPLOADS_DIR . $file );
            }
        }

        closedir( $handle );
    }
}


/**
 * Add new panel to the CF7 form edit screen.
 * Add a setting to verify sender email.
 */

function wpcf7ev_admin_panel ( $panels ) {

    $new_page = array(
          'wpcf7ev-addon-settings' => array(
                  'title' => __( 'Email verification', 'wpcf7ev' ),
                  'callback' => 'wpcf7ev_admin_panel_content'
          )
    );

    $panels = array_merge($panels, $new_page);

    return $panels;

}
add_filter( 'wpcf7_editor_panels', 'wpcf7ev_admin_panel' );


function wpcf7ev_admin_panel_content( $cf7 ){

    $id =  $cf7->id();
    $verify = (bool)get_post_meta( $id, 'wpcf7_verify_email', true );

    ?>

    <p>
        <input type="checkbox" name="vpcf7-verify-email" id="vpcf7-verify-email" value="1" <?php echo checked( $verify ); ?> >
        <label for="vpcf7-verify-email"><?php _e( 'Verify sender email', 'wpcf7ev' ); ?></label>
    </p>

    <?php

}


function cf7hsfi_admin_save_form( $cf7 ) {

    $post_id = $cf7->id();

    update_post_meta( $post_id, 'wpcf7_verify_email', (bool)$_POST['vpcf7-verify-email'] );

}
add_action( 'wpcf7_save_contact_form', 'cf7hsfi_admin_save_form' );


?>
