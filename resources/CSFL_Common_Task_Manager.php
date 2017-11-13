<?php

require_once ( dirname(__FILE__) . '/Facebook_Credential_List_Table.php');

class CSFL_Common_Task_Manager 
{
    public function __construct() {
        $dir = dirname( __FILE__ );
        
        // ACTION TO ENQUEUE FRONT END SCRIPTS
        add_action('wp_enqueue_scripts', array($this,'enqueue_public_scripts_and_styles')); 
        
        // FILTER TO ADD DEFER FOR PLUGIN SCRIPTS
        add_filter( 'script_loader_tag', function ( $tag, $handle ) 
        {
            $scripts_to_defer = array('jquery', 'csfl-custom');
            foreach($scripts_to_defer as $defer_script) {
               if ($defer_script === $handle) {
                  return str_replace(' src', ' defer="defer" src', $tag);
               }
            }
            return $tag;
        }, 10, 2);
        
        // ACTION TO START SESSION IS NOT STARTED
        add_action( 'init', function () {
            if( !session_id() )
                session_start();
        });
        
        // ACTION TO ADD PLUGIN ADMIN MENU/PAGE
        add_action('admin_menu',array($this,'csfl_admin_menu'));
        
        add_action( 'admin_action_save_credentials', array($this,'save_credentials_admin_action') );

    }
    
