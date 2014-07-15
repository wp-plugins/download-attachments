<?php
/*
Plugin Name: Download Attachments
Description: Download Attachments is a new approach to managing downloads in WordPress. It allows you to easily add and display download links in any post or page.
Version: 1.0.10
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/download-attachments/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: download-attachments
Domain Path: /languages

Download Attachments
Copyright (C) 2013, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if(!defined('ABSPATH'))	exit; //exit if accessed directly

define('DOWNLOAD_ATTACHMENTS_URL', plugins_url('', __FILE__));
define('DOWNLOAD_ATTACHMENTS_PATH', plugin_dir_path(__FILE__));
define('DOWNLOAD_ATTACHMENTS_REL_PATH', dirname(plugin_basename(__FILE__)).'/');

$download_attachments = new Download_Attachments();

include_once(DOWNLOAD_ATTACHMENTS_PATH.'includes/functions.php');
include_once(DOWNLOAD_ATTACHMENTS_PATH.'includes/shortcodes.php');
include_once(DOWNLOAD_ATTACHMENTS_PATH.'includes/settings.php');
include_once(DOWNLOAD_ATTACHMENTS_PATH.'includes/metabox.php');
include_once(DOWNLOAD_ATTACHMENTS_PATH.'includes/media.php');

class Download_Attachments
{
	private $options = array();
	private $defaults = array(
		'general' => array(
			'pretty_urls' => FALSE,
			'use_css_style' => TRUE,
			'download_link' => 'download-attachment',
			'attachment_link' => 'modal',
			'download_box_display' => 'after_content',
			'library' => 'all',
			'downloads_in_media_library' => TRUE,
			'deactivation_delete' => FALSE,
			'label' => 'Download Attachments',
			'post_types' => array(),
			'backend_columns' => array(
				'id' => TRUE,
				'author' => FALSE,
				'type' => TRUE,
				'size' => TRUE,
				'date' => FALSE,
				'downloads' => TRUE
			),
			'frontend_columns' => array(
				'author' => FALSE,
				'icon' => TRUE,
				'size' => TRUE,
				'date' => FALSE,
				'downloads' => TRUE
			),
			'backend_content' => array(
				'caption' => TRUE,
				'description' => FALSE
			),
			'frontend_content' => array(
				'caption' => TRUE,
				'description' => FALSE
			),
			'capabilities' => array(
				'manage_download_attachments'
			)
		),
		'version' => '1.0.10'
	);


	/**
	 * Class constructor
	*/
	public function __construct()
	{
		register_activation_hook(__FILE__, array(&$this, 'multisite_activation'));
		register_deactivation_hook(__FILE__, array(&$this, 'multisite_deactivation'));

		//settings
		$this->options = array_merge(
			array('general' => get_option('download_attachments_general'))
		);

		//update plugin version
		update_option('download_attachments_version', $this->defaults['version'], '', 'no');

		//actions
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('after_setup_theme', array(&$this, 'pass_variables'), 9);
		add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts_styles'));
		add_action('wp_enqueue_scripts', array(&$this, 'front_scripts_styles'));
		add_action('send_headers', array(&$this, 'download_redirect'));

		//filters
		add_filter('the_content', array(&$this, 'add_content'));
		add_filter('plugin_row_meta', array(&$this, 'plugin_extend_links'), 10, 2);
		add_filter('plugin_action_links', array(&$this, 'plugin_settings_link'), 10, 2);
	}


	/**
	 * Multisite activation
	*/
	public function multisite_activation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('download_attachments_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}
	
	
	/**
	 * Activation
	*/
	public function activate_single()
	{
		global $wp_roles;

		//add caps to administrators
		foreach($wp_roles->roles as $role_name => $display_name)
		{
			$role = $wp_roles->get_role($role_name);

			if($role->has_cap('upload_files'))
			{
				foreach($this->defaults['general']['capabilities'] as $capability)
				{
					$role->add_cap($capability);
				}
			}
		}

		//add default options
		add_option('download_attachments_general', $this->defaults['general'], '', 'no');
		add_option('download_attachments_version', $this->defaults['version'], '', 'no');
	}


	/**
	 * Multisite deactivation
	*/
	public function multisite_deactivation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('download_attachments_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single(TRUE);

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('download_attachments_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}


	/**
	 * Deactivation
	*/
	public function deactivate_single($multi = FALSE)
	{
		global $wp_roles;

		//remove capabilities
		foreach($wp_roles->roles as $role_name => $display_name)
		{
			$role = $wp_roles->get_role($role_name);

			foreach($this->defaults['general']['capabilities'] as $capability)
			{
				$role->remove_cap($capability);
			}
		}

		if($multi === TRUE)
		{
			$options = get_option('download_attachments_general');
			$check = $options['deactivation_delete'];
		}
		else
			$check = $this->options['general']['deactivation_delete'];

		if($check === TRUE)
		{
			delete_option('download_attachments_general');
			delete_option('download_attachments_version');
		}
	}


	/**
	 * Gets default settings
	*/
	public function get_defaults()
	{
		return $this->defaults;
	}


	/**
	 * Gets columns
	*/
	public function get_columns()
	{
		return $this->columns;
	}


	/**
	 * Passes variables to other classes
	*/
	public function pass_variables()
	{
		$this->columns = array(
			'id' => __('ID', 'download-attachments'),
			'author' => __('Added by', 'download-attachments'),
			'title' => __('Title', 'download-attachments'),
			'type' => __('File type', 'download-attachments'),
			'icon' => __('Icon', 'download-attachments'),
			'size' => __('Size', 'download-attachments'),
			'date' => __('Date added', 'download-attachments'),
			'downloads' => __('Downloads', 'download-attachments')
		);
	}


	/**
	 * Loads text domain
	*/
	public function load_textdomain()
	{
		load_plugin_textdomain('download-attachments', FALSE, DOWNLOAD_ATTACHMENTS_REL_PATH.'languages/');
	}


	/**
	 * Recognizes download URL
	*/
	function download_redirect()
	{
		global $wp;

		if(preg_match('/^'.$this->options['general']['download_link'].'\/(\d+)$/', $wp->request, $vars) === 1)
			da_download_attachment((int)$vars[1]);
	}


	/**
	 * Adds frontend attachments box
	*/
	public function add_content($content)
	{
		if(!is_singular() || !in_array(get_post_type(), array_keys($this->options['general']['post_types'], TRUE)) || $this->options['general']['download_box_display'] === 'manually')
			return $content;

		$args = '';

		foreach($this->options['general']['frontend_columns'] as $column => $bool)
		{
			switch($column)
			{
				case 'icon':
				case 'size':
				case 'date':
					$args .= ' display_'.$column.'="'.($bool === TRUE ? 1 : 0).'"';
					break;

				case 'author':
					$args .= ' display_user="'.($bool === TRUE ? 1 : 0).'"';
					break;

				case 'downloads':
					$args .= ' display_count="'.($bool === TRUE ? 1 : 0).'"';
					break;
			}
		}

		//after content
		if($this->options['general']['download_box_display'] === 'after_content')
			return $content.do_shortcode('[download-attachments'.$args.']');
		//before content
		else
			return do_shortcode('[download-attachments'.$args.']').$content;
	}


	/**
	 * Adds scripts and styles to backend
	*/
	public function admin_scripts_styles($page)
	{
		wp_register_style(
			'download-attachments-admin',
			DOWNLOAD_ATTACHMENTS_URL.'/css/admin.css'
		);
		
		wp_register_style(
			'download-attachments-wplike',
			DOWNLOAD_ATTACHMENTS_URL.'/css/wp-like-ui-theme.css'
		);
		
		wp_register_script(
			'download-attachments-admin-post',
			DOWNLOAD_ATTACHMENTS_URL.'/js/admin-post.js',
			array('jquery')
		);
		
		wp_register_script(
			'download-attachments-admin-settings',
			DOWNLOAD_ATTACHMENTS_URL.'/js/admin-settings.js',
			array('jquery', 'jquery-ui-core', 'jquery-ui-button')
		);
		
		wp_localize_script(
			'download-attachments-admin-settings',
			'daArgs',
			array(
				'resetToDefaults' => __('Are you sure you want to reset these settings to defaults?', 'download-attachments'),
				'resetDownloadsToDefaults' => __('Are you sure you want to reset number of downloads of all attachments?', 'download-attachments')
			)
		);
		
		$columns = 0;

		foreach($this->options['general']['backend_columns'] as $column => $bool)
		{
			if($bool === TRUE)
				$columns++;
		}

		wp_localize_script(
			'download-attachments-admin-post',
			'daArgs',
			array(
				'addTitle' => __('Select Attachments', 'download-attachments'),
				'editTitle' => __('Edit attachment', 'download-attachments'),
				'buttonAddNewFile' => __('Add selected attachments', 'download-attachments'),
				'buttonEditFile' => __('Save attachment', 'download-attachments'),
				'noFiles' => __('No attachments added yet.', 'download-attachments'),
				'deleteFile' => __('Do you want to remove this attachment?', 'download-attachments'),
				'removeFile' => __('Remove', 'download-attachments'),
				'editFile' => __('Edit', 'download-attachments'),
				'activeColumns'	=> ($columns + 2),
				'internalUnknownError' => __('Unexpected error occured. Please refresh the page and try again.', 'download-attachments'),
				'library' => ($this->options['general']['library'] === 'all' ? 1 : 0),
				'addNonce' => wp_create_nonce('da-add-file-nonce'),
				'removeNonce' => wp_create_nonce('da-remove-file-nonce'),
				'sortNonce' => wp_create_nonce('da-sort-file-nonce'),
				'attachmentLink' => $this->options['general']['attachment_link']
			)
		);

		//settings
		if($page === 'settings_page_download-attachments-options')
		{
			wp_enqueue_script('download-attachments-admin-settings');
			wp_enqueue_style('download-attachments-admin');
			wp_enqueue_style('download-attachments-wplike');
		}
		//metabox
		elseif(in_array($page, array('post.php', 'post-new.php'), TRUE) && in_array(get_post_type(), array_keys($this->options['general']['post_types']), TRUE))
		{
			wp_enqueue_media();
			wp_enqueue_style('download-attachments-admin');
			wp_enqueue_script('download-attachments-admin-post');
		}
	}


	/**
	 * Adds scripts and styles to frontend
	*/
	public function front_scripts_styles() 
	{
		if($this->options['general']['use_css_style'] === TRUE)
		{
			wp_register_style(
				'download-attachments-front',
				DOWNLOAD_ATTACHMENTS_URL.'/css/front.css'
			);

			wp_enqueue_style('download-attachments-front');
		}
	}


	/**
	 * Adds links to Support Forum
	*/
	public function plugin_extend_links($links, $file) 
	{
		if(!current_user_can('install_plugins'))
			return $links;

		$plugin = plugin_basename(__FILE__);

		if($file == $plugin)
		{
			return array_merge(
				$links,
				array(sprintf('<a href="http://www.dfactory.eu/support/forum/download-attachments/" target="_blank">%s</a>', __('Support', 'download-attachments')))
			);
		}

		return $links;
	}


	/**
	 * Add links to Settings page
	*/
	function plugin_settings_link($links, $file) 
	{
		if(!is_admin() || !current_user_can('manage_options'))
			return $links;

		static $plugin;

		$plugin = plugin_basename(__FILE__);

		if($file == $plugin) 
		{
			$settings_link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php').'?page=download-attachments-options', __('Settings', 'download-attachments'));
			array_unshift($links, $settings_link);
		}

		return $links;
	}
}
?>