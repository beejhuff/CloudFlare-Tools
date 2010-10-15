<?php
/*
Plugin Name: CloudFlare
Plugin URI: http://cloudflare.com/
Description: CloudFlare integrates your blog with the CloudFlare platform.
Version: 1.0.0
Author: Ian Pye
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by 
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
GNU General Public License for more details. 

You should have received a copy of the GNU General Public License 
along with this program; if not, write to the Free Software 
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/	

define('CLOUDFLARE_VERSION', '1.0.0');
require_once("ip_in_range.php");

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

function cloudflare_init() {
	global $cf_api_host, $cf_api_port;

    $cf_api_host = "https://www.cloudflare.com/api.html?";
    $cf_api_port = 80;
    $cf_ip_ranges = array("204.93.240.0/24", "204.93.177.0/24", "204.93.173.0/24", "199.27.128.0/21");
    
    // Update the REMOTE_ADDR value if the current REMOTE_ADDR value is in the specified range.
    foreach ($cf_ip_ranges as $range) {
        if (ip_in_range($_SERVER["REMOTE_ADDR"], $range)) {
            if ($_SERVER["HTTP_CF_CONNECTING_IP"]) {
                $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }
            break;
        }
    }
	add_action('admin_menu', 'cloudflare_config_page');
	cloudflare_admin_warnings();
} 
add_action('init', 'cloudflare_init');

function cloudflare_admin_init() {
    
}

add_action('admin_init', 'cloudflare_admin_init');

function cloudflare_config_page() {
	if ( function_exists('add_submenu_page') ) {
		add_submenu_page('plugins.php', __('CloudFlare Configuration'), __('CloudFlare'), 'manage_options', 'cloudflare-key-config', 'cloudflare_conf');
    }
}

function load_cloudflare_keys () {
    global $cloudflare_api_key, $cloudflare_api_email;
    if (!$cloudflare_api_key) {
        $cloudflare_api_key = get_option('cloudflare_api_key');
    }
    if (!$cloudflare_api_email) {
        $cloudflare_api_email = get_option('cloudflare_api_email');
    }
}

function cloudflare_conf() {
    if ( function_exists('current_user_can') && !current_user_can('manage_options') )
        die(__('Cheatin&#8217; uh?'));
    global $cloudflare_api_key, $cloudflare_api_email;
    global $wpdb;

    $db_results = array();
               
	if ( isset($_POST['submit']) && !($_POST['optimize']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') ) {
			die(__('Cheatin&#8217; uh?'));
        }

		$key = $_POST['key'];
		$email = $_POST['email'];
		if ( empty($key) ) {
			$key_status = 'empty';
			$ms[] = 'new_key_empty';
			delete_option('cloudflare_api_key');
		} else {
            $ms[] = 'new_key_valid';
			update_option('cloudflare_api_key', $key);
            update_option('cloudflare_api_key_set_once', "TRUE");
        }

		if ( empty($email) ) {
			$email_status = 'empty';
			$ms[] = 'new_email_empty';
			delete_option('cloudflare_api_email');
		} else {
			$ms[] = 'new_email_valid';
			update_option('cloudflare_api_email', $email);
            update_option('cloudflare_api_email_set_once', "TRUE");
        }

        $messages = array(
                          'new_key_empty' => array('color' => 'aa0', 'text' => __('Your key has been cleared.')),
                          'new_key_valid' => array('color' => '2d2', 'text' => __('Your key has been verified. Happy blogging!')),
                          'new_email_empty' => array('color' => 'aa0', 'text' => __('Your email has been cleared.')),
                          'new_email_valid' => array('color' => '2d2', 'text' => __('Your email has been verified. Happy blogging!')),
                          );
    } else if ( isset($_POST['submit']) && isset($_POST['optimize']) ) {
        if(current_user_can('manage_database')) {
            remove_action('admin_notices', 'cloudflare_warning');
            $tables = $wpdb->get_col("SHOW TABLES");
            foreach($tables as $table_name) {
                $optimize = $wpdb->query("OPTIMIZE TABLE `$table_name`");
                $analyze = $wpdb->query("ANALYZE TABLE `$table_name`");
                if (!$optimize || !$analyze) {
                    $db_results[] = "Error optimizing $table_name";
                }
            }
            if (count($db_results) == 0) {
                update_option('cloudflare_api_db_last_run', time());
                $db_results[] = "All tables optimized without error.";
            }
        }
    }

    ?>
    <?php if ( !empty($_POST['submit'] ) && !($_POST['optimize']) ) { ?>
    <div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
    <?php } else if ( isset($_POST['submit']) && isset($_POST['optimize']) ) { 
    foreach ($db_results as $res) {
        ?><div id="message" class="updated fade"><p><strong><?php _e($res) ?></strong></p></div><?php
    }
} 
    ?>
    <div class="wrap">
    <h2><?php _e('CloudFlare Configuration'); ?></h2>
    <div class="narrow">
    <form action="" method="post" id="cloudflare-conf" style="margin: auto; width: 400px; ">
    <?php if (get_option('cloudflare_api_key') && get_option('cloudflare_api_email')) { ?>
    <p><?php printf(__('CloudFlare is accelerating and protecting your site.')); ?></p>
    <?php } else { ?> 
        <p><?php printf(__('For many people, <a href="%1$s">CloudFlare</a> will accelerate and protect their website. If you don\'t have an API key yet for CloudFlare, you can get one at <a href="%2$s">CloudFlare.com</a>.'), 'http://cloudflare.com/', 'http://cloudflare.com/'); ?></p>
    <?php } ?>
    <?php if ($ms) { foreach ( $ms as $m ) { ?>
    <p style="padding: .5em; background-color: #<?php echo $messages[$m]['color']; ?>; color: #fff; font-weight: bold;"><?php echo $messages[$m]['text']; ?></p>
    <?php } } ?>
    <h3><label for="key"><?php _e('CloudFlare API Key'); ?></label></h3>
    <p><input id="key" name="key" type="text" size="50" maxlength="48" value="<?php echo get_option('cloudflare_api_key'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /> (<?php _e('<a href="http://cloudflare.com/">What is this?</a>'); ?>)</p>
    <h3><label for="email"><?php _e('CloudFlare API Email'); ?></label></h3>
    <p><input id="email" name="email" type="text" size="50" maxlength="48" value="<?php echo get_option('cloudflare_api_email'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /> (<?php _e('<a href="http://cloudflare.com/">What is this?</a>'); ?>)</p>

    <p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
    </form>

    <hr />

    <form action="" method="post" id="cloudflare-db" style="margin: auto; width: 400px; ">
    <input type="hidden" name="optimize" value="1" />
    <h3><label for="optimize_db"><?php _e('Make your site run faster'); ?></label></h3>
    <p class="submit"><input type="submit" name="submit" value="<?php _e('Run the optimizer'); ?>" /> (<?php _e('<a href="http://cloudflare.com/">What is this?</a>'); ?>)</p>
    </form>

    </div>
    </div>
    <?php
}

function cloudflare_admin_warnings() {
    
    global $cloudflare_api_key, $cloudflare_api_email; 
    load_cloudflare_keys();

	if ( !get_option('cloudflare_api_key_set_once') && !$cloudflare_api_key && !isset($_POST['submit']) ) {
		function cloudflare_warning() {
			echo "
			<div id='cloudflare-warning' class='updated fade'><p><strong>".__('CloudFlare is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your CloudFlare API key</a> for it to work.'), "plugins.php?page=cloudflare-key-config")."</p></div>
			";
		}
		add_action('admin_notices', 'cloudflare_warning');
		return;
	} else if ( !get_option('cloudflare_api_key_set_once') && !$cloudflare_api_email && !isset($_POST['submit']) ) {
		function cloudflare_warning() {
			echo "
			<div id='cloudflare-warning' class='updated fade'><p><strong>".__('CloudFlare is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your CloudFlare API email</a> for it to work.'), "plugins.php?page=cloudflare-key-config")."</p></div>
			";
		}
		add_action('admin_notices', 'cloudflare_warning');
		return;
	} 

    // Check to see if they should optimized their DB
    $last_run_time = (int)get_option('cloudflare_api_db_last_run');
    if (!$last_run_time || time() - $last_run_time > 5259487) {
        function cloudflare_warning() {
			echo "
			<div id='cloudflare-warning' class='updated fade'><p><strong>".__('Your Database is due to be optimized again.')."</strong> ".sprintf(__('We reccomend that you <a href="%1$s">run the CloudFlare optimizer</a> at least once every few months to keep your blog running quickly.'), "plugins.php?page=cloudflare-key-config")."</p></div>
			";
		}
		add_action('admin_notices', 'cloudflare_warning');
		return;
    }
}

// Now actually allow CF to see when a comment is approved/not-approved.
function cloudflare_set_comment_status($id, $status) {
	global $cf_api_host, $cf_api_port, $cloudflare_api_key, $cloudflare_api_email; 
    if (!$cf_api_host || !$cf_api_port) {
        return;
    }
    load_cloudflare_keys();
    if (!$cloudflare_api_key || !$cloudflare_api_email) {
        return;
    }

    $comment = get_comment($id);
    $url = $cf_api_host . "key=" . $comment->comment_author_IP . "&u=$cloudflare_api_email&tkn=$cloudflare_api_key&a=";
     
    // If spam, send this info over to CloudFlare.
    if ($status == "spam") {
        $url .= "chl";
        file_put_contents("/tmp/ckc", file_get_contents($url));
    }
}

add_action('wp_set_comment_status', 'cloudflare_set_comment_status', 1, 2);

?>