    //ENQUEUE PUBLIC STYLES AND SCRIPTS
    public function enqueue_public_scripts_and_styles()
    {
        // ENQUEUE STYLE FOR FRONT END
        wp_enqueue_style('csfl_style', plugins_url ( 'css/style.css', __FILE__ ), array(), '1.0', 'all');  
        wp_enqueue_style('font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css'); 

        if ( ! wp_script_is( 'jquery', 'enqueued' )) {
            wp_enqueue_script( 'jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js' );
        }

        // ENQUEUE JQUERY FOR FRONT END
        wp_enqueue_script( 'csfl-custom', plugins_url ( 'js/custom.js', __FILE__ ), 'jquery', '1.0', true);
        wp_localize_script('csfl-custom', 'control_vars', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
    } 
    
    public function csfl_admin_menu(){
        add_menu_page( 'Manage Facebook Credentials', 'Manage Facebook Credentials', 'manage_options', 'csfl-manage-fb-credentials',array($this,'manage_facebook_details'));
        add_submenu_page('csfl-manage-fb-credentials','Add New Credentials','Add New Credentials', 'manage_options', 'csfl-add-fb-credentials', array($this,'add_facebook_details'));
    }
     
    
     public function save_credentials_admin_action()
    {
        if(isset($_POST['submit']) && $_POST['submit']=="Save Changes")
        {
            $fb_app_country = $_POST['fb_app_country'];
            $csfl_id = $_POST['csfl_id'];
            
            if((isset($_POST['fb_app_id']) && $_POST['fb_app_id']!="") && (isset($_POST['fb_app_secret']) && $_POST['fb_app_secret']!=""))
            {
                $fb_app_id = $_POST['fb_app_id'];
                $fb_app_secret = $_POST['fb_app_secret'];
            }
            else
            {
                $_SESSION['csfl_admin_msg'] = '<div id="error" class="notice notice-error is-dismissible"><p><strong>'.__('ERROR','csfl').'</strong>: '.__('Facebook App ID can not be blank.','csfl').'</p></div>';
                
                wp_redirect(admin_url( 'admin.php?page=csfl-add-fb-credentials' , is_ssl() ? 'https' : 'http'));
                exit();
            }
            
            global $wpdb;
            $prefix = $wpdb->prefix;
            $tablename = $prefix.'facebook_credentials';
            
            // CHECK FOR DUPLICATION
            if(isset($csfl_id) && $csfl_id!="")
                $app_cnt = $wpdb->get_var( "SELECT COUNT(*) FROM $tablename where app_id = '". $fb_app_id ."' AND app_secret='".$fb_app_secret."' AND id!=".$csfl_id );
            else
                $app_cnt = $wpdb->get_var( "SELECT COUNT(*) FROM $tablename where app_id = '". $fb_app_id ."' AND app_secret='".$fb_app_secret."'" );
            
            if($app_cnt > 0)
            {
                 $_SESSION['csfl_admin_msg'] = '<div id="error" class="notice notice-error is-dismissible"><p><strong>'.__('DUPLICATION ERROR','csfl').'</strong>: '.__('This Facebook App ID and App Secret already registered. Please enter another one.','csfl').'</p></div>';
                
                wp_redirect(admin_url( 'admin.php?page=csfl-add-fb-credentials' , is_ssl() ? 'https' : 'http'));
                exit();
            }
            else
            {
                $query_args = array( 'country_nm'=>$fb_app_country, 'app_id'=>$fb_app_id, 'app_secret'=>$fb_app_secret);

                if(isset($csfl_id) && $csfl_id!=""){
                    $wpdb->update( $tablename, $query_args, array( 'ID' => $csfl_id ) );
                    $url_to_pass = 'admin.php?page=csfl-add-fb-credentials&action=edit&facebook_credential='.$csfl_id;
                }
                else{
                    $wpdb->insert( $tablename, $query_args );
                    $url_to_pass = 'admin.php?page=csfl-add-fb-credentials&action=edit&facebook_credential='.$wpdb->insert_id;
                }
                
                $_SESSION['csfl_admin_msg'] = '<div id="message" class="updated notice notice-success is-dismissible"><p><strong>'.__('SUCCESS','csfl').'</strong>: '.__('Facebook Credential Saved.','csfl').'</p></div>';
             
                $url = admin_url( $url_to_pass , is_ssl() ? 'https' : 'http');
                wp_redirect($url);  
                exit();
            }
        }
        wp_redirect(admin_url( 'admin.php?page=csfl-add-fb-credentials' , is_ssl() ? 'https' : 'http'));
        exit();
    }
    
    public function add_facebook_details()
    {
        // INITIALIZATION
        $country_nm = $app_id = $app_secret = $csfl_id = "";
        $title = '<h1>Add Facebook Details</h1>';
        if(isset($_REQUEST['action']) && $_REQUEST['action']=='edit' && isset($_REQUEST['facebook_credential']) && $_REQUEST['facebook_credential']!="")
        {
            $csfl_id = $_REQUEST['facebook_credential'];
            global $wpdb;
            $prefix = $wpdb->prefix;
            $tablename = $prefix.'facebook_credentials';
            $app_details = $wpdb->get_row( "SELECT * FROM $tablename where id = ". $csfl_id );
            if ( null !== $app_details ) 
            {
                $country_nm = $app_details->country_nm;
                $app_id = $app_details->app_id;
                $app_secret = $app_details->app_secret;
                $title = sprintf('<a href="%s" class="page-title-action">%s</a>',admin_url( 'admin.php?page=csfl-add-fb-credentials' , is_ssl() ? 'https' : 'http'),__('Add New Credentials','csfl'));
            }
        }

    ?>
        <div class="wrap">
            <?php 
                echo $title; 
                if(isset($_SESSION['csfl_admin_msg']) && $_SESSION['csfl_admin_msg']!="")
                {
                    echo $_SESSION['csfl_admin_msg'];
                    $_SESSION['csfl_admin_msg'] = "";
                }
            ?>
            
            <form action="<?php echo admin_url( 'admin.php' , is_ssl() ? 'https' : 'http'); ?>" method="post">
                <table class="form-table">
                    <tbody>
                        <?php
                            $countries = array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", 
                                                "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria",
                                                "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize",
                                                "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island",
                                                "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso",
                                                "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic",
                                                "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo",
                                                "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", 
                                                "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", 
                                                "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea",
                                                "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji",
                                                "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", 
                                                "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", 
                                                "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", 
                                                "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", 
                                                "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", 
                                                "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", 
                                                "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", 
                                                "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", 
                                                "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", 
                                                "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", 
                                                "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", 
                                                "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia",
                                                "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", 
                                                "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", 
                                                "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", 
                                                "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", 
                                                "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", 
                                                "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", 
                                                "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", 
                                                "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", 
                                                "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", 
                                                "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", 
                                                "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay",
                                                "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", 
                                                "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");
                        ?>
                        <tr class="fb-app-country-wrap">
                            <th><label for="fb_app_id"><?php _e('Select Country:','csfl'); ?></label></th>
                            <td>
                                <select name="fb_app_country" id="fb_app_country">
                                    <?php 
                                        foreach ($countries as $value):
                                            $selected = "";
                                            if($country_nm == $value)
                                                $selected = ' selected="selected"';
                                        ?>
                                        <option value="<?php echo $value; ?>" <?php echo $selected; ?>>
                                        <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="fb-app-id-wrap">
                            <th><label for="fb_app_id"><?php _e('Facebook App ID:','csfl'); ?></label></th>
                            <td><input type="text" name="fb_app_id" id="fb_app_id" value="<?php echo $app_id; ?>" class="regular-text"></td>
                        </tr>
                        <tr class="fb-app-secret-wrap">
                            <th><label for="fb_app_secret"><?php _e('Facebook App Secret:','csfl'); ?></label></th>
                            <td><input type="text" name="fb_app_secret" id="fb_app_secret" value="<?php echo $app_secret; ?>" class="regular-text"></td>
                        </tr>
                    <tbody>
                </table> 
                <input type="hidden" name="csfl_id" id="csfl_id" value="<?php echo $csfl_id; ?>" />
                <input type="hidden" name="action" value="save_credentials" />
                <?php submit_button();?>
            </form>
        </div>
    <?php
    }
    
    public function manage_facebook_details()
    {
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Manage Facebook Credentials','csfl'); ?></h1>
             <?php
                echo sprintf('<a href="%s" class="page-title-action">%s</a>',admin_url( 'admin.php?page=csfl-add-fb-credentials' , is_ssl() ? 'https' : 'http'),__('Add New Credentials','csfl'));
            ?>
            <form method="post">
                <?php
                $exampleListTable = new Facebook_Credential_List_Table();
                $exampleListTable->prepare_items();
                $exampleListTable->display();
                ?>
            </form>
        </div>
    <?php
    }
}
?>