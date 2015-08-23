function SSO_SendField()
{
	if (this.name)
	{
		var data = {};
		data[this.name] = jQuery(this).val();
		jQuery('form.sso_main_form input[type=hidden]').each(function() {
			data[this.name] = jQuery(this).val();
		});
		jQuery(this).parent().parent().find('.sso_main_formresult').html('<div class="sso_main_formchecking">' + SSO_Vars['checking'] + '</div>');
		jQuery(this).parent().parent().find('.sso_main_formresult').load(SSO_Vars['ajaxurl'], data);
	}
}

function SSO_ChangePasswordField(node, newtype)
{
	var srcnode = node.parent().parent().find('.sso_main_formdata input').get(0);
	if (srcnode)
	{
		var destnode = jQuery('<input />');
		if (jQuery(srcnode).hasClass('sso_login_changehook'))  destnode.change(SSO_SendField);
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
	jQuery('input.sso_login_changehook').change(SSO_SendField).parent().parent().append('<div class="sso_main_formresult"></div>');
});
