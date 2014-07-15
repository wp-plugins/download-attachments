<?php
if(!defined('ABSPATH'))	exit; //exit if accessed directly

new Download_Attachments_Metabox($download_attachments);

class Download_Attachments_Metabox
{
	private $columns = array();
	private $options = array();
	private $defaults = array();
	private $download_attachments = '';


	/**
	 * Class constructor
	*/
	public function __construct($download_attachments = '')
	{
		//settings
		$this->options = array_merge(
			array('general' => get_option('download_attachments_general'))
		);

		if($download_attachments !== '')
		{
			//passed vars
			$this->download_attachments = $download_attachments;
			$this->defaults = $download_attachments->get_defaults();
			unset($download_attachments);
		}

		//actions
		add_action('add_meta_boxes', array(&$this, 'add_download_meta_box'));
		add_action('after_setup_theme', array(&$this, 'set_columns'));
		add_action('delete_attachment', array(&$this, 'remove_attachment'));
		add_action('wp_ajax_da-new-file', array(&$this, 'update_attachments'));
		add_action('wp_ajax_da-remove-file', array(&$this, 'ajax_remove_file'));
		add_action('wp_ajax_da-change-order', array(&$this, 'ajax_change_order'));
	}


	/**
	 * Sets columns passed from base class
	*/
	public function set_columns()
	{
		$this->columns = $this->download_attachments->get_columns();
	}


	/**
	 * Updates files and posts ids when removing
	*/
	public function remove_attachment($attachment_id)
	{
		$attachment_id = (int)$attachment_id;

		if(($files_meta = get_post_meta($attachment_id, '_da_posts', TRUE)) !== '' && is_array($files_meta) && !empty($files_meta))
		{
			foreach($files_meta as $id)
			{
				if(($files = get_post_meta($id, '_da_attachments', TRUE)) !== '' && is_array($files) && !empty($files))
				{
					foreach($files as $key => $file)
					{
						if((int)$file['file_id'] === $attachment_id)
						{
							unset($files[$key]);
							break;
						}
					}

					update_post_meta($id, '_da_attachments', $files);
				}
			}
		}
	}


	/**
	 * Updates attachments using AJAX
	*/
	public function update_attachments()
	{
		if(isset($_POST['danonce'], $_POST['post_id'], $_POST['attachments_ids'], $_POST['action']) && ($post_id = (int)$_POST['post_id']) > 0 && $_POST['action'] === 'da-new-file' && is_array($_POST['attachments_ids']) && !empty($_POST['attachments_ids']) && current_user_can('manage_download_attachments') && wp_verify_nonce($_POST['danonce'], 'da-add-file-nonce') !== FALSE)
		{
			global $current_user;

			$attachments_ids = $files_ids = $new_files = $deleted_atts = $rows = array();

			//checks already added attachments
			if(($files = get_post_meta($post_id, '_da_attachments', TRUE)) !== '' && is_array($files) && !empty($files))
			{
				foreach($files as $inc_id => $file)
				{
					$attachments_ids[$inc_id] = $file['file_id'];
				}
			}
			else
				$files = array();

			//prepares integer IDs
			foreach($_POST['attachments_ids'] as $att_id)
			{
				$files_ids[] = (int)$att_id;
			}

			//make sure we have unique attachments just in case
			$files_ids = array_unique($files_ids);

			//removes deselected attachments
			if(!empty($attachments_ids))
			{
				foreach($attachments_ids as $inc_id => $id)
				{
					if(!in_array($id, $files_ids, TRUE))
					{
						$deleted_atts[] = $id;
						unset($files[$inc_id]);

						if(($files_meta = get_post_meta($id, '_da_posts', TRUE)) !== '' && is_array($files_meta) && !empty($files_meta))
						{
							foreach($files_meta as $key => $post_file_id)
							{
								if($post_file_id === $post_id)
								{
									unset($files_meta[$key]);
									break;
								}
							}

							update_post_meta($id, '_da_posts', $files_meta);
						}
					}
				}
			}

			//adds selected attachments
			foreach($files_ids as $att_id)
			{
				//if we already have some attached files
				if(($files_meta = get_post_meta($att_id, '_da_posts', TRUE)) !== '' && is_array($files_meta) && !empty($files_meta))
				{
					$files_meta[] = $post_id;

					update_post_meta($att_id, '_da_posts', array_unique($files_meta));
				}
				else
					update_post_meta($att_id, '_da_posts', array($post_id));

				if(get_post_meta($att_id, '_da_downloads', TRUE) === '')
					update_post_meta($att_id, '_da_downloads', 0);

				//checks if we already have this file
				if(!in_array($att_id, $attachments_ids, TRUE))
				{
					$new_files[$att_id] = $new_file = array(
						'file_id' => $att_id,
						'file_date' => current_time('mysql'),
						'file_user_id' => $current_user->ID
					);

					array_push($files, $new_file);
				}
			}

			update_post_meta($post_id, '_da_attachments', $files);

			if(!empty($new_files))
			{
				$files = $this->prepare_files_data($post_id);

				foreach($new_files as $file)
				{
					$rows[] = $this->get_table_row($post_id, TRUE, $files[$file['file_id']]);
				}
			}

			echo json_encode(array('status' => 'OK', 'files' => $rows, 'remove' => implode(',', $deleted_atts), 'info' => ''));
		}
		else
			echo json_encode(array('status' => 'ERROR', 'files' => array(), 'remove' => '', 'info' => __('Unexpected error occured. Please refresh the page and try again.', 'download-attachments')));

		exit;
	}


