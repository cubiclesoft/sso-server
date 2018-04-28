Creating a Generic Login Module
===============================

The Generic Login provider supports modules.  A module is a bit of logic that extends the Generic Login provider's default e-mail address, username, and password options.  As bot authors adapt to changing circumstances and new ways are devised to keep them out of systems where they don't belong, new modules will need to be developed.  A custom-built module has the added advantage of doing something no one else is doing, which generally results in fewer bots getting through.

Developing a Generic Login module is not a simple task.  It is best to find existing modules that have aspects to them that are similar to what is desired and use them as a template for the new module.

Generic Login modules are not meant to provide additional SSO field mapping information.  Adding first name, last name, etc. fields is not the purpose of the module architecture but rather to add new system defenses - including legal, which is why there is a Terms of Service module.

Anyway, all Generic Login modules are classes that derive from the base class 'sso_login_ModuleBase'.  The official modules implement a private function called `GetInfo()` to retrieve the settings for the module, which is a good practice to follow.  The default functions in the base class don't do much of anything.  Since there is a base class, not every function must be defined in the derived class.

To create a new Generic Login module, create a PHP file inside the 'providers/sso_login/modules' directory.  The name of the file before '.php' should consist of lowercase letters and underscores and will be used as the internal module name.  The 'sso_' prefix is reserved for official modules.  The class name must be 'sso_login_module_[modulename]'.

The rest of this section is a breakdown of each member function and what it is expected to do.

sso_login_ModuleBase::DefaultOrder()
------------------------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the field display order for the module or a boolean of false if the module has no publicly displayed fields.

This member function is expected to return the order of the module when displaying fields to the user.  The Generic Login provider manages this setting and won't show an ordering option when false is returned.

sso_login_ModuleBase::ConfigSave()
----------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This member function is expected to save the configuration changes for the module.  `BB_SetPageMessage()` can set an information or error message for the user to see.

sso_login_ModuleBase::Config(&$contentopts)
-------------------------------------------

Access:  public

Parameters:

* $contentopts - An array containing Admin Pack page information.

Returns:  Nothing.

This member function is expected to append fields to `$contentopts["fields"]` containing configuration options for the module.  This function is called only if the module is enabled.  This may also modify other parts of the array (e.g. 'htmldesc') to add links to a custom page such that `$this->CustomConfig()` is called.

sso_login_ModuleBase::CustomConfig()
------------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This member function is expected to construct and display an entire page itself using `BB_GeneratePage()`.  Not really necessary but available just in case.  Something was originally going to use this, then figured out it wasn't actually necessary but the logic was left in anyway.

sso_login_ModuleBase::CheckEditUserFields(&$userinfo)
-----------------------------------------------------

Access:  public

Parameters:

* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to validate user input and save the user's information into the array if it validates.  This function is called when submitting changes while editing a user in the Generic Login admin interface.

sso_login_ModuleBase::AddEditUserFields(&$contentopts, &$userinfo)
------------------------------------------------------------------

Access:  public

Parameters:

* $contentopts - An array containing Admin Pack page information.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to append fields to `$contentopts["fields"]` when the user is being edited in the Generic Login admin interface.

sso_login_ModuleBase::AddFindUserOptions(&$contentopts, &$options, &$num)
-------------------------------------------------------------------------

Access:  public

Parameters:

* $contentopts - An array containing Admin Pack page information.
* $options - An array containing key-value pairs for "Include in Output" checkboxes at the end.
* $num - An integer containing the number of columns output so far.

Returns:  Nothing.

This member function is expected to append search fields to `$contentopts["fields"]` and any extra output options to the $options array.  This function lets a module add extra search fields and output options to the Generic Login search page to find users.  The sequence of steps for each new field must be to check if $num is the third entry in the current row and to append "startrow" if it is, then append the field, and finally increment $num.

sso_login_ModuleBase::AddFindUserCols(&$cols)
---------------------------------------------

Access:  public

Parameters:

* $cols - An array containing column names.

Returns:  A boolean of true if the function added columns, otherwise false.

This member function is expected to append a column name to the input array if the user requested that column to be displayed.  If false is returned, the module will be excluded from handling row information later.

sso_login_ModuleBase::AddFindUserRowInfo(&$result, &$userinfo)
--------------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing the current row being constructed.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to append the columns the user requested to the row using the current user information.  This function is only called by the Generic Login search enging if `AddFindUserCols()` returned true.  This function should be prepared to handle mangled data due to changes in the settings over time.

sso_login_ModuleBase::IsAllowed()
---------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user is allowed to see the Generic Login provider, false otherwise.

This member function is not used by the official modules but can be used to hide the Generic Login provider.  The default return value is true.  This was originally going to be used in the rate limiting module, but that changed during development to be more user-friendly.

sso_login_ModuleBase::AddProtectedFields(&$result)
--------------------------------------------------

Access:  public

Parameters:

* $result - An array containing key-value pairs.

Returns:  Nothing.

This member function is expected to set a mapping of SSO field names to a boolean value of whether a field is protected or not.  This function exists for future development purposes and should not be used for current modules.

