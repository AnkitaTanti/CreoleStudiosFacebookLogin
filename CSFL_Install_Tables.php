<?php
class CSFL_Install_Tables  
{
    function csfl_install()
    {
        global $wpdb;
        global $csfl_db_version;
        
        $csfl_credential_tbl = $wpdb->prefix . 'facebook_credentials';
    	$charset_collate = $wpdb->get_charset_collate();

    	$sql = "CREATE TABLE $csfl_credential_tbl (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    country_nm varchar(255) NOT NULL,
                    app_id varchar(255) NOT NULL,
                    app_secret varchar(255) NOT NULL,
    		PRIMARY KEY  (id)
    	) $charset_collate;";

    	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    	dbDelta( $sql );

    	add_option( 'reg_db_version', $csfl_db_version );

    }
}
?>