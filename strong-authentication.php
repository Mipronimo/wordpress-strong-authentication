<?php
/*
Plugin Name: Strong Authentication
Plugin URI: https://github.com/cornelinux/wp-strong-authentication
Description: Wordpress Strong Authentication lets you authenticate users with a second factor of possession like OTP, EMail or SMS. Only if the user is able to provide this second factor, he is allowed to login.
	
	Such a second factor can be an OTP display card, an OTP hardware token, a Yubikey,
	a smartphone App like the Google Authenticator or access to an mobile phone
	to receive an SMS or access to an email account.
	
	The use then needs to authenticate with his wordpress password and in addition with
	a code, generated by his device or sent via email or SMS.
	
	All the devices are managed in the backend (privacyIDEA)[http://privacyidea.org], the Strong Authentication
	plugin forwards authentication requests to this backend, which you can easily run
	on the same machine or anywhere in your network.
	
Version: 0.9
Author: Cornelius Kölbel

    Copyright 2013 Cornelius Kölbel (corny@cornelinux.de)

    This program is free software; you can redistribute it and/or modify
    it  under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/



class StrongAuthentication {
        private $server, $verify_peer, $verify_host;

        public function __construct( $server = "localhost",  $verify_peer=0, $verify_host=0) {
                $this->server=$server;
                # can be 0 or 2
                #$verify_host = 0;
                #$verify_peer = 0;
                $this->verify_peer=$verify_peer;
                $this->verify_host=$verify_host;
        }


        public function strong_auth($user="", $pass="", $realm="") {
                $ret=false;
                try {
                        $server = $this->server;
                        $REQUEST="https://$server/validate/check?user=$user&pass=$pass";
                        if(""!=$realm)
                                $REQUEST="$REQUEST&realm=$realm";

                        if(!function_exists("curl_init"))
                                die("PHP cURL extension is not installed");

                        $ch=curl_init($REQUEST);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verify_host);
                        $r=curl_exec($ch);
                        curl_close($ch);


                        $jObject = json_decode($r);
                        if (true == $jObject->{'result'}->{'status'} )
                                if (true == $jObject->{'result'}->{'value'} )
                                        $ret=true;
                } catch (Exception $e) {
			error_log("Error in receiving response from Authentication server: $e");
                }
                return $ret;
        }
}

function strong_auth_activate(){
	add_option('strong_authentication_server',"localhost:5001","The FQDN of the Authentication server. This server must be reached via https.");
	add_option('strong_authentication_verify_host',0,"Wether the hostname of the certificate shall be verified (0 or 2)");
	add_option('strong_authentication_verify_peer',0,"Wether the certificate shall be verified (0 or 2)");
	add_option('strong_authentication_realm',"","The Realm in the Authentication server. Leave empty if you want to use the default realm.");
	add_option('strong_authentication_exclude_users',"","User who do not need to do strong authentication");
}


function strong_auth_init(){
	register_setting('strong_auth','strong_authentication_server');
	register_setting('strong_auth','strong_authentication_verify_host');
	register_setting('strong_auth','strong_authentication_verify_peer');
	register_setting('strong_auth','strong_authentication_realm');
	register_setting('strong_auth','strong_authentication_exclude_users');
}

//page for config menu
function strong_auth_add_menu() {
	add_options_page("Strong Authentication", "Strong Authentication", 10, __FILE__,"strong_auth_display_options");
}

//actual configuration screen
function strong_auth_display_options() { 
?>
	<div class="wrap">
	<h2>Strong Authentication</h2>        
	<form method="post" action="options.php">
	<?php settings_fields('strong_auth'); ?>
        <h3>privacyIDEA Settings</h3>
          <strong>Make sure your admin accounts also exist in the privacyIDEA server.</strong>
        <table class="form-table">
        <tr valign="top">
            <th scope="row"><label>Authentication server name</label></th>
				<td><input type="text" name="strong_authentication_server" 
				value="<?php echo get_option('strong_authentication_server'); ?>" /> </td>
				<td><span class="description"><strong style="color:red;">required</strong>
				The FQDN of the privacyIDEA server.</span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Realm</label></th>
				<td><input type="text" name="strong_authentication_realm" 
				value="<?php echo get_option('strong_authentication_realm'); ?>" /> </td>
				<td><span class="description">The realm of the user in the 
					privacyIDEA server. Leave empty if you use default realm.</span> </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Verify Host</label></th>
				<td><select name="strong_authentication_verify_host">
				<option value=0  <?php 
									if (get_option('strong_authentication_verify_host')==0)
										print "selected";
								 	?>
				>do not verify</option>
				<option value=2 <?php 
									if (get_option('strong_authentication_verify_host')==2)
										print "selected";
								 	?>
				>verifiy hostname</option> 
				
				</select></td>
				<td><span class="description">If you choose to verify the hostname, 
				the given hostname must match the common name in the SSL certificate
				of the peer.
				</span></td>
        </tr>        
        <tr valign="top">
            <th scope="row"><label>Verify Peer</label></th>
				<td><select name="strong_authentication_verify_peer">
				<option value=0  <?php 
									if (get_option('strong_authentication_verify_peer')==0)
										print "selected";
								 	?>
				>do not verify</option>
				<option value=2 <?php 
									if (get_option('strong_authentication_verify_peer')==2)
										print "selected";
								 	?>
				>verifiy peer</option> 
				</select> </td>
				<td><span class="description">If you choose to verify the peer, 
				the SSL certificate of the peer is verified, if it is valid,
				i.e. if it has a correct signature and is not revoked.
				If you want to use self signed certificates you need to
				choose "do not verify".
				</span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Exclude users</label></th>
				<td><input type="text" name="strong_authentication_exclude_users" 
				value="<?php echo get_option('strong_authentication_exclude_users'); ?>" /> </td>
				<td><span class="description">This is a comma seperated list of
				users, that will not authenticate against the privacyIDEA server
				with OTP, but only need to authenticate with their wordpress
				password. These users will also be able to authenticate if you
				misconfigured this plugin or your privacyIDEA server went down. 
				</span></td>
        </tr>
        </table>	
	<p class="submit">
	<input type="submit" name="Submit" value="Save changes" />
	</p>
	</form>
	</div>
<?php
}


if ( !function_exists("wp_authenticate") ) :
function wp_authenticate($username,$password) {
	$username = sanitize_user($username);
    $password = trim($password);
	$user = null;

	// The users, who do not need to do strong auth!
	$exclude_users_string = get_option('strong_authentication_exclude_users');
	$exclude_users = preg_split("/[\s,]+/", $exclude_users_string);
	
	if (in_array($username, $exclude_users)) {
		/*
		 * The user is in the exclusion list, 
		 * we do normal authentication
		 */
		$user = apply_filters( 'authenticate', null, $username, $password );
	} else {
		/* The user is not in the exclusion list, so we
		 * do strong authentication!
		 */
		// get the server name
		$server = get_option('strong_authentication_server');
		// get SSL options
    	$verify_peer = get_option('strong_authentication_verify_peer');
    	$verify_host = get_option('strong_authentication_verify_host');
    	$realm = get_option('strong_authentication_realm');
        $l = new StrongAuthentication( $server, $verify_peer, $verify_host );
        $r = $l->strong_auth($username, $password, $realm);
	
		if ($r) {
			$user = new WP_User( $username );
		}    
   }
   if ( $user == null ) {
	   	$user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.'));
   }
   $ignore_codes = array('empty_username', 'empty_password');
   if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes) ) {
   		do_action('wp_login_failed', $username);
   }
   return $user;	
}
endif;

add_action('admin_init', 'strong_auth_init' );
add_action('admin_menu', 'strong_auth_add_menu');

register_activation_hook( __FILE__, 'strong_auth_activate' );
