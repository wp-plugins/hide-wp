<?php
/*
Plugin Name: Hide WP
Plugin URI: 
Description: Hide Wordpress instalation and protect Wordpress files
Version: 1.0.1
Author: Grzegorz Rola
Author URI: 
Text Domain: hide-wp
*/ 

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

include_once('hide-wp-admin.php');

class Hide_WP {
	
	private $options = null;
	private $disable_filters = false;	
	private $theme_url = false;
	private $theme_url_relative = false;

	protected function __construct() {
		$this->get_options();
		add_action('init', array( $this, 'init' ) );
		add_action('admin_init', array( $this, 'admin_init' ) );
		add_filter('site_url', array($this,'site_url'), 10, 4);
		add_filter('upload_dir',array( $this, 'upload_dir' ));
		add_filter('plugins_url',array( $this, 'plugins_url' ), 10, 3);
		add_filter('template_directory_uri',array( $this, 'template_directory_uri' ), 100000, 3);
		add_filter('stylesheet_uri',array( $this, 'stylesheet_uri' ), 10, 2);
		add_filter('stylesheet_directory_uri',array( $this, 'stylesheet_directory_uri' ), 10, 3);
		add_filter('includes_url',array( $this, 'includes_url' ), 10, 2);
		add_filter('wp_admin_css',array($this,'wp_admin_css'), 10, 2 );
		add_filter('admin_url',array($this,'admin_url'), 10, 3 );
		add_filter('script_loader_src',array( $this, 'script_loader_src' ), 10, 2);
		add_filter('style_loader_src',array( $this, 'style_loader_src' ), 10, 2);
		add_filter('login_url',array($this,'login_url'), 10, 2 );
		add_filter('register_url',array($this,'register_url'), 10, 2 );
		add_filter('lostpassword_url',array($this,'lostpassword_url'), 10, 2 );
		add_filter('logout_url',array($this,'logout_url'), 10, 2 );
		add_filter('theme_root_uri',array($this,'theme_root_uri'), 10, 3 );
		add_filter('logout_redirect',array($this,'logout_redirect'), 10, 3 );
		add_filter('wp_redirect',array($this,'wp_redirect'), 10, 2 );
		add_action('auth_redirect',array($this,'auth_redirect'));
		add_action('set_logged_in_cookie',array($this,'set_logged_in_cookie'), 10, 5);
		add_action('clear_auth_cookie',array($this,'clear_auth_cookie'));
		add_filter('post_link',array($this,'post_link'), 10, 3 );
		add_filter('post_type_link',array($this,'post_link'), 10, 3 );		
		add_filter('page_link',array($this,'page_link'), 10, 3 );
		add_filter('term_link',array($this,'term_link'), 10, 3 );
		add_filter('bloginfo_url',array($this,'bloginfo_url'), 10, 2 );
		add_filter('cron_request',array($this,'cron_request'), 10, 1 );		
		//
		add_filter('get_the_generator_html',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_xhtml',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_atom',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_rss2',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_rdf',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_comment',array($this,'get_the_generator'), 10, 2 );		
		add_filter('get_the_generator_export',array($this,'get_the_generator'), 10, 2 );		
		//
		add_filter('wp_headers', array($this,'remove_x_pingback'));
		//
		add_action('activated_plugin', array($this,'activated_plugin'), 10, 2 );
		add_action('deactivated_plugin', array($this,'activated_plugin'), 10, 2 );
		add_action('after_switch_theme', 'after_switch_theme');
		//
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		//
		//
		global $compress_scripts, $concatenate_scripts;
		$compress_scripts = 0;
		$concatenate_scripts = false;
	}

	public static function instance() {
		static $instance;
		if (!$instance)	$instance = new self();
		return $instance;
	}	
	
	function init() {
		if (isset($this->options['header_feed_links']) && $this->options['header_feed_links'] == '1') {
			remove_action( 'wp_head', 'feed_links', 2 );
		} 		
		if (isset($this->options['header_feed_links_extra']) && $this->options['header_feed_links_extra'] == '1') {
			remove_action( 'wp_head', 'feed_links_extra', 3 ); 
		} 		
		if (isset($this->options['header_feed_rsd_link']) && $this->options['header_feed_rsd_link'] == '1') {
			remove_action( 'wp_head', 'feed_rsd_link', 3 ); 
		} 		
		if (isset($this->options['header_hide_generator']) && $this->options['header_hide_generator'] == '1') {
			remove_action('wp_head', 'wp_generator');
		}
	}
	
	public function plugin_slug($file) {
		$slug = substr($file,0,strpos($file,'/')); 
		if ($slug == '') {
			$slug = $file;
		}
		return $slug;
	}

	
	function admin_init() {
	}
	
	function relative_url($url) {
		$parsed_url = parse_url($url);
		$url = $parsed_url['path'];
		if (isset($parsed_url['query']) && $parsed_url['query'] != '') {
			$url .= '?'.$parsed_url['query'];
		}
		return $url;
	}
	
	function get_options() {
		if ($this->options == null) {
			$this->options = get_option('hide-wp',array());
		}
		return $this->options;
	}
	
	function save_options($options) {
		update_option('hide-wp',$options);		
		$this->options = $options;
	}
	
	function disabled() {
		return $this->disable_filters;
	}

	function disable() {
		$this->disable_filters = true;
	}
	
	function enable() {
		$this->disable_filters = false;
	}

	function set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $logged_in) {
		if (isset($this->options['admin_url']) && trim($this->options['admin_url'].' ') != '') {
			if ( '' === $secure ) {
				$secure = is_ssl();
			}
			$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );
			if ( $secure ) {
				$auth_cookie_name = SECURE_AUTH_COOKIE;
				$scheme = 'secure_auth';
			} else {
				$auth_cookie_name = AUTH_COOKIE;
				$scheme = 'auth';
			}
			$manager = WP_Session_Tokens::get_instance( $user_id );
			$token = $manager->create( $expiration );
			$auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );			
			setcookie($auth_cookie_name, $auth_cookie, $expire, SITECOOKIEPATH.trim($this->options['admin_url'],'/'), COOKIE_DOMAIN, $secure, true);
		}
	}
	
	function clear_auth_cookie() {
		if ( '' === $secure ) {
			$secure = is_ssl();
		}
		$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );
		if ( $secure ) {
			$auth_cookie_name = SECURE_AUTH_COOKIE;
			$scheme = 'secure_auth';
		} else {
			$auth_cookie_name = AUTH_COOKIE;
			$scheme = 'auth';
		}
		if (isset($this->options['admin_url']) && trim($this->options['admin_url'].' ') != '') {
			setcookie($auth_cookie_name,'', time() - YEAR_IN_SECONDS, SITECOOKIEPATH.trim($this->options['admin_url'],'/'),   COOKIE_DOMAIN );
		}
	}

	function login_url($login_url, $redirect ) {
		if ($this->disable_filters) {
			return $login_url;
		}		
		if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
			$login_url = home_url($this->options['login_url']);
			if (isset($redirect) && $redirect != '') {
				$login_url .= '?redirect_to='.$redirect;
			}
		}		
    return $login_url;
	}
	
	function register_url($register_url) {
		if ($this->disable_filters) {
			return $register_url;
		}		
		if (isset($this->options['register_url']) && trim($this->options['register_url'].' ') != '') {
			$register_url = home_url($this->options['register_url']);
		}
		else if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
			$register_url = home_url($this->options['login_url']);
			$args = array('action' => 'register');
			$register_url = add_query_arg($args, $register_url);
		}		
    return $register_url;
	}

	function lostpassword_url($lostpassword_url) {
		if ($this->disable_filters) {
			return $lostpassword_url;
		}		
		if (isset($this->options['lostpassword_url']) && trim($this->options['lostpassword_url'].' ') != '') {
			$lostpassword_url = home_url($this->options['lostpassword_url']);
		}
		else if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
			$lostpassword_url = home_url($this->options['login_url']);
			$args = array('action' => 'lostpassword');
			$lostpassword_url = add_query_arg($args, $lostpassword_url);
		}		
    return $lostpassword_url;
	}

	function logout_url($logout_url, $redirect ) {
		if ($this->disable_filters) {
			return $logout_url;
		}		
		if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
			$args = array( 'action' => 'logout' );
			if (!empty($redirect) && $redirect != '') {
				$args['redirect_to'] = urlencode( $redirect );
			}    
			$logout_url = add_query_arg($args, site_url($this->options['login_url'], 'login'));
			$logout_url = wp_nonce_url( $logout_url, 'log-out' );
		}		
    return $logout_url;
	}

	function wp_redirect($location, $status) {
		if ($this->disable_filters) {
			return $location;
		}	
		if ($location == 'wp-login.php?checkemail=registered') {	
			if (isset($this->options['register_url']) && trim($this->options['register_url'].' ') != '') {			
				$location = home_url($this->options['registere_url'].'?checkemail=registered' );
			}
			else if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$location = home_url($this->options['login_url'].'?checkemail=registered' );
			}		
		}		
		if ($location == 'wp-login.php?checkemail=confirm') {	
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$location = home_url($this->options['login_url'].'?checkemail=confirm' );
			}		
		}		
		if ($location == 'wp-login.php?loggedout=true') {	
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$location = home_url($this->options['login_url'].'?loggedout=true' );
			}		
		}		
    return $location;		
	}

	function logout_redirect($redirect_to, $requested_redirect_to, $user) {
		if ($this->disable_filters) {
			return $redirect_to;
		}		
		if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
			$redirect_to = home_url($this->options['login_url']).'?loggedout=true';
		}		
    return $redirect_to;		
	}
	
	function auth_redirect($user_id) {		
		if (!$user_id && isset($this->options['block_admin_url']) && $this->options['block_admin_url'] == '1') {
			header("HTTP/1.0 404 Not Found");
			exit();
		}
	}
	
	function upload_dir($upload_dir) {
		if ($this->disable_filters) {
			return $upload_dir;
		}
		if (isset($this->options['media_url']) && trim($this->options['media_url'].' ') != '') {
			$upload_dir['baseurl'] = str_replace(WP_CONTENT_URL.'/uploads'
					 						  									,trailingslashit(site_url()).trim(trim($this->options['media_url']),'/')
																					,$upload_dir['baseurl']);
		}
		if ((isset($this->options['media_relative']) && $this->options['media_relative'] =='1')) {
			$upload_dir['baseurl'] = $this->relative_url($upload_dir['baseurl']);
		}
		return $upload_dir;
	}
	
	function site_url($url, $path, $scheme, $blog_id) {
		if ($this->disable_filters) {
			return $url;
		}		
		if ($path == 'wp-login.php') {
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$url = str_replace("wp-login.php",trim(trim($this->options['login_url']),'/'),$url);
			}
		}
		if ($path == 'wp-login.php?action=register') {
			if (isset($this->options['register_url']) && trim($this->options['register_url'].' ') != '') {
				$url = home_url($this->options['register_url']);
			}
			else if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$register_url = home_url($this->options['login_url']);
				$args = array('action' => 'register');
				$url = add_query_arg($args, $register_url);
			}					
		}
		if (strpos($path,'wp-login.php?action=rp&') === 0) {
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {				
				$url = str_replace("wp-login.php",trim(trim($this->options['login_url']),'/'),$url);				
			}
		}
		if ($path == 'wp-login.php?action=resetpass') {
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {				
				$url = str_replace("wp-login.php",trim(trim($this->options['login_url']),'/'),$url);				
			}
		}
		if ($path == 'wp-login.php?action=lostpassword') {
			if (isset($this->options['lostpassword_url']) && trim($this->options['lostpassword_url'].' ') != '') {
				$url = home_url($this->options['lostpassword_url']);
			}
			else if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$lostpassword_url = home_url($this->options['login_url']);
				$args = array('action' => 'lostpassword');
				$url = add_query_arg($args, $lostpassword_url);
			}					
		}
		if ($path == 'wp-login.php?checkemail=confirm') {
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {
				$url = home_url($this->options['login_url']);
			}
			$args = array('checkmail' => 'confirm');
			$url = add_query_arg($args, $url);								
		}
		if (strpos($path,'wp-login.php?action=') === 0) {
			if (isset($this->options['login_url']) && trim($this->options['login_url'].' ') != '') {				
				$url = str_replace("wp-login.php",trim(trim($this->options['login_url']),'/'),$url);				
			}
		}
		if ($path == '/wp-comments-post.php') {
			if (isset($this->options['comments_url']) && trim($this->options['comments_url'].' ') != '') {
				$url = str_replace("wp-comments-post.php",trim(trim($this->options['comments_url']),'/'),$url);
			}
		}
		return $url;
	}
	
	function plugins_url($url, $path, $plugin) {
		if ($this->disable_filters) {
			return $url;
		}				
		$trailing_slash = false;
		if (trailingslashit($url) == $url) {
			$trailing_slash = true;
		}
		foreach ($this->options['plugins'] as $slug=>$path) {
			$url = str_replace(trailingslashit(WP_PLUGIN_URL).$slug.'/','/'.$path.'/',trailingslashit($url));
		}
		if ((isset($this->options['plugins_relative']) && $this->options['plugins_relative'] =='1')) {
			$url = $this->relative_url($url);
		}
		if (!$trailing_slash) {
			$url = untrailingslashit($url);
		}
		return $url;
	}
	
	function template_directory_uri($template_dir_uri, $template, $theme_root_uri) {
		if ($this->disable_filters) {
			return $template_dir_uri;
		}
		if (isset($this->options['theme_url']) && trim($this->options['theme_url'].' ') != '') {
			if (!$this->theme_url) {
				$template_dir_uri = str_replace(substr($theme_root_uri,strlen(trailingslashit(site_url()))).'/'.$template,trim(trim($this->options['theme_url']),'/'),$template_dir_uri);			
				$this->theme_url = $template_dir_uri;
			}
			else {
				$template_dir_uri = $this->theme_url;
			}
			if (!$this->theme_url_relative) {
				$this->theme_url_relative = $this->relative_url($template_dir_uri);
			}
		}
		if ((isset($this->options['theme_relative']) && $this->options['theme_relative'] =='1')) {
			if (!$this->theme_url_relative) {
				$template_dir_uri = $this->relative_url($template_dir_uri);
			}
		}
		return $template_dir_uri;
	}

	function stylesheet_uri($stylesheet_uri, $stylesheet_dir_uri) {
		if ($this->disable_filters) {
			return $stylesheet_uri;
		}
		if (isset($this->options['style_url']) && trim($this->options['style_url'].' ') != '') {
			$this->disable();
			$stylesheet_uri = str_replace(trailingslashit(get_template_directory_uri()).$stylesheet_uri,trim(trim($this->options['style_url']),'/'),$stylesheet_uri);
			$this->enable();
		}
		if ((isset($this->options['theme_relative']) && $this->options['theme_relative'] == '1')) {
			$stylesheet_uri = $this->relative_url($stylesheet_uri);
		}
		return $stylesheet_uri;
	}

	function stylesheet_directory_uri($stylesheet_dir_uri, $stylesheet, $theme_root_uri) {
		if ($this->disable_filters) {
			return $stylesheet_dir_uri;
		}
		if (isset($this->options['style_dir_url']) && trim($this->options['style_dir_url'].' ') != '') {
			$stylesheet_dir_uri = str_replace(substr($stylesheet_dir_uri,strlen(trailingslashit(site_url())))
																			 ,trim(trim($this->options['style_dir_url']),'/')
																			 ,$stylesheet_dir_uri);
		}
		if ((isset($this->options['theme_relative']) && $this->options['theme_relative'] =='1')) {
			$stylesheet_dir_uri = $this->relative_url($stylesheet_dir_uri);
		}
		return $stylesheet_dir_uri;
	}

	function includes_url($url, $path) {
		if ($this->disable_filters) {
			return $url;
		}
		if (isset($this->options['includes_url']) && trim($this->options['includes_url'].' ') != '') {
			$url = str_replace(WPINC
					 						  ,trim(trim($this->options['includes_url']),'/')
												,$url);
		}
		if ((isset($this->options['includes_relative']) && $this->options['includes_relative'] =='1')) {
			$url = $this->relative_url($url);
		}
		return $url;
	}
	
	function admin_url($url, $path, $blog_id) {
		if ($this->disable_filters) {
			return $url;
		}
		if (isset($this->options['admin_url']) && trim($this->options['admin_url'].' ') != '') {
			$url = str_replace('wp-admin/'
					 						  ,trailingslashit(trim(trim($this->options['admin_url']),'/'))
												,$url);
		}				
		if ($path == 'admin-ajax.php') {
			if (isset($this->options['ajax_url']) && trim($this->options['ajax_url'].' ') != '') {
				$url = str_replace(substr(trailingslashit(admin_url('/')),strlen(trailingslashit(site_url())))."admin-ajax.php",trim(trim($this->options['ajax_url']),'/'),$url);
			}
		}
		return $url;
	}

	function wp_admin_css($style, $file) {
		return $style;
	}

	function script_loader_src($src, $handle) {
		if ((isset($this->options['theme_relative']) && $this->options['theme_relative'] =='1')) {
			if ($this->theme_url && $this->theme_url_relative) {
				$src = str_replace($this->theme_url,$this->theme_url_relative,$src);
			}
		}
		if (isset($this->options['includes_url']) && trim($this->options['includes_url'].' ') != '') {
			$src = str_replace(WPINC
					 						  ,trim(trim($this->options['includes_url']),'/')
												,$src);
		}
		if (isset($this->options['admin_url']) && trim($this->options['admin_url'].' ') != '') {
			$src = str_replace(trailingslashit(site_url()).'wp-admin'
					 						  ,trailingslashit(site_url()).trim(trim($this->options['admin_url']),'/')
												,$src);
		}
		if ((isset($this->options['includes_relative']) && $this->options['includes_relative'] =='1') && strpos($src,includes_url()) !== false) {
			$src = $this->relative_url($src);
		}
		if (isset($this->options['plugins'])) {
			foreach ($this->options['plugins'] as $slug=>$path) {
				$src = str_replace(trailingslashit(WP_PLUGIN_URL).$slug.'/','/'.$path.'/',$src);
			}
		}
		if ((isset($this->options['plugins_relative']) && $this->options['plugins_relative'] =='1')) {
			foreach ($this->options['plugins'] as $slug=>$path) {
				if (strpos($src,trailingslashit(site_url()).$path) === 0) {
					$src = $this->relative_url($src);
				}
			}
		}
		return $src;
	}
	
	function style_loader_src($src, $handle) {
		if ((isset($this->options['theme_relative']) && $this->options['theme_relative'] =='1')) {
			if ($this->theme_url && $this->theme_url_relative) {
				$src = str_replace($this->theme_url,$this->theme_url_relative,$src);
			}
			if (strpos($src,trailingslashit(site_url()).trailingslashit($this->options['style_dir_url'])) === 0) {
				$src = $this->relative_url($src);
			}
		}
		if (isset($this->options['includes_url']) && trim($this->options['includes_url'].' ') != '') {
			$src = str_replace(WPINC
					 						  ,trim(trim($this->options['includes_url']),'/')
												,$src);
		}
		if ((isset($this->options['includes_relative']) && $this->options['includes_relative'] =='1') && strpos($src,includes_url()) !== false) {
			$src = $this->relative_url($src);
		}
		if (isset($this->options['admin_url']) && trim($this->options['admin_url'].' ') != '') {
			$src = str_replace(trailingslashit(site_url()).'wp-admin'
					 						  ,trailingslashit(site_url()).trim(trim($this->options['admin_url']),'/')
												,$src);
		}
		if (isset($this->options['plugins'])) {
			foreach ($this->options['plugins'] as $slug=>$path) {
				$src = str_replace(trailingslashit(WP_PLUGIN_URL).$slug.'/','/'.$path.'/',$src);
			}
		}
		if ((isset($this->options['plugins_relative']) && $this->options['plugins_relative'] =='1')) {
			foreach ($this->options['plugins'] as $slug=>$path) {
				if (strpos($src,trailingslashit(site_url()).$path) === 0) {
					$src = $this->relative_url($src);
				}
			}
		}
		return $src;
	}


	function post_link($permalink, $post, $leavename) {
		if ($this->disable_filters) {
			return $permalink;
		}
		if (isset($this->options['post_permalink_relative']) && trim($this->options['post_permalink_relative'].' ') == '1') {
			$permalink = $this->relative_url($permalink);
		}
		return $permalink;
	}
	
	function page_link($link, $post_ID, $sample) {
		if ($this->disable_filters) {
			return $link;
		}
		if (isset($this->options['page_permalink_relative']) && trim($this->options['page_permalink_relative'].' ') == '1') {
			$link = $this->relative_url($link);
		}
		return $link;
	}

	function term_link($termlink, $term, $taxonomy) {
		if ($this->disable_filters) {
			return $termlink;
		}
		if (isset($this->options['term_permalink_relative']) && trim($this->options['term_permalink_relative'].' ') == '1') {
			$termlink = $this->relative_url($termlink);
		}
		return $termlink;
	}

	function theme_root_uri($theme_root_uri, $siteurl, $stylesheet_or_template) {
		if ($this->disable_filters) {
			return $theme_root_uri;
		}
		if (is_admin() && isset($this->options['block_theme_url']) && $this->options['block_theme_url'] == '1') {			
			$theme_root_uri = str_replace('wp-content/themes',md5(site_url().$this->options['theme_url']),$theme_root_uri);
		}
		return $theme_root_uri;
	}
	
	function bloginfo_url($output, $show) {
		if ($this->disable_filters) {
			return $output;
		}
		if ($show == 'pingback_url' && isset($this->options['xmlrpc_url']) && trim($this->options['xmlrpc_url']) != '') {
			$output = str_replace('xmlrpc.php',trim($this->options['xmlrpc_url']),$output);
		}		
    if ($show == 'pingback_url' && isset($this->options['header_pingback']) && $this->options['header_pingback'] == '1') $output = '';
    return $output;
	}
	
	function activated_plugin(  $plugin, $network_activation ) {
    flush_rewrite_rules();	
	}
	
	function after_switch_theme() {
    flush_rewrite_rules();	
	}	
	
	function cron_request($cron_request_array) {
		if ($this->disable_filters) {
			return $cron_request_array;
		}
		if (isset($this->options['cron_url']) && trim($this->options['cron_url']) != '') {
			$cron_request_array['url'] = str_replace('wp-cron.php',trim(trim($this->options['cron_url']),'/').'',$cron_request_array['url']);
		}
		error_log('cron='.serialize($cron_request_array));
		return $cron_request_array;
	}
	
	function get_the_generator($gen, $type) {
		if ($this->disable_filters) {
			return $gen;
		}
		if (isset($this->options['header_hide_generator']) && $this->options['header_hide_generator'] == '1') {
			$gen = '';
		}
		else if (isset($this->options['header_generator']) && trim($this->options['header_hide_generator']) != '') {
			$gen = str_replace('WordPress/'.get_bloginfo_rss('version'),$this->options['header_generator'],$gen);
			$gen = str_replace('WordPress/'.get_bloginfo('version'),$this->options['header_generator'],$gen);
			$gen = str_replace('http://wordpress.org/?v='.get_bloginfo_rss('version'),site_url(),$gen);
			$gen = str_replace('http://wordpress.org/',site_url(),$gen);
			$gen = str_replace('WordPress '.get_bloginfo('version'),$this->options['header_generator'],$gen);
		}
		return $gen;
	}
	
	function remove_x_pingback($headers) {
		if ($this->disable_filters) {
			return $headers;
		}
		if (isset($this->options['header_x_pingback']) && $this->options['header_x_pingback'] == '1') {
    	unset($headers['X-Pingback']);
    }
    else if (isset($this->options['xmlrpc_url']) && trim($this->options['xmlrpc_url']) != '') {
    	$headers['X-Pingback'] = trailingslashit(site_url()).$this->options['xmlrpc_url'];
    }
    return $headers;
	}
	
}

$hide_wp = Hide_WP::instance();

function hide_wp_install() {
  flush_rewrite_rules();	
}
register_activation_hook( __FILE__, 'hide_wp_install' );

function hide_wp_deinstall() {
	global $hide_wp;
	$hide_wp->disable();
	update_option('rewrite_rules');
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'hide_wp_deinstall' );