	/**
	 * Removes file using AJAX
	*/
	public function ajax_remove_file()
	{
		if(isset($_POST['action'], $_POST['attachment_id'], $_POST['post_id']) && $_POST['action'] === 'da-remove-file' && current_user_can('manage_download_attachments') && wp_verify_nonce($_POST['danonce'], 'da-remove-file-nonce') !== FALSE)
		{
			$post_id = (int)$_POST['post_id'];
			$att_id = (int)$_POST['attachment_id'];

			if(($files = get_post_meta($att_id, '_da_posts', TRUE)) !== '' && is_array($files) && !empty($files))
			{
				foreach($files as $key => $id)
				{
					if($id === $post_id)
					{
						unset($files[$key]);
						break;
					}
				}

				update_post_meta($att_id, '_da_posts', $files);
			}

			if(($files = get_post_meta($post_id, '_da_attachments', TRUE)) !== '' && is_array($files) && !empty($files))
			{
				foreach($files as $key => $file)
				{
					if($file['file_id'] == $att_id)
					{
						unset($files[$key]);
						break;
					}
				}

				update_post_meta($post_id, '_da_attachments', $files);
			}

			echo json_encode(array('status' => 'OK', 'info' => ''));
		}
		else
			echo json_encode(array('status' => 'ERROR', 'info' => __('Unexpected error occured. Please refresh the page and try again.', 'download-attachments')));

		exit;
	}


