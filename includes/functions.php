<?php
if(!defined('ABSPATH'))	exit; //exit if accessed directly

function da_get_download_attachments($post_id = 0, $args = array())
{
	if(empty($post_id))
	{
		$post = get_post();
		$post_id = (isset($post->ID) ? $post->ID : 0);
	}

	$defaults = array(
		'include' => '',
		'exclude' => '',
		'orderby' => 'menu_order',
		'order' => 'asc'
	);

	$args = array_merge($defaults, $args);

	//include
	if(is_array($args['include']) && !empty($args['include']))
	{
		$ids = array();

		foreach($args['include'] as $id)
		{
			$ids[] = (int)$id;
		}

		$args['include'] = $ids;
	}
	elseif(is_numeric($args['include']))
		$args['include'] = (int)$args['include'];
	else
	{
		$args['include'] = $defaults['include'];

		//exclude
		if(is_array($args['exclude']) && !empty($args['exclude']))
		{
			$ids = array();

			foreach($args['exclude'] as $id)
			{
				$ids[] = (int)$id;
			}

			$args['exclude'] = $ids;
		}
		elseif(is_numeric($args['exclude']))
			$args['exclude'] = (int)$args['exclude'];
		else
			$args['exclude'] = $defaults['exclude'];
	}

	//order
	$args['orderby'] = (in_array($args['orderby'], array('menu_order', 'attachment_id', 'attachment_date', 'attachment_title', 'attachment_size', 'attachment_downloads'), TRUE) ? $args['orderby'] : $defaults['orderby']);
	$args['order'] = (in_array($args['order'], array('asc', 'desc'), TRUE) ? $args['order'] : $defaults['order']);

	$files = array();

	if(($files_meta = get_post_meta($post_id, '_da_attachments', TRUE)) !== '' && is_array($files_meta) && !empty($files_meta))
	{
		foreach($files_meta as $file)
		{
			$files[$file['file_id']] = array(
				'attachment_id' => $file['file_id'],
				'attachment_date' => $file['file_date'],
				'attachment_user_id' => $file['file_user_id'],
				'attachment_user_name' => get_the_author_meta('display_name', $file['file_user_id']),
				'attachment_downloads' => (int)get_post_meta($file['file_id'], '_da_downloads', TRUE)
			);
		}

		$args['include'] = array_keys($files);
	}

	$files_data = get_posts(
		array(
			'include' => $args['include'],
			'exclude' => $args['exclude'],
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
				$filename = get_attached_file($file->ID);
				$filetype = wp_check_filetype($filename);
				$extension = ($filetype['ext'] === 'jpeg' ? 'jpg' : $filetype['ext']);

				$files[$file->ID]['attachment_title'] = trim(esc_attr($file->post_title));
				$files[$file->ID]['attachment_caption'] = trim(esc_attr($file->post_excerpt));
				$files[$file->ID]['attachment_description'] = trim(esc_attr($file->post_content));
				$files[$file->ID]['attachment_size'] = (file_exists($filename) ? filesize($filename) : 0);
				$files[$file->ID]['attachment_url'] = esc_url(wp_get_attachment_url($file->ID));
				$files[$file->ID]['attachment_type'] = $extension;
				$files[$file->ID]['attachment_icon_url'] = (file_exists(DOWNLOAD_ATTACHMENTS_PATH.'images/ext/'.$extension.'.gif') ? DOWNLOAD_ATTACHMENTS_URL.'/images/ext/'.$extension.'.gif' : DOWNLOAD_ATTACHMENTS_URL.'/images/ext/unknown.gif');
			}
		}
	}

	//multiarray sorting
	if($args['orderby'] !== 'menu_order')
	{
		$sort_array = array();

		foreach($files as $key => $row)
		{
			$sort_array[$key] = ($args['orderby'] === 'attachment_title' ? mb_strtolower($row[$args['orderby']], 'UTF-8') : $row[$args['orderby']]);
		}

		$order = ($args['order'] === 'asc' ? SORT_ASC : SORT_DESC);

		array_multisort($files, SORT_NUMERIC, $order, $sort_array, (in_array($args['orderby'], array('attachment_id', 'attachment_size', 'attachment_downloads'), TRUE) ? SORT_NUMERIC : SORT_STRING), $order);
	}

	//we need to format raw data
	foreach($files as $key => $row)
	{
		$files[$key]['attachment_date'] = date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($row['attachment_date']));
		$files[$key]['attachment_size'] = size_format($row['attachment_size']);
	}

	return $files;
}


