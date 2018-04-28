Using Custom API Keys
=====================

Here be dragons.  The not recommended last resort workaround for dealing with encountered SSO server endpoint limitations.

If you have entered the area of custom API keys, then you have reached a limitation of the core SSO server endpoint API.  Instead of working with custom API keys and endpoint hooks, it is highly recommended to find another approach to accomplishing the task.  There are very few reasons to ever use a custom API key when other options almost always exist that will work better.  Custom API keys should only be used as an absolute last resort.

Creating a custom API key is a process that involves multiple steps:

* First, create an API key in the SSO server admin and edit it.  The "Type" dropdown contains three options - Normal, Remote, and Custom.  Select "Custom" and save the API key.  This creates the custom API key that will be used later.
* Create a file called 'endpoint_hook.php' in the same directory as 'endpoint.php'.  The endpoint will automatically load this file if it exists.
* Write a function called `EndpointHook_CustomHandler()`.  The function takes no options and is expected to return a boolean to the caller that indicates whether or not the "action" was handled.
* Within EndpointHook_CustomHandler(), write the custom code to do the task you want.  The handler should handle the "action" you plan to use with SSO_Client::SendRequest().  It is highly recommended to actually open and read 'endpoint.php' to gain a proper understanding of how the SSO server endpoint functions for the operations it handles natively.
* From the application using the SSO client, call `SSO_Client::SendRequest()` (e.g. `$sso_client->SendRequest()`) with the action you wish to run and the options you wish to send.  Be sure to check the return value.  The API key and secret must be the custom API key and secret from earlier (normal and remote API keys won't work).  Read the documentation on `SSO_Client::SendRequest()` and look at the source code in 'support/sso_functions.php' in the client to see how this low-level function works.

Those are the basic guidelines for working with custom API keys.  The best way to work with them is to crack open the relevant source code of the SSO server and client to understand what is going on.  Help with working with custom API keys is limited and falls outside the general scope of support.