	/**
	 * Changes attachments order
	*/
	public function ajax_change_order()
	{
		if(isset($_POST['action'], $_POST['attachments_ids'], $_POST['post_id']) && $_POST['action'] === 'da-change-order' && is_array($_POST['attachments_ids']) && !empty($_POST['attachments_ids']) && current_user_can('manage_download_attachments') && wp_verify_nonce($_POST['danonce'], 'da-sort-file-nonce') !== FALSE)
		{
			$post_id = (int)$_POST['post_id'];

			if(($files = get_post_meta($post_id, '_da_attachments', TRUE)) !== '' && is_array($files) && !empty($files))
			{
				$old_order = $new_order = $deleted = array();

				foreach($files as $file)
				{
					$old_order[$file['file_id']] = $file;
				}

				foreach(array_unique($_POST['attachments_ids']) as $attachment_id)
				{
					if(isset($old_order[$attachment_id]))
						$new_order[] = $old_order[$attachment_id];
					else
						$deleted[] = $attachment_id;
				}

				update_post_meta($post_id, '_da_attachments', $new_order);

				if(!empty($deleted))
					$info = __('Order of attachments has been changed but some of them no longer exists', 'download-attachments').': '.implode(', ', $deleted).'.';
				else
					$info = '';

				echo json_encode(array('status' => 'OK', 'info' => $info, 'ids' => $deleted));
			}
			else
				echo json_encode(array('status' => 'ERROR', 'info' => __('Unexpected error occured. Please refresh the page and try again.', 'download-attachments')));
		}
		else
			echo json_encode(array('status' => 'ERROR', 'info' => __('Unexpected error occured. Please refresh the page and try again.', 'download-attachments')));

		exit;
	}


	/**
	 * Adds metabox
	*/
	public function add_download_meta_box()
	{
		if(!current_user_can('manage_download_attachments'))
			return;

		//filterable metabox settings 
		$context = apply_filters('da_metabox_context', 'normal');
		$priority = apply_filters('da_metabox_priority', 'high');

		foreach($this->options['general']['post_types'] as $post_type => $bool)
		{
			if($bool === TRUE)
			{
				//get post ID
				if(isset($_GET['post']))
					$post_id = $_GET['post'];
				elseif(isset($_POST['post_ID']))
					$post_id = $_POST['post_ID'];

				if(!isset($post_id))
					$post_id = FALSE;

				//add metabox
				if(apply_filters('da_metabox_limit', TRUE, $post_id))
				{
					add_meta_box(
						'download_attachments_metabox',
						__('Download Attachments', 'download-attachments'),
						array(&$this, 'display_metabox'),
						$post_type,
						$context,
						$priority
					);
				}
			}
		}
	}


	/**
	 * Displays metabox
	*/
	public function display_metabox($post) 
	{
		$hide = '';

		echo '
		<div id="download-attachments">
			<p id="da-add-new-file">
				<a class="button-primary" href="#">'.__('Add new attachment', 'download-attachments').'</a>
			</p>
			<p id="da-spinner"></p>';

		$this->display_table_start($post->ID);
		$this->display_table_body($post->ID);
		$this->display_table_end();

		echo '
			<p id="da-infobox" style="display: none;"></p>
		</div>';
	}


	/**
	 * Prepares attachments data for output
	*/
	public function prepare_files_data($post_id = 0, $files = array())
	{
		if(empty($files) && (($files_meta = get_post_meta($post_id, '_da_attachments', TRUE)) !== '' && is_array($files_meta) && !empty($files_meta)))
		{
			foreach($files_meta as $file)
			{
				$files[$file['file_id']] = array(
					'file_id' => $file['file_id'],
					'file_date' => $file['file_date'],
					'file_user_id' => $file['file_user_id'],
					'file_downloads' => (int)get_post_meta($file['file_id'], '_da_downloads', TRUE)
				);
			}
		}

		if(!empty($files))
		{
			$files_data = get_posts(
				array(
					'include' => array_keys($files),
					'posts_per_page' => -1,
					'offset' => 0,
					'orderby' => 'post_date',
					'order' => 'DESC',
					'post_type' => 'attachment',
					'post_status' => 'any'
				)
			);

			if(!empty($files_data))
			{
				foreach($files_data as $file)
				{
					if(isset($files[$file->ID]))
					{
						$title = '';
						$filename = get_attached_file($file->ID);
						$filetype = wp_check_filetype($filename);
						$displayname = get_avatar($files[$file->ID]['file_user_id'], 16).get_the_author_meta('display_name', $files[$file->ID]['file_user_id']);

						if($this->options['general']['backend_content']['caption'] === TRUE)
							$title .= '<span class="caption">'.esc_attr($file->post_excerpt).'</span>';

						if($this->options['general']['backend_content']['description'] === TRUE)
							$title .= '<span class="description">'.esc_attr($file->post_content).'</span>';

						$files[$file->ID]['file_date'] = date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($files[$file->ID]['file_date']));
						$files[$file->ID]['file_author'] = (current_user_can('edit_users') ? '<a href="'.esc_url(admin_url('user-edit.php?user_id='.$files[$file->ID]['file_user_id'])).'">'.$displayname.'</a>' : $displayname);
						$files[$file->ID]['file_type'] = ($filetype['ext'] === 'jpeg' ? 'jpg' : $filetype['ext']);
						$files[$file->ID]['file_size'] = (file_exists($filename) ? size_format(filesize($filename)) : '0 B');
						$files[$file->ID]['file_title'] = '<p><a target="_blank" href="'.esc_url(wp_get_attachment_url($file->ID)).'">'.esc_attr($file->post_title).'</a>'.$title.'</p>';
					}
				}
			}
		}

