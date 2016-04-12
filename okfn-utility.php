<?php

class OKFN_Utility {

    const SLUG = 'okfn-utility';

    var $plugin_dir;

    public static function init() {

        // Load plugin text domain
        add_action( 'init', array( get_class(), 'plugin_textdomain' ) );

        add_action( 'wpmu_options', array( get_class(), 'lock_login_network_options_display' ) );
        add_action( 'update_wpmu_options' , array( get_class(), 'lock_login_network_options_save' ) );
        add_filter( 'authenticate' , array( get_class(), 'lock_login_action' ), 100, 3 );

        add_filter( 'login_message', array ( get_class(),  'password_reset_login_notice') );

        add_filter( 'wp_footer', array ( get_class(),  'pagely_footer_notice') );
        // add_filter( 'allow_password_reset', array ( get_class(),  'disable_reset_lost_password') );

        // add_action( 'init', array( get_class(), 'user_blog_checker_checker' ) );

    } // end init

    function __construct() {
        $this->plugin_dir = ( WPMU_PLUGIN_DIR == dirname(__FILE__) ) ? WPMU_PLUGIN_DIR . '/okfn-utility' : dirname(__FILE__);

        return $this;
    }

    /*--------------------------------------------*
     * Core Functions
     *---------------------------------------------*/


	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {

            $locale = apply_filters( 'plugin_locale', get_locale(), self::SLUG );
            load_textdomain( self::SLUG, WP_LANG_DIR.'/'.self::SLUG.'/'.self::SLUG.'-'.$locale.'.mo' );
            load_plugin_textdomain( self::SLUG, FALSE, plugin_dir_url( __FILE__ ) . 'lang/' );

	} // end plugin_textdomain

    function lock_login_network_options_display() {
        global $okfn_utility;
        wp_nonce_field( plugin_basename( __FILE__ ), 'okfn_login_lock_nonce' );
        $okf_login_lock = get_site_option('okf_login_lock');
        include( $okfn_utility->plugin_dir . '/views/lock-login.php' );
    }

    function lock_login_network_options_save() {


        if ( ( ! current_user_can( 'manage_network_options' ) )
            || ( ! isset( $_POST['okfn_login_lock_nonce'] ) )
            || ( ! wp_verify_nonce( $_POST['okfn_login_lock_nonce'], plugin_basename( __FILE__ ) ) )
            ) return;

        update_site_option( 'okf_login_lock', $_POST['okf_login_lock'] );

    }

    function lock_login_action( $user, $username, $password ) {
        $okf_login_lock = get_site_option('okf_login_lock');

        if ( $okf_login_lock && is_a($user, 'WP_User') ) {
            if ( !is_super_admin( $user->ID ) )
                $user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Login is currently disabled.'));

        }

        return $user;
    }

    function password_reset_login_notice( $message ) {
        if ( empty($message) ){
            return "<p style='margin-bottom: 10px;'>Be advised, due to a server migration all passwords on this system were reset on July 27th, 2013. If you haven't done so yet, please use the 'Lost Your Password' link below to set your own password.</p>";
        }
        else {
            return $message;
        }

    }

    function pagely_footer_notice() {
        if ( DB_NAME == 'db_dom4659' ) {
            global $okfn_utility;
            include( $okfn_utility->plugin_dir . '/views/pagely-footer-notice.php' );
        }
    }

    function disable_reset_lost_password() {
        if ( DB_NAME == 'db_dom4659' ) {
            return false;
        }
        else return true;
    }


    function user_blog_checker_checker() {
        global $wpdb;

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );
        $batch = 200;
        $offset = 0;
        $inactive_users = 0;


        $filename = sanitize_title_with_dashes('Inactive User Export') . "-" . gmdate("Y-m-d", time()) . ".csv";
        $charset = get_option('blog_charset');
        $lines = chr(239) . chr(187) . chr(191);
        $separator = ',';

        $fields = array(
            'ID',
            'user_login',
            'user_email'
        );

        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename");
        header('Content-Type: text/plain; charset=' . $charset, true);
        ob_clean();

        foreach ( $fields as $field_label ) {
            $lines .= '"' . str_replace('"', '""', $field_label) . '"' . $separator;
        }
        $lines.= "\n";


        for ( $i = 1; $i <= ($count/$batch); $i++ ) {
        // for ( $i = 1; $i <= 2; $i++ ) {
            $users = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->users LIMIT %d, %d", $offset, $batch) );
            if ($users) {
                foreach ($users as $user) {
                    $user_blogs = get_blogs_of_user($user->ID);

                    if (empty( $user_blogs )) {
                        foreach ($fields as $field) {
                            $lines .= '"' . str_replace('"', '""', $user->$field) . '"' . $separator;
                        }
                        // $inactive_users++;
                    }
                    $lines = substr($lines, 0, strlen($lines)-1);
                    $lines.= "\n";
                }
            }

            $offset += $batch;
            if ( !seems_utf8( $lines ) )
                $lines = utf8_encode( $lines );

            echo $lines;
            $lines = "";
        }
        // echo $inactive_users;
        die();

    }


} // end class
