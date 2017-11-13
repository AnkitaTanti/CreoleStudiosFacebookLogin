<?php
/*
Plugin Name: Creole Studios Facebook Login
Description: Shortcode Based Country Wise Facebook Login Buttons on Different Pages
Version: 1.0
Author: Ankita Tanti
*/


define('CSFL_PLUGIN_DIR', plugin_dir_path('__FILE__'));

// DON'T ALLOW DIRECT ACCESS TO FILE
if ( ! defined( 'ABSPATH' ) ) {
    die( '-1' );
}

require_once ( plugin_dir_path( __FILE__ ) . 'resources/CSFL_Common_Task_Manager.php');
require_once ( plugin_dir_path( __FILE__ ) . 'CSFL_Install_Tables.php');

// PLUGIN TABLE MANAGEMENT CLASS
global $csfl_db_version;
$csfl_db_version = '1.0';
$install_tbl = new CSFL_Install_Tables();
register_activation_hook( __FILE__, array($install_tbl,'csfl_install') );

// NECESSORY CSS AND JS INCLUSION
$common_tasks_obj = new CSFL_Common_Task_Manager();
register_activation_hook( __FILE__, array($common_tasks_obj,'enqueue_public_scripts_and_styles') );

/*
 * IMPORT THE FACEBOOK SDK AND LOAD ALL THE CLASSES
 */
include (plugin_dir_path( __FILE__ ) . 'facebook_sdk5/autoload.php');

/*
 * CLASSES REQUIRED TO CALL FACEBOOK API
 * WHICH WILL BE USER IN OUR CLASS
 */
use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;


/**
 * Class CSFL_Manager
 */
class CSFL_Manager
{

    /**
     * FACEBOOK APP ID
     *
     * @var string
     */
    private $app_id = '';

    /**
     * FACEBOOK APP SECRET
     *
     * @var string
     */
    private $app_secret = '';
    
    /**
     * ACCESS TOKEN RETRIEVED FROM FACEBOOK
     *
     * @var string
     */
    private $access_token;
    
    /**
     * USER DETAILS RECEIVED FROM THE FACEBOOK API
     *
     * @var array 
     */
    private $user_details;
    
    public function __construct()
    {

        // CSFL SHORTCODE REGISTRATION
        add_shortcode( 'CS-FACEBOOK-LOGIN', array($this, 'csfl_front_button') );

        // CALLBACK AJAX FOR LOGIN/REGISTRATION
        add_action( 'wp_ajax_nopriv_csfl_ajax_facebook_login', array($this,'csfl_ajax_facebook_login'));  
        add_action( 'wp_ajax_csfl_ajax_facebook_login', array($this,'csfl_ajax_facebook_login'));
        
        // CALLBACK AJAX FOR PROCESSING FACEBOOK LOGIN/REGISTRATION
        add_action( 'wp_ajax_nopriv_csfl_process_facebook_login', array($this,'csfl_process_facebook_login'));  
        add_action( 'wp_ajax_csfl_process_facebook_login', array($this,'csfl_process_facebook_login'));
    }
    