		return $files;
	}


	/**
	 * Displays table's head
	*/
	public function display_table_start($post_id = 0)
	{
		$html = '
		<table id="da-files" class="widefat" rel="'.$post_id.'">
			<thead>
				<tr>
					<th class="file-drag"></th>';

		foreach($this->columns as $column => $name)
		{
			if($column !== 'icon' && $this->options['general']['backend_columns'][$column] === TRUE)
			{
				$html .= '
					<th class="file-'.$column.'">'.$name.'</th>';
			}
		}

		$html .= '
					<th class="file-actions">'.__('Actions', 'download-attachments').'</th>
				</tr>
			</thead>';

		echo $html;
	}


	/**
	 * Display table's foot
	*/
	public function display_table_end()
	{
		echo '
		</table>';
	}


	/**
	 * Displays table's row
	*/
	public function get_table_row($post_id = 0, $ajax = FALSE, $file = array())
	{
		$html = '<tr'.($ajax === TRUE ? ' style="display: none;"' : '').' id="att-'.$file['file_id'].'"><td class="file-drag"></td>';

		foreach($this->columns as $column => $name)
		{
			if($column != 'icon' && $this->options['general']['backend_columns'][$column] === TRUE)
			{
				$html .= '<td class="file-'.$column.'">'.$file['file_'.$column].'</td>';
			}
		}

		$html .= '<td class="file-actions">';

		if(current_user_can('edit_post', $file['file_id']))
		{
			$html .= '<a class="button-secondary da-edit-file" href="'.($this->options['general']['attachment_link'] === 'modal' ? '#' : esc_url(admin_url('post.php?post='.$file['file_id'].'&action=edit'))).'">'.__('Edit', 'download-attachments').'</a> ';
		}
		else
			$html .= '<span class="button-secondary" disabled="disabled">'.__('Edit', 'download-attachments').'</span> ';

		$html .= '<a href="#" class="button-secondary da-remove-file remove">'.__('Remove', 'download-attachments').'</a></td></tr>';

		return $html;
	}


	/**
	 * Displays table with attachments
	*/
	public function display_table_body($post_id)
	{
		$files = $this->prepare_files_data($post_id);

		if(!empty($files))
		{
			$html = '<tbody>';

			foreach($files as $file)
			{
				$html .= $this->get_table_row($post_id, FALSE, $file);
			}

			$html .= '</tbody>';
		}
		else
		{
			$columns = 0;

			foreach($this->options['general']['backend_columns'] as $column => $bool)
			{
				if($bool === TRUE)
					$columns++;
			}

			$html = '
			<tbody>
				<tr id="da-info">
					<td colspan="'.($columns + 2).'">'.__('No attachments added yet.', 'download-attachments').'</td>
				</tr>
			</tbody>';
		}

		echo $html;
	}
}
?>