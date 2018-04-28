SSO Server Provider:  Remote Login
==================================

The Remote Login provider gets its own section of documentation because, behind the scenes, it is a strange provider with special capabilities that both the SSO server endpoint and the SSO client directly support.  It is the only provider like this for very specific reasons that will become apparent shortly.  There are three different scenarios where the Remote Login provider comes in real handy:

* The SSO server sits outside the firewall but you want to allow employees who are logged in via VPN or on the network to sign in using Active Directory credentials rather than manage multiple sign ins.  I.T. won't, because of corporate security policies, open a hole in the firewall so you can use the SSO server LDAP provider, but they are willing to let you use an internal server behind the firewall for whatever you might want to do.
* An external company, which we'll call BigCompany, establishes a contract with your company and wants to use their own login system for their employees.
* You have an existing SSO server instance using the Generic Login provider one way (e.g. administrators sign in with a username and password for the admin interface) and want to allow those users to sign into a second SSO server instance used for the application frontend.  Or some equally bizarre scenario where remoting the login makes the most sense.

The Remote Login provider solves all of these problems and takes roughly 30 minutes to an hour to set up.  The first scenario is common and sign ins can actually be fully automated in most corporate environments (Apache + mod_auth_sspi + Active Directory + SSO Client + a little software glue).  The second scenario is quite similar to the first scenario with the only difference being that you have to work with someone else via e-mail to get it all set up.  The third is basically custom work and the Remote Provider is merely there to make the process less painful.

Setting Up a Remote Sign In
---------------------------

The Remote Login provider of the SSO Server allows for relatively quick and painless setup of a SSO Client when it is located behind a firewall.  This is accomplished with web browser redirects.  Trust is established through revokable API keys, hard to guess secrets, and layers of cryptographic communications.

The rest of this documentation assumes that a "Manager" manages the SSO server (the destination login system) and that a "Deployer" manages deployment of the SSO client.  The instructions are step-by-step.  You need only read the steps that apply to you.

Manager:  Setup a Remote API Key
--------------------------------

If the Remote Login provider is not installed, then click "Install" under the "Remote Login" option in the SSO server admin.  Then click the "Install" button on the screen that appears.  This creates the necessary database table that will contain the remotes.

Next, set up a "Remote" API key.  This procedure is identical to setting up a "Normal" API key.  After creating the API key as usual (leave the URL field empty for now), change its "Type" to "Remote" and click "Save" and the API key will be a Remote API key.  Also, be sure to map fields you want to allow the remote to be able to write to and set the "Permissions" to "Read/Write" on each mapped field.  Remote API keys can only be used with the SSO_RemoteLogin() call in the SSO client.  That is, the usual SSO client functions (e.g. `SSO_Client::Login()`) are disabled at the SSO server level.  Remote API keys can only write field mapped data, which is subsequently considered protected by the Remote Login provider.

Copy the API key numerical ID to the clipboard (or just memorize it) and select "Manage Remotes" under "Remote Login".  Click "Add Remote."  Enter in the business name or business unit that will sign in with this remote and paste or type in the API key numerical ID.  Click "Create."  If all goes well, the editing screen will appear.

On the editing screen, fill in any extra details.  The icon URL is highly recommended.  If the user gets signed out of the application for any reason, the Remote Login provider will show the icon and business name as an option for signing into the system in addition to any other providers that might be enabled.  The "Automate Validation Phase" option allows for setting up a remote to bypass the validation phase of signing in, which can be useful to enable for creating a seamless experience for a large company.  This results in similar behavior to how namespace sign ins work.

Manager:  Application Support for Remote Login
----------------------------------------------

The application itself has to support the Remote Login provider.  The most common method is to accept an additional request parameter in the URL called "sso_remote_id", which will be passed onto the SSO server as-is.  You might have code that looks like this:

```php
<?php
	if (!$sso_client->LoggedIn())  $sso_client->Login("", "You must login to use this system.");
?>
```

The `Login()` function supports a third parameter that contains an array of information that will be passed onto the SSO server.  Modify the code to look like:

```php
<?php
	$extra = array();
	if (isset($_REQUEST["sso_remote_id"]) && is_string($_REQUEST["sso_remote_id"]))
	{
		$extra["sso_provider"] = "sso_remote";
		$extra["sso_remote_id"] = $_REQUEST["sso_remote_id"];
	}
	if (!$sso_client->LoggedIn())  $sso_client->Login("", "You must login to use this system.", $extra);
?>
```

Now the application accepts a URL like:

`https://yourdomain.com/?sso_remote_id=[Deployer's Remote Key]`

You will need to figure out the correct URL for your application.  You can get the correct value for the Deployer's Remote Key from the remote configuration in the SSO Server admin under `Remote Login -> Manage Remotes -> Edit -> Remote Key`.  From here on, the URL will be referred to as the "Entry Point URL" since it is how end-users will access the system to sign into your application.

Manager:  Send Setup Information
--------------------------------

Send the following information to the Deployer:

* This webpage's URL.
* The SSO server Endpoint, Remote API key, and API secret from the API key setup in the SSO Server admin under `Manage API Keys -> Edit -> Endpoint and API Key/Secret`.
* Fields that are mapped in the API key.
* Entry Point URL.

As part of the information, it is also a good idea to include a reminder on what the next step is for the Deployer ("Install the SSO Client", "Connecting the SSO Client", and "Send Response").  Things like requesting a URL to an icon and mentioning the sign in icon size (default is 48x48 pixels), allowed IP address ranges of the Deployer's users, allowed IP address ranges of the server(s) that will be using the API key, and verification of what is already entered into the system are commonly requested.  The response for such information, as described in the next section, is known as the "Common Setup Information."

Deployer:  Installing the SSO Client
------------------------------------

Make you have received five pieces of information from the Manager:

* A SSO server Endpoint URL.
* An API key.
* An API secret.
* A list of fields that you can write to for each sign in.
* An Entry Point URL.

You may also be asked for:

* A URL to use that contains an icon of a specific size.
* The range of allowed IP addresses for your users (from an outside-your-firewall perspective).
* The range of allowed IP addresses of the server(s) that will use the API key (i.e. the server(s) on which you install the SSO client - again, from an outside-your-firewall perspective).
* To verify any information already gathered.

You will include your response as part of the "Send Response" step.  From here on, your response for this information will be referred to as "Common Setup Information."

You are now ready to install the SSO client.