sso_login_ModuleBase::AddIPCacheInfo($displayname)
--------------------------------------------------

Access:  public

Parameters:

* $displayname - A string containing the base display name to use.

Returns:  Nothing.

This member function is expected to modify the global `$contentopts` to introduce additional IP address cache information when displaying information about an IP address.  Primarily used by various rate limiting modules to show a user's current rate limiting status.

sso_login_ModuleBase::ModifyEmail($userinfo, &$htmlmsg, &$textmsg)
------------------------------------------------------------------

Access:  public

Parameters:

* $userinfo - An array containing user information.
* $htmlmsg - A string containing a message in HTML.
* $textmsg - A string containing a message in plain text.

Returns:  Nothing.

This member function is expected to modify an e-mail.  This allows a module to add extra string replacement tokens to e-mail messages sent to the user.  An example of this is the anti-phishing module with the @ANTIPHISH@ string replacement token.

sso_login_ModuleBase::TwoFactorCheck(&$result, $userinfo)
---------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to check a two-factor authentication code and add an error message if the check fails.  The member function may check the value of `$userinfo["two_factor_method"]` to see if the module is supposed to handle it or not.

sso_login_ModuleBase::TwoFactorFailed(&$result, $userinfo)
----------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is lets a module act on failures of two-factor authentications.  Primarily used by the Rate Limiting module to only allow a certain number of failures before requiring the user to sign in again.

sso_login_ModuleBase::SignupCheck(&$result, $ajax, $admin)
----------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $ajax - A boolean that specifies if this is part of an AJAX callback.
* $admin - A boolean that specifies if this is being called from the SSO server admin.

Returns:  Nothing.

This member function is expected to check the user's input for validity and append any messages to be displayed to the `$result` array.  This function is called during the Generic Login signup process.  The `$ajax` parameter can be used to take a slightly different path through the code because, generally, only one field is being checked when AJAX is used on a signup form.  Modules that support both `SignupCheck()` and `UpdateInfoCheck()` generally call an internal function that handles both calls.

sso_login_ModuleBase::SignupAddInfo(&$userinfo, $admin)
-------------------------------------------------------

Access:  public

Parameters:

* $userinfo - An array containing user information.
* $admin - A boolean that specifies if this is being called from the SSO server admin.

Returns:  Nothing.

This member function is expected to add information to the user information array based on user input.  This function is called during the Generic Login signup process.

sso_login_ModuleBase::SignupDone($userid, $admin)
-------------------------------------------------

Access:  public

Parameters:

* $userid - An integer containing the user ID of the new user.
* $admin - A boolean that specifies if this is being called from the SSO server admin.

Returns:  Nothing.

This member function lets a module act on a newly created user in the Generic Login provider database.

sso_login_ModuleBase::GetTwoFactorName()
----------------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of false or a string to display to the user representing the two-factor authentication mechanism that the module supports.

This member function lets the Generic Login provider know which modules offer two-factor authentication capabilities and presents the options to the user in various dropdowns.

sso_login_ModuleBase::GenerateSignup($admin)
--------------------------------------------

Access:  public

Parameters:

* $admin - A boolean that specifies if this is being called from the SSO server admin.

Returns:  Nothing.

This member function is expected to generate fields for the user to view or fill in.  Can also output Javascript.  Fields should be wrapped using standard 'div' wrapping.  See existing modules on how to do this correctly.  Output is cached and reordered according to the module ordering specified in the admin interface.

sso_login_ModuleBase::VerifyCheck(&$result)
-------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.

Returns:  Nothing.

This member function is expected to check settings and append any errors or warnings.  Called by the Generic Login provider when verifying an account.  Primarily used by the rate limiting module.

sso_login_ModuleBase::InitMessages(&$result)
--------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.

Returns:  Nothing.

This member function is expected to examine `$_REQUEST["sso_msg"]` and append any messages to display to the user.  Primarily used for redirection message management.

sso_login_ModuleBase::LoginCheck(&$result, $userinfo, $recoveryallowed)
-----------------------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - A boolean of false or an array containing user information.
* $recoveryallowed - A boolean that specifies if account recovery is allowed.

Returns:  Nothing.

This member function is expected to check the login information and act accordingly.  Error and warnings to display to the user are appended to the relevant section of the `$result` array.  `LoginCheck()` is called twice - once with `$userinfo` set to false and then again with an array of user information if the first check didn't encounter any problems.

sso_login_ModuleBase::SendTwoFactorCode(&$result, $userrow, $userinfo)
----------------------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userrow - An object containing user row information.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to send the two-factor authentication code to the user.  `sso_login::GetTimeBasedOTP()` is typically used to generate the time-based one-time password that is sent.

sso_login_ModuleBase::LoginAddMap(&$mapinfo, $userrow, $userinfo)
-----------------------------------------------------------------

Access:  public

Parameters:

* $mapinfo - An array containing SSO field mapping data.
* $userrow - An object containing user row information.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to append SSO field mapping data based on user information and configuration.  It is also a good place to do last minute activities such as set browser cookies.  The SSO field options exist for future development purposes and should not be used for current modules.

