function InitPropertiesTableDragAndDrop(tableid, callback)
{
	$('#' + tableid).tableDnD({
		dragHandle : '.draghandle',
		onDragClass : 'dragactive',
		onDrop : function (table, row) {
			var altrow = false;

			$('#' + tableid + ' tr').each(function(x) {
				if (altrow)  $(this).addClass('altrow');
				else  $(this).removeClass('altrow');

				altrow = !altrow;
			});

			if (callback)  callback();
		}
	});
}

$(window).resize(function() {
	$(window).trigger('resize.stickyTableHeaders');
});

$(function() {
	$('#navbutton').click(function() {
		$('#navbutton').toggleClass("clicked");
		$('#navdropdown').toggle().each(function() {
			pos = $('#navbutton').position();
			height = $('#navbutton').outerHeight();
			$(this).css({ top: (pos.top + height) + "px" });
		});
	});

	$('.leftnav').clone().appendTo('#navdropdown');
});