    /**
     * DISPLAY FRONT END FACEBOOK LOGIN FORM
     *
     * @return Front End Html
     */
    function csfl_front_button($atts)
    {   
        // TO HIDE LOGIN WITH FACEBOOK BUTTON IF USER IS ALREADY LOGGED IN
        if(is_user_logged_in())
            return;

        $return_string = "";
        
        // REDIRECTION URL
        if(!isset($_SESSION['csfl_redirection_url']))
            $_SESSION['csfl_redirection_url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        
        if(isset($_SESSION['csfl_error_msg']) && $_SESSION['csfl_error_msg']!="")
        {
            $return_string.= "<p>".$_SESSION['csfl_error_msg']."</p>";
            $_SESSION['csfl_error_msg'] = "";
        }
        
        if(isset($atts['csfl_id']))
            $return_string .= '<input type="hidden" name="csfl_id" id="csfl_id" value="'.$atts['csfl_id'].'" />';
        
        $return_string.='<div id="cs_facebooklogin" data-social="facebook">'.__('Login with Facebook','csfl').'</div>';
        return $return_string;
    }
    
    /**
     * INITIALISE FACEBOOK FACEBOOK API CONNECTION
     *
     * @return Facebook Object
     */
    private function facebookInit() 
    {
        $facebook = new Facebook([
            'app_id' => $this->app_id,
            'app_secret' => $this->app_secret,
            'default_graph_version' => 'v2.5'
        ]);

        return $facebook;
    }
    
    /**
     * GET ACCESS TOKEN FROM THE FACEBOOK API OR REDIRECT IN CASE THERE'S ANY ERROR 
     *
     * @param $fb Facebook Object
     * @return string - The Token
     */
    private function getFbAccessToken($fb) {

        $helper = $fb->getRedirectLoginHelper();
        
        try {
            $accessToken = $helper->getAccessToken();
        } 
        catch(Facebook\Exceptions\FacebookResponseException $e) {
             // WHEN GRAPH RETURNS AN ERROR
            $error = __('Graph returned an error: ','csfl').$e->getMessage();
        } 
        catch(Facebook\Exceptions\FacebookSDKException $e) {
            // WHEN VALIDATION FAILS OR OTHER LOCAL ISSUES
            $error = __('Facebook SDK returned an error: ','csfl') . $e->getMessage();
        }

        // NO ACCESS TOKEN RETRIEVED IT MEANS THERE'S SOME ERROR
        if (!isset($accessToken)) 
        {
            $_SESSION['csfl_error_msg'] = $error;
            
            // REDIRECTING AS ERROR OCCURED
            wp_redirect( $this->redirect_url ); exit(); 
        }
        return $accessToken;
    }
    
    /**
     * GET USER DETAILS FROM FACEBOOK API
     *
     * @param $fb Facebook Object
     * @return \Facebook\GraphNodes\GraphUser
     */
    private function getUserDetails($fb)
    {
        // THE OAuth 2.0 CLIENT HANDLER HELPS US MANAGE ACCESS TOKENS
        $oAuth2Client = $fb->getOAuth2Client();

        // GET THE ACCESS TOKEN METADATA FROM /debug_token
        $tokenMetadata = $oAuth2Client->debugToken($this->access_token);

        // VALIDATION (THESE WILL THROW FacebookSDKException's WHEN THEY FAIL)
        $tokenMetadata->validateAppId($this->app_id); 

        $tokenMetadata->validateExpiration();

        if (! $this->access_token->isLongLived()) {
            // EXCHANGES A SHORT-LIVED ACCESS TOKEN FOR A LONG-LIVED ONE
            try {
              $this->access_token = $oAuth2Client->getLongLivedAccessToken($this->access_token);
            } 
            catch (Facebook\Exceptions\FacebookSDKException $e) {
              $error = "<p>".__('Error getting long-lived access token: ','csfl') . $helper->getMessage() . "</p>\n\n";
            }
        }
        
        $_SESSION['fb_access_token'] = (string) $this->access_token;
        try {
            // RETURNS A `Facebook\FacebookResponse` OBJECT
            $response = $fb->get('/me?fields=id,email,name,first_name,last_name,link', $this->access_token);
        } 
        catch(Facebook\Exceptions\FacebookResponseException $e) {
            $error = __('Graph returned an error: ','csfl').$e->getMessage();
        } 
        catch(Facebook\Exceptions\FacebookSDKException $e) {
            $error = __('Facebook SDK returned an error: ','csfl') . $e->getMessage();
        }
        
        if (isset($error)) {
            $_SESSION['csfl_error_msg'] = $error;
            
            // REDIRECTING AS ERROR OCCURED
            wp_redirect( $this->redirect_url ); exit(); 
        }
        return $response->getGraphUser();
    }
    // end getUserDetails()
    
    // AJAX CALLBACK
    public function csfl_ajax_facebook_login() 
    {
        if(!session_id()) {
            session_start();
        }

        // REDIRECTION URL 
        $this->redirect_url = (isset($_SESSION['csfl_redirection_url'])) ? $_SESSION['csfl_redirection_url'] : home_url();
        
        if(isset($_POST['csfl_id']) && $_POST['csfl_id'])
        {
            $csfl_id = $_POST['csfl_id'];
            global $wpdb;
            $prefix = $wpdb->prefix;
            $tablename = $prefix.'facebook_credentials';
            
            $app_details = $wpdb->get_row( "SELECT * FROM $tablename where id = ". $csfl_id );
            if ( null !== $app_details ) 
            {
                $this->app_id = $app_details->app_id;
                $this->app_secret = $app_details->app_secret;
                
                $fb = $this->facebookInit();
                $helper = $fb->getRedirectLoginHelper();
                $permissions = ['email'];
                $callback_url = admin_url('admin-ajax.php')."?action=csfl_process_facebook_login&csfl_id=$csfl_id"; 
                print $loginUrl = $helper->getLoginUrl($callback_url, $permissions);

                exit();
            }
            else
            {
                // THROW AN ERROR AND REDIRECT
                $_SESSION['csfl_error_msg'] = __('Oops! Someting went wrong!','csfl');
            
                // REDIRECTING AS ERROR OCCURED
                echo $this->redirect_url; exit();
            }
        }
        else
        {
            // THROW AN ERROR AND REDIRECT
            $_SESSION['csfl_error_msg'] = __('Oops! Someting went wrong!','csfl');
            
            // REDIRECTING AS ERROR OCCURED
            echo $this->redirect_url; exit();
        }
    }
    public function csfl_process_facebook_login()
    {
        if(!session_id()) {
            session_start();
        }

        // REDIRECTION URL 
        $this->redirect_url = (isset($_SESSION['csfl_redirection_url'])) ? $_SESSION['csfl_redirection_url'] : home_url();

        
        if(isset($_REQUEST['csfl_id']) && $_REQUEST['csfl_id'])
        {
            $csfl_id = $_REQUEST['csfl_id'];
            global $wpdb;
            $prefix = $wpdb->prefix;
            $tablename = $prefix.'facebook_credentials';
            
            $app_details = $wpdb->get_row( "SELECT * FROM $tablename where id = ". $csfl_id );
            if ( null !== $app_details ) 
            {
                $this->app_id = $app_details->app_id;
                $this->app_secret = $app_details->app_secret;
                
                // FACEBOOK CONNECTION
                $fb = $this->facebookInit();

                $helper = $fb->getRedirectLoginHelper();

                // TOKEN SAVED IN OUR INSTANCE
                $this->access_token = $this->getFbAccessToken($fb);

                $this->user_details  = $this->getUserDetails($fb);

                // CREATE NEW USER ACCOUNT IF ABOVE FUNCTION FAILS
                $this->createUser();

                wp_redirect( $this->redirect_url ); exit(); 
                
            }
            else
            {
                // THROW AN ERROR AND REDIRECT
                $_SESSION['csfl_error_msg'] = __('Oops! Someting went wrong!','csfl');
            
                // REDIRECTING AS ERROR OCCURED
                echo $this->redirect_url; exit();
            }
        }
        else
        {
            // THROW AN ERROR AND REDIRECT
            $_SESSION['csfl_error_msg'] = __('Oops! Someting went wrong!','csfl');
            
            // REDIRECTING AS ERROR OCCURED
            echo $this->redirect_url; exit();
        }
        
    } 
    // end csfl_process_facebook_login()
    
    /**
     * Create a new WordPress account using Facebook Details
     */
    private function createUser() {
        
        $user = $this->user_details;
        
        // REDIRECTION URL 
        $this->redirect_url = (isset($_SESSION['csfl_redirection_url'])) ? $_SESSION['csfl_redirection_url'] : home_url();

        // CREATE A USER NAME WITHOUT SPACES
        $username = sanitize_user(str_replace(' ', '-', strtolower($user['name'])));

        // CREATING NEW USER ACCOUNT USING PROVIDED DETAILS
        $new_user = wp_create_user($username, wp_generate_password(), $user['email']);
        
        if(is_wp_error($new_user)) 
        {
            
            // CHECK IF THE USER IS REGISTED USING FACEBOOK
            $wp_user = get_users(array(
                'search'       => $username,
                'meta_key'     => 'csfl_user_fb_id',
                'number'       => 1,
                'count_total'  => false,
                'fields'       => 'id',
                'role'         => 'subscriber'
            ));
            
            // IF NOT, THE SAME USER IS REGISTERED USING SOME OTHER METHOD
            if(empty($wp_user[0])) 
            {   
                $error_arr =  (array) $new_user;
                    
                // CHECK IF USER NAME EXIST 
                if(array_key_exists('existing_user_login',$error_arr['errors']))
                {
                    //THROW AN ERROR AS USER ALREADY REGISTERED USING SOME OTHER LOGIN METHOD WITH SAME USER NAME
                    $_SESSION['csfl_error_msg'] = $new_user->get_error_message();
                }
                else if(array_key_exists('existing_user_email',$error_arr['errors']))
                {
                    // REGISTER USER WITH USER NAME AND PASSWORD ONLY(WITHOUT ANY EMAIL ADDRESS)
                    $new_user  = wp_create_user( $username, wp_generate_password(),'');  
                }
                else
                {
                    $_SESSION['csfl_error_msg'] = $new_user->get_error_message();
                    wp_redirect( $this->redirect_url ); 
                    exit(); 
                }
            }
            else
            {
                // ELSE YES, ALLOW THAT USER TO LOGIN
                $new_user = $wp_user[0];
            }
        }

        if(isset($user['first_name']) && $user['first_name']!="") 
            update_user_meta( $new_user, 'first_name', $user['first_name'] );
        if(isset($user['last_name']) && $user['last_name']!="")
            update_user_meta( $new_user, 'last_name', $user['last_name'] );
        
        update_user_meta( $new_user, 'csfl_user_url', $user['link'] );
        update_user_meta( $new_user, 'csfl_user_fb_id', $user['id'] );
        
        // LOGIN EXISTING FACEBOOK USER/NEW REGISTERED FACEBOOK USER
        wp_set_auth_cookie( $new_user );
    }
    // end createUser()
}

new CSFL_Manager();