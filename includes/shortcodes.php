<?php
if(!defined('ABSPATH'))	exit; //exit if accessed directly

new Download_Attachments_Shortcodes();

class Download_Attachments_Shortcodes
{
	private $options = array();


	/**
	 * Class constructor
	*/
	public function __construct()
	{
		//settings
		$this->options = array_merge(
			array('general' => get_option('download_attachments_general'))
		);

		//actions
		add_action('init', array(&$this, 'register_download_shortcodes'));
	}


	/**
	 * Registers download attachments shortcodes
	*/
	public function register_download_shortcodes()
	{
		add_shortcode('download-attachments', array(&$this, 'download_attachments_shortcode'));
		add_shortcode('download-attachment', array(&$this, 'download_attachment_shortcode'));
	}


	/**
	 * Handles download-attachments shortcode
	*/
	public function download_attachments_shortcode($args)
	{
		$defaults = array(
			'container' => 'div',
			'container_class' => 'download_attachments',
			'container_id' => '',
			'style' => 'list',
			'link_before' => '',
			'link_after' => '',
			'display_user' => (int)$this->options['general']['frontend_columns']['author'],
			'display_icon' => (int)$this->options['general']['frontend_columns']['icon'],
			'display_count' => (int)$this->options['general']['frontend_columns']['downloads'],
			'display_size' => (int)$this->options['general']['frontend_columns']['size'],
			'display_date' => (int)$this->options['general']['frontend_columns']['date'],
			'display_caption' => (int)$this->options['general']['frontend_content']['caption'],
			'display_description' => (int)$this->options['general']['frontend_content']['description'],
			'display_empty' => 0,
			'display_option_none' => __('No attachments to download', 'download-attachments'),
			'use_desc_for_title' => 0,
			'exclude' => '',
			'include' => '',
			'title' => __('Download Attachments', 'download-attachments'),
			'orderby' => 'menu_order',
			'order' => 'asc',
			'echo' => 1
		);
		
		//we have to force return in shortcodes
		$args['echo'] = 0;

		return da_display_download_attachments((int)get_queried_object_id(), shortcode_atts($defaults, $args));
	}


	/**
	 * Handles download-attachment shortcode
	*/
	public function download_attachment_shortcode($args)
	{
		$defaults = array(
			'attachment_id' => 0
		);

		$args = shortcode_atts($defaults, $args);

		return da_download_attachment_link((int)$args['attachment_id'], FALSE);
	}
}
?>