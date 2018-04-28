Creating a SSO Server Provider
==============================

Suppose a popular method of logging in is not already a part of the SSO server.  This is where creating a new SSO server provider comes into play.  This can be quite the undertaking and the simplest solution is probably to request it.  That said, it is best to look at the source code to LDAP provider.  The LDAP provider is about as simple as the average provider gets and it took less than a day to create and test the LDAP provider.  On the opposite end of the spectrum is the Generic Login provider which is very complex due to its flexibility.

There are three aspects to every SSO server provider:  The configuration, the admin interface functions, and the user interface functions.  The SSO server manages all aspects of loading and calling the correct functions at the appropriate times.

All SSO server providers are classes that derive from the base class 'SSO_ProviderBase'.  The default functions in the base class don't do much of anything.  Since there is a base class, not every function must be defined in the derived class.

To create a new provider, create a directory in the 'providers' directory with the name of the provider.  Limit the characters of the name to lowercase letters and underscores.  The 'sso_' prefix is reserved for official providers.  Inside the new directory, create an 'index.php' file.  The class name must be the same name as the directory (hence the restrictions on the directory name).

The rest of this section is a breakdown of each function and what it is expected to do.

SSO_ProviderBase::Init()
------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to initialize the class settings in preparation for other calls.  The global variable `$sso_settings` is guaranteed to at least contain a key-value pair of class name and an empty array.  Most providers use this function as an opportunity to initialize an 'iprestrict' option with the results of a `SSO_InitIPFields()` call.

SSO_ProviderBase::DisplayName()
-------------------------------

Access:  public

Parameters:  None.

Returns:  A translated string containing the name of the provider to display to a user.

This function is expected to return a translated string that will be displayed to the user.  This function is called both within the admin interface and the frontend - primarily for a selector when more than one provider is enabled.

SSO_ProviderBase::DefaultOrder()
--------------------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the default display order of the provider.

This function is expected to return the default display order for the provider in relation to other providers when more than one is available/enabled.  The default order can be overridden by changing the global configuration in the admin interface.

SSO_ProviderBase::MenuOpts()
----------------------------

Access:  public

Parameters:  None.

Returns:  An array containing "name" and "items" keys that map to a string and array of links to be displayed respectively.

This function is expected to generate a set of items to display in the admin interface and a section name for the items.  Most providers differentiate between users with 'sso_site_admin' and 'sso_admin' privileges here and show only relevant options to the user.  The array returned is ordered by the display order before being included into the global $sso_menuopts array.  URLs are generally generated with the `SSO_CreateConfigURL()` function.

SSO_ProviderBase::Config()
--------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to take request inputs and generate a standard [Admin Pack](https://github.com/cubiclesoft/admin-pack) compliant interface.  Be sure to check for permissions and errors before executing any command.

SSO_ProviderBase::IsEnabled()
-----------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user should be able to see the provider, false otherwise.

This function is expected to run a series of tests to make sure that the provider is enabled for a specific user.  Tests can range from checking for specific PHP functions and configuration settings to verifying that the user isn't coming from a spammer IP address.

SSO_ProviderBase::GetProtectedFields()
--------------------------------------

Access:  public

Parameters:  None.

Returns:  An array containing key-value pairs.

This function is expected to return a mapping of SSO field names to a boolean value of whether the field is protected or not.  Protected fields are not able to be modified by the user except possibly in the provider itself.  The only provider that currently offers direct editing of protected fields is the Generic Login provider.

SSO_ProviderBase::FindUsers()
-----------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to modify the global `$contentopts` to add the results of a search for users.  Primarily used by the Generic Login provider to find users who exist but have not activated in search results.

SSO_ProviderBase::GetEditUserLinks($id)
---------------------------------------

Access:  public

Parameters:

* $id - The internal provider ID that identifies the user.

Returns:  An array of links.

This function is expected to return an array of links if it supports editing of protected fields.  The `$this->Config()` function is expected to be able to handle the actual editing.

SSO_ProviderBase::AddIPCacheInfo()
----------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to modify the global `$contentopts` to introduce additional IP address cache information when displaying information about an IP address.  Primarily used by various Generic Login rate limiting modules.

SSO_ProviderBase::GenerateSelector()
------------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to output HTML for a selector for the frontend.  Only called when there is more than one enabled provider that the user can choose from.

SSO_ProviderBase::ProcessFrontend()
-----------------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function is expected to perform all frontend tasks required before the user can proceed to the next step.  A login system of some sort is generally implemented by this function.  `SSO_ActivateUser()` moves the user to the next step.
