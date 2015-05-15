<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Hide_WP_Admin {

	private $hide_wp = null;

	protected function __construct() {
		$this->hide_wp = Hide_WP::instance();
		add_action('admin_menu', array($this,'admin_menu'));
		add_filter('mod_rewrite_rules', array($this,'mod_rewrite_rules'));
	}
	
	public static function instance() {
		static $instance;
		if (!$instance)	$instance = new self();
		return $instance;
	}	

	public function admin_menu() {
		add_menu_page(__('Hide WP', 'hide-wp'), __('Hide WP', 'hide-wp'), 'manage_options', 'hide_wp', array($this,'config_handler'));
	}
	
	function generate_plugin_url($slug, $plugins) {
		$stop = false;
		$l = 0;
		$url = 'ext/';
		while (!$stop) {
			$url = 'ext/';
			$l++;
			$url .= substr($slug,0,1).$l;
			$stop = true;
			foreach ($plugins as $u) {
				if ($u == $url) {
					$stop = false;
				}
			}
		}
		return $url;
	}
	
	function mod_rewrite_rules($rules) {
		$subreq = rand(1,1000000);
		$settings = $this->hide_wp->get_options();
		if ($this->hide_wp->disabled()) {
			return $rules;
		}
		//
		$permalink_structure = get_option('permalink_structure');
		if (!$permalink_structure || $permalink_structure == '') {
			return $rules;
		}
		$this->hide_wp->disable();
		$slashed_home = trailingslashit( get_option( 'home' ) );
		$base = parse_url( $slashed_home, PHP_URL_PATH );
		$my_content = <<<EOD
\n# BEGIN Hide WP
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase {$base}
EOD;

		if (isset($settings['ajax_url']) && trim($settings['ajax_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['ajax_url']),'/').'(.*) /'.substr(admin_url( 'admin-ajax.php'),strlen(trailingslashit(site_url()))).'$1 [L,QSA]';
			if (isset($settings['block_ajax_url']) && $settings['block_ajax_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".substr(admin_url( 'admin-ajax.php'),strlen(trailingslashit(site_url())))." /404 [L]";
			}
		}

		if (isset($settings['login_url']) && trim($settings['login_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['login_url']),'/').' /'.substr(wp_login_url(),strlen(trailingslashit(site_url()))).'$1 [L,QSA]';
			if (isset($settings['register_url']) && trim($settings['register_url'].' ') != '') {
				$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['register_url']),'/').' /'.substr(wp_login_url(),strlen(trailingslashit(site_url()))).'$1?action=register [L,QSA]';
			}
			if (isset($settings['lostpassword_url']) && trim($settings['lostpassword_url'].' ') != '') {
				$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['lostpassword_url']),'/').' /'.substr(wp_login_url(),strlen(trailingslashit(site_url()))).'$1?action=lostpassword [L,QSA]';
			}
			if (isset($settings['block_login_url']) && $settings['block_login_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".substr(wp_login_url(),strlen(trailingslashit(site_url())))." /404 [L]";
			}
		}
		
		if (isset($settings['includes_url']) && trim($settings['includes_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trailingslashit(trim(trim($settings['includes_url']),'/')).'(.*) /'.trailingslashit(substr(includes_url(),strlen(trailingslashit(site_url())))).'$1 [L,QSA]';
			if (isset($settings['block_includes_url']) && $settings['block_includes_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^wp-includes(.*) /404 [L]";
			}
		}
		
		$upload_dir = wp_upload_dir();
		$media_url = $upload_dir['baseurl'];
		if (isset($settings['media_url']) && trim($settings['media_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trailingslashit(trim(trim($settings['media_url']),'/')).'(.*) /'.trailingslashit(substr($media_url,strlen(trailingslashit(site_url())))).'$1 [L,QSA]';
			if (isset($settings['block_media_url']) && $settings['block_media_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".trailingslashit(substr($media_url,strlen(trailingslashit(site_url()))))."(.*) /404 [L]";
			}
		}

		if (isset($settings['theme_url']) && trim($settings['theme_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trailingslashit(trim(trim($settings['theme_url']),'/')).'(.*) /'.trailingslashit(substr(get_template_directory_uri(),strlen(trailingslashit(site_url())))).'$1 [L,QSA]';			
			if (isset($settings['block_theme_url']) && $settings['block_theme_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^wp-content/themes(.*) /404 [L]";
				$my_content .= "\n\n  RewriteRule ^".md5(site_url().$settings['theme_url']).'/(.*) /wp-content/themes/$1 [L,QSA]';
			}
		}
		if (isset($settings['style_dir_url']) && trim($settings['style_dir_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['style_dir_url']),'/').'/(.*) /'.trailingslashit(substr(get_stylesheet_directory_uri(),strlen(trailingslashit(site_url())))).'$1 [L,QSA]';
		}
		if (isset($settings['style_url']) && trim($settings['style_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim($settings['style_url']).' /'.substr(get_stylesheet_uri(),strlen(trailingslashit(site_url()))).' [L,QSA]';
		}

		if (isset($settings['admin_url']) && trim($settings['admin_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trailingslashit(trim(trim($settings['admin_url']),'/')).'(.*) /'.substr(admin_url(),strlen(trailingslashit(site_url()))).'$1 [L,QSA]';
			if (isset($settings['block_admin_url']) && $settings['block_admin_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".substr(admin_url(),strlen(trailingslashit(site_url())))."(.*) /404 [L]";
			}
		}
		
		if (isset($settings['comments_url']) && trim($settings['comments_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".untrailingslashit(trim(trim($settings['comments_url']),'/')).'(.*) /'.substr(site_url('/wp-comments-post.php'),strlen(trailingslashit(site_url()))).'$1 [L,QSA]';
			if (isset($settings['block_comments_url']) && $settings['block_comments_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".substr(site_url('/wp-comments-post.php'),strlen(trailingslashit(site_url())))."(.*) /404 [L]";
			}
		}

		if (isset($settings['block_readme']) && $settings['block_readme'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^(readme.html|license.txt|wp-config-sample.php)(.*) /404 [L]";
		}

		if (isset($settings['block_signup']) && $settings['block_signup'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-signup.php(.*) /404 [L]";
		}

		if (isset($settings['block_activate']) && $settings['block_activate'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-activate.php(.*) /404 [L]";
		}

		if (isset($settings['block_opml']) && $settings['block_opml'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-links-opml.php(.*) /404 [L]";
		}

		if (isset($settings['block_blog_header']) && $settings['block_blog_header'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-blog-header.php(.*) /404 [L]";
		}

		if (isset($settings['cron_url']) && trim($settings['cron_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['cron_url'])).'(.*) /wp-cron.php$1 [L,QSA]';
		}
		if (isset($settings['block_cron']) && $settings['block_cron'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-cron.php(.*) /404 [L]";
		}

		if (isset($settings['xmlrpc_url']) && trim($settings['xmlrpc_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['xmlrpc_url'])).'(.*) /xmlrpc.php$1 [L,QSA]';
		}
		if (isset($settings['block_xmlrpc']) && $settings['block_xmlrpc'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^xmlrpc.php(.*) /404 [L]";
		}

		if (isset($settings['trackback_url']) && trim($settings['trackback_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['trackback_url'])).'(.*) /wp-trackback.php$1 [L,QSA]';
		}
		if (isset($settings['block_trackback']) && $settings['block_trackback'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-trackback.php(.*) /404 [L]";
		}

		if (isset($settings['mail_url']) && trim($settings['mail_url'].' ') != '') {
			$my_content .= "\n\n  RewriteRule ^".trim(trim($settings['mail_url'])).'(.*) /wp-mail.php$1 [L,QSA]';
		}
		if (isset($settings['block_mail']) && $settings['block_mail'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^wp-mail.php(.*) /404 [L]";
		}

		if (isset($settings['block_config']) && $settings['block_config'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^(wp-config.php|wp-settings.php|wp-load.php)(.*) /404 [L]";
		}

		$plugins = get_plugins();
		$save_config = false;
		if (!isset($settings['plugins'])) {
			$settings['plugins'] = array();
			$save_config = true;
		}
		foreach ($plugins as $name=>$plugin) {
			$slug = $this->hide_wp->plugin_slug($name);
			if (isset($settings['plugins'][$slug])) {
				$plugin_url = $settings['plugins'][$slug];
			}
			else {
				$plugin_url = md5(site_url().$slug);				
				$plugin_url = $this->generate_plugin_url($slug, $settings['plugins']);
				$settings['plugins'][$slug] = $plugin_url;
				$save_config = true;
			}			
			if (strpos($slug,'.php') === false) {
				$slug_path = $slug.'/';
			}
			$my_content .= "\n\n  RewriteRule ^".trailingslashit($plugin_url).'(.*) /'.trailingslashit(substr(plugins_url(),strlen(trailingslashit(site_url())))).$slug_path.'$1 [L,QSA]';
			if (isset($settings['plugins_block'][$slug]) && $settings['plugins_block'][$slug] == '1' && isset($settings['block_plugins_url']) && $settings['block_plugins_url'] == '0') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".trailingslashit(substr(plugins_url(),strlen(trailingslashit(site_url())))).$slug_path."(.*) /404 [L]";
			}			
			if ((!isset($settings['plugins_block'][$slug]) || $settings['plugins_block'][$slug] == '0') && isset($settings['block_plugins_url']) && $settings['block_plugins_url'] == '1') {
				$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
				$my_content .= "\n  RewriteRule ^".trailingslashit(substr(plugins_url(),strlen(trailingslashit(site_url())))).$slug_path."(.*) /".trailingslashit(substr(plugins_url(),strlen(trailingslashit(site_url())))).$slug_path.'$1 [L,QSA]';
			}			
		}
		if ($save_config) {
			$this->hide_wp->save_options($settings);
		}
		if (isset($settings['block_plugins_url']) && $settings['block_plugins_url'] == '1') {
			$my_content .= "\n\n  RewriteCond %{ENV:REDIRECT_STATUS} ^$";
			$my_content .= "\n  RewriteRule ^".substr(plugins_url(),strlen(trailingslashit(site_url())))."/(.*) /404 [L]";
		}

		$my_content .= <<<EOD
		

</IfModule>
# END Hide WP
\n
EOD;
		//
		$this->hide_wp->enable();
    return $my_content . $rules;
		
	}
	
	public function save_config() {
		$settings = $this->hide_wp->get_options();
		$tab = 'header';
		if (isset($_GET['tab'])) {
  		$tab = $_GET['tab'];
  	} 
	  if ($tab == 'home') {
  		$settings['block_readme'] = '0';
  		if (isset($_POST['block_readme'])) {
  			$settings['block_readme'] = $_POST['block_readme'];
  		}
  		$settings['block_config'] = '0';
  		if (isset($_POST['block_config'])) {
  			$settings['block_config'] = $_POST['block_config'];
  		}
  		$settings['block_activate'] = '0';
  		if (isset($_POST['block_activate'])) {
  			$settings['block_activate'] = $_POST['block_activate'];
  		}
  		$settings['block_opml'] = '0';
  		if (isset($_POST['block_opml'])) {
  			$settings['block_opml'] = $_POST['block_opml'];
  		}
  		$settings['block_blog_header'] = '0';
  		if (isset($_POST['block_blog_header'])) {
  			$settings['block_blog_header'] = $_POST['block_blog_header'];
  		}
  		$settings['cron_url'] = $_POST['cron_url'];
  		$settings['block_cron'] = '0';
  		if (isset($_POST['block_cron'])) {
  			$settings['block_cron'] = $_POST['block_cron'];
  		}
  		$settings['xmlrpc_url'] = $_POST['xmlrpc_url'];
  		$settings['block_xmlrpc'] = '0';
  		if (isset($_POST['block_xmlrpc'])) {
  			$settings['block_xmlrpc'] = $_POST['block_xmlrpc'];
  		}
  		$settings['mail_url'] = $_POST['mail_url'];
  		$settings['block_mail'] = '0';
  		if (isset($_POST['block_mail'])) {
  			$settings['block_mail'] = $_POST['block_mail'];
  		}
  		$settings['trackback_url'] = $_POST['trackback_url'];
  		$settings['block_trackback'] = '0';
  		if (isset($_POST['block_trackback'])) {
  			$settings['block_trackback'] = $_POST['block_trackback'];
  		}
  		$settings['block_config'] = '0';
  		if (isset($_POST['block_config'])) {
  			$settings['block_config'] = $_POST['block_config'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'other') {
  		$settings['post_permalink_relative'] = '0';
  		if (isset($_POST['post_permalink_relative'])) {
  			$settings['post_permalink_relative'] = $_POST['post_permalink_relative'];
  		}
  		$settings['page_permalink_relative'] = '0';
  		if (isset($_POST['page_permalink_relative'])) {
  			$settings['page_permalink_relative'] = $_POST['page_permalink_relative'];
  		}
  		$settings['term_permalink_relative'] = '0';
  		if (isset($_POST['term_permalink_relative'])) {
  			$settings['term_permalink_relative'] = $_POST['term_permalink_relative'];
  		}
  		$settings['w3tc_can_print_comment'] = '0';
  		if (isset($_POST['w3tc_can_print_comment'])) {
  			$settings['w3tc_can_print_comment'] = $_POST['w3tc_can_print_comment'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'header') {
 			$settings['header_generator'] = $_POST['header_generator'];
  		$settings['header_hide_generator'] = '0';
  		if (isset($_POST['header_hide_generator'])) {
  			$settings['header_hide_generator'] = $_POST['header_hide_generator'];
  		}
  		$settings['header_feed_links'] = '0';
  		if (isset($_POST['header_feed_links'])) {
  			$settings['header_feed_links'] = $_POST['header_feed_links'];
  		}
  		$settings['header_feed_links_extra'] = '0';
  		if (isset($_POST['header_feed_links_extra'])) {
  			$settings['header_feed_links_extra'] = $_POST['header_feed_links_extra'];
  		}
  		$settings['header_rsd_link'] = '0';
  		if (isset($_POST['header_rsd_link'])) {
  			$settings['header_rsd_link'] = $_POST['header_rsd_link'];
  		}
  		$settings['header_pingback'] = '0';
  		if (isset($_POST['header_pingback'])) {
  			$settings['header_pingback'] = $_POST['header_pingback'];
  		}
  		$settings['header_x_pingback'] = '0';
  		if (isset($_POST['header_x_pingback'])) {
  			$settings['header_x_pingback'] = $_POST['header_x_pingback'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'plugins') {
  		$settings['plugins'] = $_POST['plugins'];
  		$settings['plugins_block'] = $_POST['plugins_block'];
  		$settings['block_plugins_url'] = '0';
  		if (isset($_POST['block_plugins_url'])) {
  			$settings['block_plugins_url'] = $_POST['block_plugins_url'];
  		}
  		$settings['plugins_relative'] = '0';
  		if (isset($_POST['plugins_relative'])) {
  			$settings['plugins_relative'] = $_POST['plugins_relative'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'comments') {
  		$settings['comments_url'] = $_POST['comments_url'];
  		$settings['block_comments_url'] = '0';
  		if (isset($_POST['block_comments_url'])) {
  			$settings['block_comments_url'] = $_POST['block_comments_url'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'admin') {
  		$settings['admin_url'] = $_POST['admin_url'];
  		$settings['block_admin_url'] = '0';
  		if (isset($_POST['block_admin_url'])) {
  			$settings['block_admin_url'] = $_POST['block_admin_url'];
  		}
  		$settings['ajax_url'] = $_POST['ajax_url'];
  		$settings['block_ajax_url'] = '0';
  		if (isset($_POST['block_ajax_url'])) {
  			$settings['block_ajax_url'] = $_POST['block_ajax_url'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'login') {
  		$settings['login_url'] = $_POST['login_url'];
  		$settings['block_login_url'] = '0';
  		if (isset($_POST['block_login_url'])) {
  			$settings['block_login_url'] = $_POST['block_login_url'];
  		}
  		$settings['register_url'] = $_POST['register_url'];
  		$settings['lostpassword_url'] = $_POST['lostpassword_url'];
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'includes') {
  		$settings['includes_url'] = $_POST['includes_url'];
  		$settings['includes_relative'] = '0';
  		$settings['block_includes_url'] = '0';
  		if (isset($_POST['block_includes_url'])) {
  			$settings['block_includes_url'] = $_POST['block_includes_url'];
  		}
  		if (isset($_POST['includes_relative'])) {
  			$settings['includes_relative'] = $_POST['includes_relative'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'media') {
  		$settings['media_url'] = $_POST['media_url'];
  		$settings['media_relative'] = '0';
  		$settings['block_media_url'] = '0';
  		if (isset($_POST['block_media_url'])) {
  			$settings['block_media_url'] = $_POST['block_media_url'];
  		}
  		if (isset($_POST['media_relative'])) {
  			$settings['media_relative'] = $_POST['media_relative'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	  if ($tab == 'theme') {
  		$settings['theme_url'] = $_POST['theme_url'];
  		$settings['style_url'] = $_POST['style_url'];
  		$settings['style_dir_url'] = $_POST['style_dir_url'];
  		$settings['block_theme_url'] = '0';
  		if (isset($_POST['block_theme_url'])) {
  			$settings['block_theme_url'] = $_POST['block_theme_url'];
  		}
  		$settings['theme_relative'] = '0';
  		if (isset($_POST['theme_relative'])) {
  			$settings['theme_relative'] = $_POST['theme_relative'];
  		}
  		$this->hide_wp->save_options($settings);
  		flush_rewrite_rules();
	  }
	}
	
	public function config_handler() {
	  echo '<div class="wrap">';
  	echo '<div id="icon-settings" class="icon32"><br></div>';
  	echo '<h2>Hide WP</h2>';
		$permalink_structure = get_option('permalink_structure');
		//		
		if (!$permalink_structure || $permalink_structure == '') {
   		?>
			<div class="error">
        <p><?php _e( 'Hide WP requires Pretty Permalinks to work!', 'hide-wp' ); ?></p>
    	</div>   	
    	<?php
		}
		if (defined('W3TC')) {
			?>
			<div class="updated">
        <p><?php _e( 'There is W3 Total Cache installed. Make sure that option "Set W3 Total Cache header" in Browser Cache is disabled!', 'hide-wp' ); ?></p>
    	</div>   	
    	<?php
		}
  	//
		if ( $_POST["hide-wp-config-submit"] == 'Y' ) {
			//echo 1;
  		check_admin_referer("hide-wp-config");
   		$this->save_config();
   		?>
			<div class="updated">
        <p><?php _e( 'Settings updated!', 'hide-wp' ); ?></p>
    	</div>   	
    	<?php
  	}	
  	//
  	if (isset($_GET['tab'])) $this->config_handler_tabs($_GET['tab']); 
  	else $this->config_handler_tabs('header');	
  	//
		echo '</div>'; // wrap
	}
	
	function config_handler_tabs($current) {
		$settings = $this->hide_wp->get_options();
    $tabs = array( 'header' => 'Header'
    						 , 'login' => 'Login'
    						 , 'admin' => 'Admin'
    						 , 'includes' => 'Includes'
    						 , 'plugins' => 'Plugins'
    						 , 'comments' => 'Comments'
    						 , 'theme' => 'Theme'
    						 , 'media' => 'Media'
    						 , 'home' => 'Home dir'
    						 , 'other' => 'Other'
    						 , 'donate' => 'Donate' );
    $tabs_hints = array( 'header' => 'Header'
    						 , 'plugins' => 'Plugins'
    						 , 'comments' => 'Comments'
    						 , 'includes' => 'Includes'
    						 , 'theme' => 'Theme'
    						 , 'login' => 'Login'
    						 , 'admin' => 'Admin'
    						 , 'media' => 'Media'
    						 , 'home' => 'Home dir'
    						 , 'other' => 'Other'
    						 , 'donate' => 'Donate' );
    ?>
    <div id="poststuff">
    	<div id="post-body" class="metabox-holder">
    		<div id="post-body-content" style="">
		<?php
    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=hide_wp&tab=$tab' title='".$tab_hints[$tab]."'>$name</a>";
    }
    echo '</h2>';		
    $form_method = 'POST';
    if (1==2 && $tab == 'log') {
    	$form_method = 'GET';
    }
    if ($current == 'home') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th></th>
            <td>
               <input id="block_readme" name="block_readme" type="checkbox" value="1" <?php checked($settings['block_readme'],'1',true); ?>/> <label for="block_readme">Block readme.html, license.txt and wp-config-sample.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_config" name="block_config" type="checkbox" value="1" <?php checked($settings['block_config'],'1',true); ?>/> <label for="block_config">Block wp-config.php, wp-settings.php and wp-load.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_activate" name="block_activate" type="checkbox" value="1" <?php checked($settings['block_activate'],'1',true); ?>/> <label for="block_activate">Block wp-activate.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_opml" name="block_opml" type="checkbox" value="1" <?php checked($settings['block_opml'],'1',true); ?>/> <label for="block_opml">Block wp-links-opml.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_blog_header" name="block_blog_header" type="checkbox" value="1" <?php checked($settings['block_blog_header'],'1',true); ?>/> <label for="block_blog_header">Block wp-blog-header.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="cron_url">New wp-cron.php URL:</label></th>
            <td>
               <input style="width:340px;" id="cron_url" name="cron_url" type="text" value="<?php echo $settings['cron_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-cron.php URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_cron" name="block_cron" type="checkbox" value="1" <?php checked($settings['block_cron'],'1',true); ?>/> <label for="block_cron">Block oryginal wp-cron.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="xmlrpc_url">New xmlrpc.php URL:</label></th>
            <td>
               <input style="width:340px;" id="xmlrpc_url" name="xmlrpc_url" type="text" value="<?php echo $settings['xmlrpc_url']; ?>" /><br/>
               <span class="description">Blank - do not change xmlrpc.php URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_xmlrpc" name="block_xmlrpc" type="checkbox" value="1" <?php checked($settings['block_xmlrpc'],'1',true); ?>/> <label for="block_xmlrpc">Block oryginal xmlrpc.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="trackback_url">New wp-trackback.php URL:</label></th>
            <td>
               <input style="width:340px;" id="trackback_url" name="trackback_url" type="text" value="<?php echo $settings['trackback_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-trackback.php URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_trackback" name="block_trackback" type="checkbox" value="1" <?php checked($settings['block_trackback'],'1',true); ?>/> <label for="block_trackback">Block oryginal wp-trackback.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="mail_url">New mail.php URL:</label></th>
            <td>
               <input style="width:340px;" id="mail_url" name="mail_url" type="text" value="<?php echo $settings['mail_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-mail.php URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_mail" name="block_mail" type="checkbox" value="1" <?php checked($settings['block_mail'],'1',true); ?>/> <label for="block_mail">Block oryginal wp-mail.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'other') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th></th>
            <td>
               <input id="post_permalink_relative" name="post_permalink_relative" type="checkbox" value="1" <?php checked($settings['post_permalink_relative'],'1',true); ?>/> <label for="post_permalink_relative">Post permalink relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="page_permalink_relative" name="page_permalink_relative" type="checkbox" value="1" <?php checked($settings['page_permalink_relative'],'1',true); ?>/> <label for="page_permalink_relative">Page permalink relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="term_permalink_relative" name="term_permalink_relative" type="checkbox" value="1" <?php checked($settings['term_permalink_relative'],'1',true); ?>/> <label for="term_permalink_relative">Term permalink relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="w3tc_can_print_comment" name="w3tc_can_print_comment" type="checkbox" value="1" <?php checked($settings['w3tc_can_print_comment'],'1',true); ?>/> <label for="w3tc_can_print_comment">Disable W3 Total Cache (if installed) comment in page footer.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'header') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        <tr>
        	<th><label for="header_generator">Change generator meta to:</label></th>
            <td>
               <input style="width:340px;" id="header_generator" name="header_generator" type="text" value="<?php echo $settings['header_generator']; ?>" /><br/>
               <span class="description">Blank - do not change generator meta.</span>               
            </td>
        </tr>
        	<th></th>
            <td>
               <input id="header_hide_generator" name="header_hide_generator" type="checkbox" value="1" <?php checked($settings['header_hide_generator'],'1',true); ?>/> <label for="header_hide_generator">Remove generator meta</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="header_feed_links" name="header_feed_links" type="checkbox" value="1" <?php checked($settings['header_feed_links'],'1',true); ?>/> <label for="header_feed_links">Remove feed links</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="header_feed_links_extra" name="header_feed_links_extra" type="checkbox" value="1" <?php checked($settings['header_feed_links_extra'],'1',true); ?>/> <label for="header_feed_links_extra">Remove extra feed links</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="header_rsd_link" name="header_rsd_link" type="checkbox" value="1" <?php checked($settings['header_rsd_link'],'1',true); ?>/> <label for="header_rsd_link">Remove RSD link</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="header_pingback" name="header_pingback" type="checkbox" value="1" <?php checked($settings['header_pingback'],'1',true); ?>/> <label for="header_pingback">Remove pingback URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="header_x_pingback" name="header_x_pingback" type="checkbox" value="1" <?php checked($settings['header_x_pingback'],'1',true); ?>/> <label for="header_x_pingback">Remove X-Pingback header</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'media') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="media_url">New media directory:</label></th>
            <td>
               <input style="width:340px;" id="media_url" name="media_url" type="text" value="<?php echo $settings['media_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-content/uploads directory.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_media_url" name="block_media_url" type="checkbox" value="1" <?php checked($settings['block_media_url'],'1',true); ?>/> <label for="block_media_url">Block wp-content/uploads URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="media_relative" name="media_relative" type="checkbox" value="1" <?php checked($settings['media_relative'],'1',true); ?>/> <label for="media_relative">Change media URLs to relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'plugins') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th></th>
            <td>
               <input id="block_plugins_url" name="block_plugins_url" type="checkbox" value="1" <?php checked($settings['block_plugins_url'],'1',true); ?>/> <label for="block_plugins_url">Block wp-content/plugins URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="plugins_relative" name="plugins_relative" type="checkbox" value="1" <?php checked($settings['plugins_relative'],'1',true); ?>/> <label for="plugins_relative">Change plugins URLs to relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
 	      	<th><h2 style="float:left; margin-top:2px; margin-bottom:2px;">Plugins</h2></th>
 	        <td></td>
       	</tr>
				<?php
				$plugins = get_plugins();
				foreach ($plugins as $name=>$plugin) {
					$slug = $this->hide_wp->plugin_slug($name);
					if (isset($settings['plugins'][$slug])) {
						$plugin_url = $settings['plugins'][$slug];
					}
					else {
						$plugin_url = md5(site_url().$slug);				
						$plugin_url = $this->generate_plugin_url($slug, $settings['plugins']);
						$settings['plugins'][$slug] = $plugin_url;
						$settings['plugins_block'][$slug] = '1';
						$save_config = true;
					}			
					?>
	        <tr>
  	      	<th><label for="plugin_<?php echo $slug; ?>"><?php echo $plugin['Name']; ?></label></th>
    	        <td>
      	         <input style="width:340px;" id="plugin_<?php echo $slug; ?>" name="plugins[<?php echo $slug; ?>]" type="text" value="<?php echo $settings['plugins'][$slug]; ?>" /><br/>
        	       <span class="description">Blank - do not change plugin URL. Oryginal: <?php echo trim(plugins_url(),'/').$slug; ?></span>               
          	  </td>
        	</tr>
	        <tr>
  	      	<th></th>
    	        <td>
      	         <input id="block_<?php echo $slug; ?>" name="plugins_block[<?php echo $slug; ?>]" type="checkbox" value="1" <?php checked($settings['plugins_block'][$slug],'1',true); ?>/> <label for="block_<?php echo $slug; ?>">Block oryginal plugin URL</label><br/>
        	       <span class="description"></span>
          	  </td>
        	</tr>
					<?php
				}
				?>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'admin') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="admin_url">New wp-admin URL:</label></th>
            <td>
               <input style="width:340px;" id="admin_url" name="admin_url" type="text" value="<?php echo $settings['admin_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-admin URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_admin_url" name="block_admin_url" type="checkbox" value="1" <?php checked($settings['block_admin_url'],'1',true); ?>/> <label for="block_admin_url">Block oryginal wp-admin URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="ajax_url">New wp-ajax URL:</label></th>
            <td>
               <input style="width:340px;" id="ajax_url" name="ajax_url" type="text" value="<?php echo $settings['ajax_url']; ?>" /><br/>
               <span class="description">Blank - do not change oryginal wp-ajax URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_ajax_url" name="block_ajax_url" type="checkbox" value="1" <?php checked($settings['block_ajax_url'],'1',true); ?>/> <label for="block_ajax_url">Block wp-ajax URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'comments') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="comments_url">New wp-comments-post.php URL:</label></th>
            <td>
               <input style="width:340px;" id="comments_url" name="comments_url" type="text" value="<?php echo $settings['comments_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-comments-post.php URL.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_comments_url" name="block_comments_url" type="checkbox" value="1" <?php checked($settings['block_comments_url'],'1',true); ?>/> <label for="block_comments_url">Block oryginal wp-comments-post.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'login') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="login_url">New wp-login.php URL:</label></th>
            <td>
               <input style="width:340px;" id="login_url" name="login_url" type="text" value="<?php echo $settings['login_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-login.php URL.</span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_login_url" name="block_login_url" type="checkbox" value="1" <?php checked($settings['block_login_url'],'1',true); ?>/> <label for="block_login_url">Block oryginal wp-login.php URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th><label for="register_url">New register URL:</label></th>
            <td>
               <input style="width:340px;" id="register_url" name="register_url" type="text" value="<?php echo $settings['register_url']; ?>" /><br/>
               <span class="description">Blank - do not change register URL.</span>               
            </td>
        </tr>
        <tr>
        	<th><label for="lostpassword_url">New lost password URL:</label></th>
            <td>
               <input style="width:340px;" id="lostpassword_url" name="lostpassword_url" type="text" value="<?php echo $settings['lostpassword_url']; ?>" /><br/>
               <span class="description">Blank - do not change lost password URL.</span>               
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'includes') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="includes_url">New wp-includes directory:</label></th>
            <td>
               <input style="width:340px;" id="includes_url" name="includes_url" type="text" value="<?php echo $settings['includes_url']; ?>" /><br/>
               <span class="description">Blank - do not change wp-includes directory.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_includes_url" name="block_includes_url" type="checkbox" value="1" <?php checked($settings['block_includes_url'],'1',true); ?>/> <label for="block_includes_url">Block oryginal wp-includes URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="includes_relative" name="includes_relative" type="checkbox" value="1" <?php checked($settings['includes_relative'],'1',true); ?>/> <label for="includes_relative">Change includes URLs to relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'theme') {
    	?>
			<form id="hide_wp_settings" method="<?php echo $form_method; ?>" action="">
				<table class="form-table">
        <tr>
        	<th><label for="theme_url">New theme directory:</label></th>
            <td>
               <input style="width:340px;" id="theme_url" name="theme_url" type="text" value="<?php echo $settings['theme_url']; ?>" /><br/>
               <span class="description">Blank - do not change theme directory.</span>               
            </td>
        </tr>
        <tr>
        	<th><label for="style_dir_url">New style directory:</label></th>
            <td>
               <input style="width:340px;" id="style_dir_url" name="style_dir_url" type="text" value="<?php echo $settings['style_dir_url']; ?>" /><br/>
               <span class="description">Blank - do not change style directory.</span>               
            </td>
        </tr>
        <tr>
        	<th><label for="style_url">New style file:</label></th>
            <td>
               <input style="width:340px;" id="style_url" name="style_url" type="text" value="<?php echo $settings['style_url']; ?>" /><br/>
               <span class="description">Blank - do not change style directory.</span>               
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="block_theme_url" name="block_theme_url" type="checkbox" value="1" <?php checked($settings['block_theme_url'],'1',true); ?>/> <label for="block_theme_url">Block wp-content/themes URL</label><br/>
               <span class="description"></span>
            </td>
        </tr>
        <tr>
        	<th></th>
            <td>
               <input id="theme_relative" name="theme_relative" type="checkbox" value="1" <?php checked($settings['theme_relative'],'1',true); ?>/> <label for="theme_relative">Change template URLs to relative.</label><br/>
               <span class="description"></span>
            </td>
        </tr>
    		</table>
    		<?php wp_nonce_field("hide-wp-config"); ?>
	  		<input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
  	    <input type="hidden" name="hide-wp-config-submit" value="Y" />
			</form>
    	<?php
    }
    if ($current == 'donate') {
    	?>
    	Hide WP has required a great deal of time and effort to develop. If it's been useful to you then you can support this development by making a small donation. This will act as an incentive for me to carry on developing it, providing countless hours of support, and including any enhancements that are suggested.<br/><br/>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="ZS4BW6RKLC7AU">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">
</form>
    	<?php
		}
		?>
				</div><!-- post-body-content -->		
			</div><!-- post-body -->		
		</div><!-- poststuff -->
		<div style="clear:both;"></div>
		
		<?php    
	}
	
}

$hide_wp_admin = Hide_WP_Admin::instance();
