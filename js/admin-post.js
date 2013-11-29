jQuery(document).ready(function($) {

	var daEditFrame = null,
		daAddFrame = null;

	daAddFrame = wp.media({
		frame: 'select',
		title : daArgs.addTitle,
		multiple : 'add',
		button : {
			text: daArgs.buttonAddNewFile
		},
		states: [
			new wp.media.controller.Library({
				multiple: 'add',
				priority: 20,
				filterable: 'all'
			})
		]
	});

	function selectAttachments(activate) {
		var contentItem = daAddFrame.content.get().$el;

		if(collection = daAddFrame.content.get().collection) {
			if(activate === true) {
				var liItem = contentItem.find('.attachments > .attachment:eq('+(collection.length - 1)+')');

				if($('#da-files tbody tr[id="att-'+collection.last().id+'"]').length === 1) {
					liItem.addClass('hidden-attachment');
					liItem.removeClass('details');
					liItem.removeClass('selected');
					liItem.find('a.check').hide().attr('rel', 'da-hidden');
				} else {
					if(liItem.find('a.check').attr('rel') === 'da-hidden') {
						liItem.find('a.check').attr('rel', '');
						liItem.removeClass('hidden-attachment');
					}
				}
			} else {
				collection.each(function(item, count) {
					var liItem = contentItem.find('.attachments > .attachment:eq('+count+')');

					if($('#da-files tbody tr[id="att-'+item.id+'"]').length === 1) {
						liItem.addClass('hidden-attachment');
						liItem.removeClass('details');
						liItem.removeClass('selected');
						liItem.find('a.check').hide().attr('rel', 'da-hidden');
					} else {
						if(liItem.find('a.check').attr('rel') === 'da-hidden') {
							liItem.find('a.check').attr('rel', '');
							liItem.removeClass('hidden-attachment');
						}
					}
				});
			}
		}
	}

	/*
	daAddFrame.on('content:activate', function() {
		setTimeout(function() {
			daAddFrame.content.get().collection.on('add', function() {
				selectAttachments(true);
			});
		}, 0);
	});
	*/

	daAddFrame.on('open', function() {
		var selection = daAddFrame.state().get('selection'),
			id,
			attachment;

		selection.reset([]);

		$.each($('#da-files tbody tr[id^="att"]'), function() {
			id = $(this).attr('id').split('-')[1];

			if(id !== '') {
				attachment = wp.media.attachment(id);
				attachment.fetch();
				selection.add(attachment ? [attachment] : []);
			}
		});

		//selectAttachments(false);
	});

	daAddFrame.on('close', function() {
		$('#da-add-new-file a').attr('disabled', false);
	});

	daAddFrame.on('select', function() {
		var library = daAddFrame.state().get('selection'),
			ids = library.pluck('id');

		$('#da-spinner').fadeIn(300);

		$.post(ajaxurl, {
			action: 'da-new-file',
			danonce: daArgs.addNonce,
			html: daAddFrame.link,
			post_id: wp.media.view.settings.post.id,
			attachments_ids: ids
		}).done(function(data) {
			try {
				var json = $.parseJSON(data);

				if(json.status === 'OK') {
					var infoRow = $('#da-files tbody tr#da-info');

					//if no files
					if(infoRow.length === 1) {
						infoRow.fadeOut(300, function() {
							$(this).remove();
							$('#da-files tbody').append(json.files);
							$('#da-files tbody tr').fadeIn(300);
						});
					} else {
						$('#da-files tbody').append(json.files);
						$('#da-files tbody tr').fadeIn(300);
					}

					if(json.remove !== '') {
						var deletedAtts = json.remove.split(',');

						for(i in deletedAtts) {
							$('tr#att-'+deletedAtts[i]).fadeOut(300, function() {
								$(this).remove();
							});
						}
					}

					/*
					if(infoRow.length === 0) {
						$('#da-files tbody').hide().append('<tr id="da-info"><td colspan="'+daArgs.activeColumns+'">'+daArgs.noFiles+'</td></tr>').fadeIn(300);
					}
					*/

					$('#da-infobox').html('').fadeOut(300);
				} else {
					if(json.info !== '') {
						$('#da-infobox').html(json.info).fadeIn(300);
					} else {
						$('#da-infobox').html('').fadeOut(300);
					}
				}
			} catch (e) {
				$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
			}

			$('#da-spinner').fadeOut(300);
		}).fail(function() {
			$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
			$('#da-spinner').fadeOut(300);
		});
	});

	$(document).on('click', '#da-add-new-file a', function() {
		if($(this).attr('disabled') === 'disabled') {
			return false;
		}

		$(this).attr('disabled', true);

		daAddFrame.open();
	});

	$(document).on('click', '.da-edit-file', function() {
		if(daArgs.attachmentLink === 'modal') {
			var fileID = parseInt($(this).closest('tr[id^="att"]').attr('id').split('-')[1]),
				attachmentChanged = false;

			if(daEditFrame !== null) {
				daEditFrame.detach();
				daEditFrame.dispose();
				daEditFrame = null;
			}

			daEditFrame = wp.media({
				frame: 'select',
				title : daArgs.editTitle,
				multiple : false,
				button : {
					text: daArgs.buttonEditFile
				},
				library : {
					post__in : fileID
				}
			});

			daEditFrame.on('open', function() {
				var attachment = wp.media.attachment(fileID);

				daEditFrame.$el.closest('.media-modal').addClass('da-edit-modal');
				attachment.fetch();
				daEditFrame.state().get('selection').add(attachment);

				daEditFrame.$el.on('change', '.setting input, .setting textarea', function(e) {
					attachmentChanged = true;
				});
			});

			daEditFrame.on('close',function() {
				daEditFrame.$el.closest('.media-modal').removeClass('da-edit-modal');

				if(attachmentChanged === true) {
					var title = daEditFrame.$el.find('.setting[data-setting="title"] input').val(),
						caption = daEditFrame.$el.find('.setting[data-setting="caption"] textarea').val(),
						description = daEditFrame.$el.find('.setting[data-setting="description"] textarea').val();

					$('tr#att-'+fileID+' td.file-title p').fadeOut(100, function() {
						$(this).find('a').html(title);
						$(this).find('span[class="description"]').html(description);
						$(this).find('span[class="caption"]').html(caption);
						$(this).fadeIn(300);
					});
				}
			});

			daEditFrame.open();
		}
	});

	$(document).on('click', '.da-remove-file', function() {
		if(confirm(daArgs.deleteFile)) {
			var button = $('#da-add-new-file a'),
				fileID = parseInt($(this).closest('tr').attr('id').split('-')[1]),
				postID = parseInt($(this).closest('table').attr('rel'));

			$('#da-spinner').fadeIn(300);
			$('tr#att-'+fileID).addClass('remove');
			$('tr#att-'+fileID+' td.file-actions a').attr('disabled', true);
			$('tr#att-'+fileID+' td.file-actions span').attr('disabled', true);

			$.post(ajaxurl, {
				action: 'da-remove-file',
				attachment_id: fileID,
				post_id: postID,
				danonce: daArgs.removeNonce
			}).done(function(data) {
				try {
					var json = $.parseJSON(data);

					if(json.status === 'OK') {
						$('tr#att-'+fileID).fadeOut(300, function() {
							$(this).remove();

							if($('#da-files tbody tr').length === 0) {
								$('#da-files tbody').hide().append('<tr id="da-info"><td colspan="'+daArgs.activeColumns+'">'+daArgs.noFiles+'</td></tr>').fadeIn(300);
							}
						});

						$('#da-infobox').html('').fadeOut(300);
					} else {
						if(json.info !== '') {
							$('#da-infobox').html(json.info).fadeIn(300);
						} else {
							$('#da-infobox').html('').fadeOut(300);
						}
					}
				} catch (e) {
					$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
				}

				$('#da-spinner').fadeOut(300);
				button.attr('disabled', false);
			}).fail(function() {
				$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
				$('#da-spinner').fadeOut(300);
				button.attr('disabled', false);
			});
        }

		return false;
	});

	$('#da-files tbody').sortable({
		axis: 'y',
		cursor: 'move',
		delay: 0,
		distance: 0,
		items: 'tr',
		forceHelperSize: false,
		forcePlaceholderSize: false,
		handle: '.file-drag',
		opacity: 0.6,
		revert: true,
		scroll: true,
		tolerance: 'pointer',
		helper: function(e, ui) {
			var original = ui.children(),
				helper = ui.clone();

			helper.children().each(function(i) {
				$(this).width(original.eq(i).width());
			});

			return helper;
		},
		start: function(e, ui) {
			$('#da-add-new-file a').attr('disabled', true);
		},
		update: function(e, ui) {
			$('#da-spinner').fadeIn(300);
		},
		stop: function(e, ui) {
			$('#da-spinner').fadeOut(300);
			$('#da-add-new-file a').attr('disabled', false);
		},
		beforeStop: function(e, ui) {
			var newOrder = [];

			$.each($('#da-files tr[id^="att"]'), function(i) {
				newOrder[i] = parseInt($(this).attr('id').split('-')[1]);
			});

            var postID = parseInt($(this).closest('table').attr('rel'));

			$.post(ajaxurl, {
				action: 'da-change-order',
				post_id: postID,
				attachments_ids: newOrder,
				danonce: daArgs.sortNonce
			}).done(function(data) {
				try {
					var json = $.parseJSON(data);

					if(json.status === 'OK') {
						$('#da-infobox').html('').fadeOut(300);
					}

					if(json.info !== '' && json.ids.length > 0) {
						$('#da-infobox').html(json.info).fadeIn(300);

						for(i in json.ids) {
							$('tr#att-'+json.ids[i]).fadeOut(300, function() {
								$(this).remove();
							});
						}
					} else {
						$('#da-infobox').html('').fadeOut(300);
					}
				} catch (e) {
					$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
				}
			}).fail(function() {
				$('#da-infobox').html(daArgs.internalUnknownError).fadeIn(300);
			});
		}
	});
});