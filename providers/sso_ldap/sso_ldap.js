function SSO_ChangePasswordField(node, newtype)
{
	var srcnode = node.parent().parent().find('.sso_main_formdata input').get(0);
	if (srcnode)
	{
		var destnode = jQuery('<input />');
		destnode.attr('type', newtype);
		for (var key in srcnode.attributes)
		{
			if (!isNaN(key) && srcnode.attributes[key].name.toLowerCase() != 'type')  destnode.attr(srcnode.attributes[key].name, srcnode.attributes[key].value);
		}
		destnode.val(jQuery(srcnode).val());
		jQuery(srcnode).replaceWith(destnode);
	}
}

jQuery(function() {
	jQuery('.sso_main_wrap input[type=password]').each(function() {
		if (this.name)  jQuery(this).parent().parent().append('<div class="sso_main_formshowhide"><input id="sso_showhide_' + this.name + '" type="checkbox"> <label for="sso_showhide_' + this.name + '">' + SSO_Vars['showpassword'] + '</label></div>');
	});
	jQuery('.sso_main_formshowhide input').click(function() {
		SSO_ChangePasswordField(jQuery(this), this.checked ? 'text' : 'password');
	});
	jQuery('form.sso_main_form').submit(function() {
		jQuery('.sso_main_formshowhide input:checked').each(function() {
			SSO_ChangePasswordField(jQuery(this), 'password');
		});
	});
});