sso_login_ModuleBase::GenerateLogin($messages)
----------------------------------------------

Access:  public

Parameters:

* $messages - An array containing error and warning messages.

Returns:  Nothing.

This member function is expected to generate fields for the user to view or fill in.  Can also output Javascript.  Fields should be wrapped using standard 'div' wrapping.  See existing modules on how to do this correctly.  Output is cached and reordered according to the module ordering specified in the admin interface.

sso_login_ModuleBase::IsRecoveryAllowed($allowoptional)
-------------------------------------------------------

Access:  public

Parameters:

* $allowoptional - A boolean that specifies if the return value should be true if the module data is not optional.

Returns:  A boolean of true if the module offers a recovery option, false otherwise.

This member function is expected to return whether or not it supports account recovery under the specified conditions.  For example, the SMS recovery module is an optional convenience for users so, under the username only installation method, changing some information on an account would be impossible if the user didn't specify an SMS recovery phone number during account creation.

sso_login_ModuleBase::AddRecoveryMethod($method)
------------------------------------------------

Access:  public

Parameters:

* $method - A string containing the last selected recovery method.

Returns:  Nothing.

This member function is expected to output a valid select option for the module's account recovery support.

sso_login_ModuleBase::RecoveryCheck(&$result, $userinfo)
--------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - A boolean of false or an array containing user information.

Returns:  Nothing.

This member function is expected to check the recovery information and act accordingly.  Error and warnings to display to the user are appended to the relevant section of the $result array.  `RecoveryCheck()` is called twice - once with `$userinfo` set to false and then again with an array of user information if the first check didn't encounter any problems across any modules.

sso_login_ModuleBase::RecoveryDone(&$result, $method, $userrow, $userinfo)
--------------------------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $method - A string containing the selected recovery method.
* $userrow - An object containing user row information.
* $userinfo - A boolean of false or an array containing user information.

Returns:  Nothing.

This member function is expected to handle setting up for and going to the second account recovery step.  Also called for the 'email' recovery method.  This is the last chance to bail with an error message and let the user select a different account recovery method.

sso_login_ModuleBase::GenerateRecovery($messages)
-------------------------------------------------

Access:  public

Parameters:

* $messages - An array containing error and warning messages.

Returns:  Nothing.

This member function is expected to generate fields for the user to view or fill in.  Can also output Javascript.  Fields should be wrapped using standard 'div' wrapping.  See existing modules on how to do this correctly.  Output is cached and reordered according to the module ordering specified in the admin interface.

sso_login_ModuleBase::RecoveryCheck2(&$result, $userinfo)
---------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - A boolean of false or an array containing user information.

Returns:  Nothing.

This member function is expected to check the second step recovery information and act accordingly.  Error and warnings to display to the user are appended to the relevant section of the `$result` array.  `RecoveryCheck2()` is called twice - once with `$userinfo` set to false and then again with an array of user information if the first check didn't encounter any problems across any modules.

sso_login_ModuleBase::GenerateRecovery2($messages)
--------------------------------------------------

Access:  public

Parameters:

* $messages - An array containing error and warning messages.

Returns:  Nothing.

This member function is expected to generate fields for the user to view or fill in.  Can also output Javascript.  Fields should be wrapped using standard 'div' wrapping.  See existing modules on how to do this correctly.  Output is cached and reordered according to the module ordering specified in the admin interface.

sso_login_ModuleBase::UpdateInfoCheck(&$result, $userinfo, $ajax)
-----------------------------------------------------------------

Access:  public

Parameters:

* $result - An array containing error and warning messages.
* $userinfo - A boolean of false or an array containing user information.
* $ajax - A boolean that specifies if this is part of an AJAX callback.

Returns:  Nothing.

This member function is expected to check the user's input for validity and append any messages to be displayed to the `$result` array.  This function is called during the Generic Login update information process after going through the account recovery process.  The `$ajax` parameter can be used to take a slightly different path through the code because, generally, only one field is being checked when AJAX is used on a signup form.  Modules that support both `SignupCheck()` and `UpdateInfoCheck()` generally call an internal function that handles both calls.

sso_login_ModuleBase::UpdateAddInfo(&$userinfo)
-----------------------------------------------

Access:  public

Parameters:

* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to add information to the user information array based on user input.  This function is called during the Generic Login update information process after going through the account recovery process.

sso_login_ModuleBase::UpdateInfoDone($userid)
---------------------------------------------

Access:  public

Parameters:

* $userid - An integer containing the user ID of the modified user account.

Returns:  Nothing.

This member function lets a module act on a user account that has been changed in the Generic Login provider database.

sso_login_ModuleBase::GenerateUpdateInfo($userrow, $userinfo)
-------------------------------------------------------------

Access:  public

Parameters:

* $userrow - An object containing user row information.
* $userinfo - An array containing user information.

Returns:  Nothing.

This member function is expected to generate fields for the user to view or fill in so they can update their information.  Can also output Javascript.  Fields should be wrapped using standard 'div' wrapping.  See existing modules on how to do this correctly.  Output is cached and reordered according to the module ordering specified in the admin interface.