* Download or 'git pull' the latest version of a suitable SSO client from [this list](https://github.com/cubiclesoft/sso-server).
* Extract the contents and upload everything to the destination host where your users will sign in, preferably in a subdirectory for easy upgrading.
* Run the installer.  This is done by executing 'install.php' via a web browser.  Preferably all compatibility tests pass for maximum performance and security.  Use the Endpoint URL, API key, and API secret during installation and make sure all tests pass before installing.

At this point, the SSO client is installed but not connected to your sign in infrastructure.

Deployer:  Connecting the SSO Client
------------------------------------

Each sign in system is different, but a lot of enterprise environments rely on Active Directory, LDAP, or another account management interface.  Users, while they understand the need, dislike signing into systems, especially with different passwords and even dislike signing in multiple times with the same username and password.  So, if you want to get fancy, look up something called SSPI or GSSPI/GSSAPI.  All major web servers have support for this protocol (e.g. mod_auth_sspi for Apache), which allows for transparent sign in using an existing set of sign in tokens from the user's OS.  For example, if a user signs in to the OS via Active Directory, which uses an authorized domain controller, the OS gets sign in tokens.  SSPI relies on NTLM, which all major web browsers support, to talk to the OS to get the signed in user's sign in tokens, which the browser passes securely to the server.  The server then passes the tokens on to Active Directory, which confirms that the tokens are valid for the particular user.  The resulting user information gets passed onto scripting languages on the server, which can act on the information.

There are two different ways to use the SSO client:  Fully automated using SSPI or a similar protocol as described above (ideal) or displaying username and password fields and having the user enter them in (not as ideal).  How you want to integrate with the SSO client is up to you.

What follows is a short code sample that demonstrates basic integration with the PHP SSO client:

```php
<?php
	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";

	$sso_client = new SSO_Client;
	$sso_client->Init();

	// Check the input to make sure signing in is even possible.
	if (!$sso_client->CanRemoteLogin())
	{
		echo "Access denied.";
		exit();
	}

	// Check login here (LDAP, SSPI, etc).
	// ...

	// Get a unique user ID and fill out protected fields.
	$userid = "42";
	$fieldmap = array(
		"first_name" => "John",
		"last_name" => "Smith",
		"email" => "john.smith@intel.com"
	);

	// Finalize the sign in.
	$sso_client->RemoteLogin($userid, $fieldmap);
?>
```

That should be fairly simple to follow along with but it might help to understand where the above code fits into the bigger picture:

The "Entry Point URL" is the front end of the application with a special token that gets passed onto the SSO server.  The SSO client running on the application front end talks to the SSO server and sets up a new temporary session and gets a URL that represents the SSO server front end.  The web browser is redirected to the SSO server front end and the Remote Login provider (the SSO server can have multiple sign in "providers") sees the special token that was passed, verifies that it is a token it recognizes, and then enables the temporary session for remote access.  The Remote Login provider then redirects the web browser to the URL where your sign in code resides (how the server knows about your internal URL is discussed a bit later).  You sign in the user and then push information to the SSO server via the `RemoteLogin()` function, which requires knowing the SSO server endpoint URL and having an API key and secret, which are stored in 'config.php'.  The `RemoteLogin()` function redirects the browser back to the SSO server front end upon successful completion of the information push.  The Remote Login provider confirms that the information sent is valid and then completes the activation step of signing in.

Depending on the information you send to the SSO server, it may also be possible to completely bypass the validation phase and have a fully transparent sign in process all done with browser redirects and cookies.  That is, the user goes to the Entry Point URL while on your network and, the next thing they know, they are signed into the Manager's application.  Some users might find it slightly magical and/or creepy despite being securely signed in.

The code you write should try to fill in the field list as best as possible.  If you use SSPI or similar, you may only get a username.  From there you'll have to connect to LDAP to obtain additional information about that user to send onto the SSO server.  Under no circumstances should you send a user's actual password to the SSO server.

From here on, the entry URL of the code that does the sign in will be referred to as the "Internal Sign In URL."

Deployer:  Send Response
------------------------

The integration on your end is now done.  You are now ready to send the response to the Manager.  Send the following information:

* Internal Sign In URL.  (Described above in "Connecting the SSO Client".)
* Common Setup Information.  (Described above in "Installing the SSO Client".)

This information actually doesn't have to be sent all at once, but the Manager will likely appreciate having everything in one nice, neat package to finalize the setup.  More than likely, you will want to test things as they are being developed.  So, as soon as each piece of information becomes available, letting the Manager know is a good strategy to being able to test the setup sooner than later.  In particular, the "Internal Sign In URL" makes it possible to test the entire sign in process.  Where there are multiple parties involved, keeping the lines of communication open is a great strategy for a successful and rapid deployment.

The Manager may indicate their preference on how and when they want to receive information based on previous deployments.

Manager:  Final Setup
---------------------

Once you receive the "Internal Sign In URL" and "Common Setup Information" as described above, you can plug the information into the correct locations within the SSO server admin.  The remote will be fully operational as soon as the "Internal Sign In URL" is copied into the Remote API key's "Live URL" field.

Once all the information is plugged in, the Deployer can begin testing.  It is important to be lenient toward changes, especially during the setup phase.  Be sure to remind the Deployer of the Entry Point URL, which users must use to sign in using the remote.

Once the Deployer is happy, you can celebrate.

Deployer: Testing and Final Deployment
--------------------------------------

Use the Entry Point URL to test the system.  This URL is what you will send to your users so they can sign into the system.  If there are changes that need to be made, contact the Manager.

How you let users know about the Entry Point URL is up to you.  It could be done via e-mail, a nightly push that adds a Bookmark to their web browser, etc.

Now that all of this is done, go celebrate!