function da_display_download_attachments($post_id = 0, $args = array())
{
	if($post_id === NULL)
	{
		$post = get_post();
		$post_id = (isset($post->ID) ? $post->ID : 0);
	}

	$options = get_option('download_attachments_general');

	$defaults = array(
		'container' => 'div',
		'container_class' => 'download-attachments',
		'container_id' => '',
		'style' => 'list',
		'link_before' => '',
		'link_after' => '',
		'display_user' => (int)$options['frontend_columns']['author'],
		'display_icon' => (int)$options['frontend_columns']['icon'],
		'display_count' => (int)$options['frontend_columns']['downloads'],
		'display_size' => (int)$options['frontend_columns']['size'],
		'display_date' => (int)$options['frontend_columns']['date'],
		'display_caption' => (int)$options['frontend_content']['caption'],
		'display_description' => (int)$options['frontend_content']['description'],
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

	$args = array_merge($defaults, $args);
	$args['display_user'] = apply_filters('da_display_attachments_user', (int)$args['display_user']);
	$args['display_icon'] = apply_filters('da_display_attachments_icon', (int)$args['display_icon']);
	$args['display_count'] = apply_filters('da_display_attachments_count', (int)$args['display_count']);
	$args['display_size'] = apply_filters('da_display_attachments_size', (int)$args['display_size']);
	$args['display_date'] = apply_filters('da_display_attachments_date', (int)$args['display_date']);
	$args['display_caption'] = apply_filters('da_display_attachments_caption', (int)$args['display_caption']);
	$args['display_description'] = apply_filters('da_display_attachments_description', (int)$args['display_description']);
	$args['display_empty'] = apply_filters('da_display_attachments_empty', (int)$args['display_empty']);
	$args['use_desc_for_title'] = (int)$args['use_desc_for_title'];
	$args['echo'] = (int)$args['echo'];
	$args['style'] = (in_array($args['style'], array('list', 'none'), TRUE) ? $args['style'] : $defaults['style']);
	$args['orderby'] = (in_array($args['orderby'], array('menu_order', 'attachment_id', 'attachment_date', 'attachment_title', 'attachment_size', 'attachment_downloads'), TRUE) ? $args['orderby'] : $defaults['orderby']);
	$args['order'] = (in_array($args['order'], array('asc', 'desc'), TRUE) ? $args['order'] : $defaults['order']);
	$args['link_before'] = trim($args['link_before']);
	$args['link_after'] = trim($args['link_after']);
	$args['display_option_none'] = (($info = trim($args['display_option_none'])) !== '' ? $info : $defaults['display_option_none']);

	$args['title'] = apply_filters('da_display_attachments_title', trim($args['title']));

	$attachments = da_get_download_attachments(
		$post_id,
		apply_filters('da_display_attachments_args', array(
				'include' => $args['include'],
				'exclude' => $args['exclude'],
				'orderby' => $args['orderby'],
				'order' => $args['order']
			)
		)
	);

	$count = count($attachments);
	$html = '';

	if(!($args['display_empty'] === 0 && $count === 0))
	{
		//start container
		if($args['container'] !== '')
			$html .= '<'.$args['container'].($args['container_id'] !== '' ? ' id="'.$args['container_id'].'"' : '').($args['container_class'] !== '' ? ' class="'.$args['container_class'].'"' : '').'>';

		//title
		if($args['title'] !== '')
			$html .= ($args['title'] !== '' ? '<p class="download-title">'.$args['title'].'</p>' : '');
	}

	if($count > 0)
	{
		$i = 1;

		foreach($attachments as $attachment)
		{
			if($args['use_desc_for_title'] === 1 && $attachment['attachment_description'] !== '')
				$title = apply_filters('da_display_attachment_title', $attachment['attachment_description']);
			else
				$title = apply_filters('da_display_attachment_title', $attachment['attachment_title']);

			//start style
			if($args['style'] === 'list')
				$html .= ($i === 1 ? '<ul>' : '').'<li class="'.$attachment['attachment_type'].'">';
			else
				$html .= '<span class="'.$attachment['attachment_type'].'">';

			//type
			if($args['display_icon'] === 1)
				$html .= '<img class="attachment-icon" src="'.$attachment['attachment_icon_url'].'" alt="'.$attachment['attachment_type'].'" /> ';

			//link before
			if($args['link_before'] !== '')
				$html .= '<span class="attachment-link-before">'.$args['link_before'].'</span>';

			//link
			$html .= '<a href="'.($options['pretty_urls'] === TRUE ? site_url('/'.$options['download_link'].'/'.$attachment['attachment_id'].'/') : plugins_url('download-attachments/includes/download.php?id='.$attachment['attachment_id'])).'" title="'.$title.'">'.$title.'</a>';

			//link after
			if($args['link_after'] !== '')
				$html .= '<span class="attachment-link-after">'.$args['link_after'].'</span>';

			$html .= '<br />';

			//caption
			if($args['display_caption'] === 1 && $attachment['attachment_caption'] !== '')
				$html .= '<span class="attachment-caption">'.$attachment['attachment_caption'].'</span><br />';

			//description
			if($args['display_description'] === 1 && $args['use_desc_for_title'] === 0 && $attachment['attachment_description'] !== '')
				$html .= '<span class="attachment-description">'.$attachment['attachment_description'].'</span><br />';

			//date
			if($args['display_date'] === 1)
				$html .= '<span class="attachment-date"><span class="attachment-label">'.__('Date added', 'download-attachments').':</span> '.$attachment['attachment_date'].'</span> '; 

			//user
			if($args['display_user'] === 1)
				$html .= '<span class="attachment-user"><span class="attachment-label">'.__('Added by', 'download-attachments').':</span> '.$attachment['attachment_user_name'].'</span> '; 

			//size
			if($args['display_size'] === 1)
				$html .= '<span class="attachment-size"><span class="attachment-label">'.__('Attachment size', 'download-attachments').':</span> '.$attachment['attachment_size'].'</span> '; 

			//downloads
			if($args['display_count'] === 1)
				$html .= '<span class="attachment-downloads"><span class="attachment-label">'.__('Downloads', 'download-attachments').':</span> '.$attachment['attachment_downloads'].'</span> '; 

			//end style
			if($args['style'] === 'list')
				$html .= '</li>'.($i++ === $count ? '</ul>' : '');
			else
				$html .= '</span>';
		}
	}
	elseif($args['display_empty'] === 1)
		$html .= $args['display_option_none'];

	if(!($args['display_empty'] === 0 && $count === 0) && $args['container'] !== '')
		$html .= '</'.$args['container'].'>';

	if($args['echo'] === 1)
		echo $html;
	else
		return $html;
}


function da_download_attachment_link($attachment_id = 0, $display = FALSE)
{
	if(get_post_type($attachment_id) === 'attachment')
	{
		$options = get_option('download_attachments_general');
		$title = get_the_title($attachment_id);

		$link = '<a href="'.($options['pretty_urls'] === TRUE ? site_url('/'.$options['download_link'].'/'.$attachment_id.'/') : plugins_url('download-attachments/includes/download.php?id='.$attachment_id)).'" title="'.$title.'">'.$title.'</a>';
	}
	else
		$link = '';

	if($display === TRUE)
		echo $link;
	else
		return $link;
}


function da_download_attachment_url($attachment_id = 0)
{
	if(get_post_type($attachment_id) === 'attachment')
	{
		$options = get_option('download_attachments_general');

		return ($options['pretty_urls'] === TRUE ? site_url('/'.$options['download_link'].'/'.$attachment_id.'/') : plugins_url('download-attachments/includes/download.php?id='.$attachment_id));
	}
	else
		return '';
}


function da_download_attachment($attachment_id = 0)
{
	if(get_post_type($attachment_id) === 'attachment')
	{
		$uploads = wp_upload_dir();
		$attachment = get_post_meta($attachment_id, '_wp_attached_file', TRUE);
		$filepath = $uploads['basedir'].'/'.$attachment;

		if(!file_exists($filepath) || !is_readable($filepath))
			return FALSE;

		//if filename contains folder names
		if(($position = strrpos($attachment, '/', 0)) !== FALSE)
			$filename = substr($attachment, $position + 1);
		else
			$filename = $attachment;

		if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');

		header('Content-Type: application/download');
		header('Content-Disposition: attachment; filename='.rawurldecode($filename));
		header('Content-Transfer-Encoding: binary');
		header('Accept-Ranges: bytes');
		header('Cache-control: private');
		header('Pragma: private');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-Length: '.filesize($filepath));

		if($filepath = fopen($filepath, 'r'))
		{
			while(!feof($filepath) && (!connection_aborted()))
			{
				echo($buffer = fread($filepath, 524288));
				flush();
			}

			fclose($filepath);
		}
		else return FALSE;

		update_post_meta($attachment_id, '_da_downloads', (int)get_post_meta($attachment_id, '_da_downloads', TRUE) + 1);

		exit;
	}
	else
		return FALSE;
}
?>