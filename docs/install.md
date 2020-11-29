Installing Barebones SSO Server/Client
--------------------------------------

This extensive document walks through the entire procedure of installing the product.  The recommended approach is to [watch the tutorial video series](https://www.youtube.com/watch?v=xjPp_YVGttw&list=PLIvucSFZRDjgiSfsm707zn-bqKd64Eikb) and follow along.  The tutorial is not required but does provide visual cues not available in this documentation.

Terminology and Concepts
------------------------

Unless you have used a Single Sign-On (SSO) system before, there is some terminology that you will need to understand to be able to work with this software product.

* Field - A place to store information about a user.  First name, last name, e-mail address, location, etc.
* Tag - A place to store administrative access permissions to other systems or special flags for any purpose.  Primarily for manually added permissions and roles.  Forum moderator, editor, etc.
* Provider - An access authentication and authorization mechanism.  Generally adds entries to the administrative interface and the end-user interface.
* Provider ID - An ID that a provider provides during authentication with the SSO server that, when combined with the provider, uniquely identifies an associated user account.
* User Account - Stores a unique ID, provider name, provider ID, fields, and associated tags.
* Endpoint - A URL that exposes the API of the SSO server.
* API Key - Consists of a short string and a numeric ID.  API keys are used during SSO client installation and grants access to the SSO server endpoint.
* API Secret - A secret, shared token used for encryption of data sent across the wire.  The API secret is stored alongside an API key and used during SSO client installation and grants access to the SSO server endpoint.
* Namespace - Each API key has a namespace.  When a user signs in to one application, they will be signed in automatically if they visit another application using another API key in the same namespace.

The SSO server separates login information from account information.  The SSO server only knows what a Provider and a user account are, but not a login.  Today, a Provider will implement a standard login system.  However, if software developers figure out a way to eliminate usernames and passwords in the future, this SSO server can easily adapt to the new approach via a new Provider.

The SSO client communicates with the SSO server using an API key and encrypts the transmission using the associated API secret.

Architecture Overview
---------------------

If you are the type of person who likes to know how things work at a high-level before delving into implementation details, this section is for you.  A simplified breakdown of how things work is:

* The SSO server and client are two distinctly separate pieces of installable software that talk to each other.
* Your application interfaces with the SSO client.
* The SSO client is an installed piece of software that interfaces with the SSO server endpoint via an API key and secret.  Each application should have its own distinct SSO client install.
* The SSO server endpoint directs the SSO client regarding session information.
* If the user is not signed in or doesn't have the appropriate permissions, the application uses the SSO client library to initiate a new session with the SSO server (via the endpoint API).
* The SSO client redirects the browser to the SSO server frontend when a new session is successfully initiated.
* The SSO server frontend is broken down into two steps:  Authentication and validation.
* During authentication, the SSO server frontend lets the user pick from the available providers.  If there is only one provider, that provider is automatically chosen.  The most popular provider is Generic Login, which implements a generic login system.  Providers are the gate-keepers that do authentication.  The SSO_ActivateUser() function moves the user to the validation step.
* During validation, customization of the frontend is possible.  Developers can do whatever they want during this step.  The most common thing to do is have the user fill in information that is missing from their profile that the SSO client (and your application) needs.  What fields are needed are dictated by the the API key used by the SSO client.
* The SSO_ValidateUser() function finalizes the sign in and redirects the browser back to the application.
* Your application, already interfaced with the SSO client, implicitly notices that the browser has just come back from the SSO server.
* The SSO client saves the new session information, redirects the browser one last time, loads the previous session's data from the SSO server ($_GET, $_POST, etc), and the application continues on from where it left off.

As far as most developers are concerned, the following set of steps will construct the first working application that uses the SSO server and client:

* Install the SSO server.
* Set up a basic 'admin_hook.php' file that restricts access to the current IP address.
* Configure the basic settings.
* Set up fields.
* Configure the first provider (usually Generic Login).
* Configure the first API key and secret.
* Install the SSO client.
* Test the SSO client.  Both Object Oriented ('test_oo.php') and non-OO ('test_flat.php') samples exist - either one will form the basis of integrating your application with the SSO client.
* Set up 'header.php' and 'footer.php' on the SSO server so that provider selection, the Generic Login provider, error messages, and the validation phase have design and styling that provides a pleasing and consistent user experience with the rest of your website.
* Set up the SSO site admin account.
* Secure the SSO server.

The SSO server and client are generally quite flexible, which is why so many steps are necessary to get a working and secure setup.  Once the first application is up and running, it becomes much easier to start deploying more.

A Brief Note on Security
------------------------

Many PHP login systems out there claim to be "secure".  The unfortunate reality is that those same login systems are riddled with security vulnerabilities and are in the top 20 Google search results.  In addition, [security is a moving target](http://cubicspot.blogspot.com/2013/03/security-is-moooooving-target.html).  As a user, you have to take the time to make sure the system you want to use is doing things in a way that protects your users from a wide variety of attacks.  Increasing the difficulty is the changing legal landscape regarding lawsuits.  You generally only hear about the really big data/trust breaches, but servers are actually compromised daily.  The daily breaches that take place across the Internet, usually traced back to holes in a login system, will eventually result in lawsuits against those who have a trust breach of any kind.  Most people who write login systems don't know about every type of vulnerability out there and fixing one vulnerability can actually create new, unanticipated vulnerabilities.  The kind of experience required to develop a secure login system requires patience, tenacity, and skill and most people aren't willing to maintain the code they write even as new vulnerability vectors arise.

So security is hard to get right and hard to maintain.  And your next question is:  "Is this system secure?" That's a loaded question.  The answer is "probably".  The SSO server/client definitely has better defenses out there than any other open source product and I am always actively hunting for the next vulnerability.  This is also one of the few login systems out there backed financially by a real business plus the occasional donation.  The product has seen regular development since 2012.

One final note:  I have found that a number of users take this product, modify it, and then show later asking for help.  Inevitably, after discussing their situation, it is revealed that they have fundamentally broken the security of this system and unknowingly introduced critical security vulnerabilities into their installation.  If you find yourself modifying the product itself, please ask first!  Not only do you avoid an unpleasant conversation about software security, but you'll ultimately benefit everyone else who might have the same needs as yourself through future constructive product improvements.

SSO Server Installation
-----------------------

Installing the SSO client is fairly straightforward:

* Download or 'git pull' the latest SSO server.
* Extract the contents and upload everything to the destination host wherever you want the SSO server to be located.
* Run the installer.

The SSO server can reside on any host but must be accessible to a SSO client, the end-user, and any servers a provider might need to talk to.  The SSO Server is written in PHP and requires a minimum of PHP 5.3 but preferably the latest version of PHP should be used.  A [CSDB](https://github.com/cubiclesoft/csdb)-compliant database host is required to install the SSO server.  The SSO server is also master/slave replication-aware if you need to scale out the database or already use a replication cluster.

Take your time installing and setting up the SSO server since it will likely be around for a really long time.  During the installation, a compatibility checklist is shown.  It is highly recommended that all tests on the compatibility checklist pass before installing the SSO server.  Some of the items will significantly impact the security and performance of the system if they don't pass.

If you opt to "Use CRON For Cleanup" during the installation, be sure to set up 'cron.php' to run on a schedule.  It may be necessary to change permissions and modify the file a little to get it working.  If the file is modified, rename 'cron.php' to something else so that upgrading the SSO server will be easier to do in the future.

After installation, the server needs to be set up for its first SSO client.  The administrative interface requires a file called 'admin_hook.php' to exist that sets up '$bb_usertoken' before it will function.  The installer creates a basic file but if you get a blank screen then the file may need to be modified (e.g. you are behind a firewall or you rebooted and your IP address changed).

SSO Server Global Configuration
-------------------------------

You can configure the server however you want and the configuration can be changed at any time.  You will likely bounce around between various areas of the administration interface as you set things up.

Start with the "Configure" option under "SSO Server Options" and set the preferred timezone for the administration interface as well as set up any desired global whitelist and blacklist options - the settings here affect all Providers.

There are two semi-advanced options in the global configuration.  There is a way to reset the namespace encryption key and IV that is used to encrypt the namespace cookie and the other option deals with [clock drift](http://en.wikipedia.org/wiki/Clock_drift).  Understanding clock drift will help with deploying a sensible SSO server and client environment for your needs.  The default clock drift is set to five minutes (300 seconds).  This translates to a minimum of a 10 minute session regardless of what the SSO client declares as the session length because the SSO server automatically adjusts the minimum based on clock drift settings.  Clock drift happens because computers can't keep perfect time.  This causes problems that usually aren't noticeable on a single computer but across a network of machines it becomes apparent.  Each SSO client packet is timestamped with the current date and time before encrypting the packet and sending it to the SSO server endpoint.  The SSO server endpoint verifies that the packet timestamp is within acceptable limits to reduce the probability of a [replay attack](http://en.wikipedia.org/wiki/Replay_attack).  This is where clock drift comes into play - the SSO client and server can be on completely different computers whose clocks likely aren't in perfect synchronization.  In addition, the SSO client will attempt to send the same packet up to three times because networks are notoriously unreliable.  The endpoint also applies a random delay to any request.  Networks also have delays in delivery.  A five minute window in either direction is decent, but it does force a minimum session length of ten minutes.  Clock drift can be configured globally or on a per API key basis.  Start with the default and adjust on an as-needed basis keeping in mind that minimum session length is clock drift * 2.

If a user is declared to be a spammer, the system default is to not provide any details about the situation and only outputs the generic message, "This system does not have any active providers.  Either no providers have been configured or your current location has been blocked." That message, while accurate, is entirely unhelpful to the user.  The server configuration option "No Providers Message" allows for additional details to be provided.  Some people use the following basic template:

```html
<div class="sso_main_wrap">
<div class="sso_main_wrap_inner">
@BLOCKDETAILS@

<p>
If there are additional details above, please correct the problem(s) from your end.
Note that even if an IP block is cleared, it will take two weeks for the local cache to clear.
If there are no additional details, please contact the website administrator at:  webmaster @ yourdomain . com
</p>
</div>
</div>
```

The `@BLOCKDETAILS@` token is replaced with information on why the user is declared to be a spammer.  From there the user can take appropriate action to remove the block.  Note that information about an IP address is cached for 14 days (by default).  Even if a block is immediately removed, it will take additional time for the cached IP information to leave the SSO server.

The SSO server whitelist is a common field found in both the global server configuration and each provider configuration.  It consists of one or more IP address patterns that are allowed to access or use the server or provider.  Also, patterns should generally be IPv6 rather than IPv4.

Blacklists come in two flavors:  DNSRBL and GeoIP blacklists.  If an IP address is on a blacklist, it will be unable to access the server or the specific provider.  Generally, an IP address should only be added to a blacklist if it is a spammer IP.  For the global DNSRBL configuration, it is recommended to use at least one option from the following setup:

```
# dnsbl.tornevall.org offers HTTP blacklisting via StopForumSpam.com's database.
# To add a spammer to this list, visit:  http://www.stopforumspam.com/add.php
# For more information, visit:  http://www.stopforumspam.com/usage
# Be sure to donate:  http://www.stopforumspam.com/donate
https://dnsbl.tornevall.org/|dnsbl.tornevall.org|127.0.0.&64

# HTTP:bl is a Honeypot Project DNSRBL.  Registration is required to use the service.
# For more information, visit:  http://www.projecthoneypot.org/httpbl.php
# Be sure to donate:  http://www.projecthoneypot.org/donate.php
http://www.projecthoneypot.org/list_of_ips.php|[YourPassword].@IP@.dnsbl.httpbl.org|127.<60.>9.>0

# dnsbl.sorbs.net offers a limited HTTP DNSRBL that doesn't catch many spammers.
# For more information, visit:  http://dnsbl.sorbs.net/
http://dnsbl.sorbs.net/|dnsbl.sorbs.net|127.0.0.2|127.0.0.7
```

GeoIP blacklisting takes an IP address and maps it to a physical location in the world.  It isn't accurate or always right but it is usually good enough to identify someone's physical location to a city level.  In order to use GeoIP blacklists, an IPv6 compatible database will have to be obtained and uploaded into the 'support' directory (e.g.  'GeoLiteCityv6.dat').

The global configuration also has GeoIP mapping options to take a GeoIP location field and map it to a SSO server field.  Again, this requires a valid IPv6 compatible database to function.

Whitelist and blacklist options are found in both global and provider configurations.  The global options are processed first followed by the specific provider options.

SSO Server Field Setup
----------------------

A field stores a piece of information about a user.  It can contain anything such as first name, last name, e-mail address, etc.  Field data can be stored encrypted or unencrypted in the database.  Ideally, all fields are encrypted, however only unencrypted fields are able to be searched when using the "Find User" option.  So, encrypt as many fields as make sense to encrypt.  You will still be able to edit encrypted field data.

To add a field, select the "Manage Fields" option under the "SSO Server Options" menu and then click "Add Field".  Fill in the form and click "Create".  The field will be added to the list in the field manager.  Fields in the field manager can easily be added, removed, enabled, disabled, sliced, diced, and toasted.  Changes to a field can be made at any time.

Of course, you may be wondering what fields to set up.  Here is a list of common field names:

* email - The user's e-mail address.
* username - The user's username.
* first_name - The user's first name.
* middle_name - The user's middle name or initial.
* last_name - The user's last name.
* gender - The user's gender.
* birthday_year - The user's year of birth.
* birthday_month - The user's birthday month.
* birthday_day - The user's birthday day.
* city - The user's city.
* state_region - The user's state or region.
* country - The user's country.

It is a good idea to separate pieces of user information wherever possible.  Take 'first_name' and 'last_name' for instance.  While 'full_name' could be a field that contains the user's full name, very few providers have support for a full name mapping and most SSO clients won't either.

Keep the number of fields to the minimum required for your needs.  This helps keep RAM and storage requirements to a minimum and the server will run faster with fewer enabled fields.  Also, asking users for more information will tend to keep them away.

Encrypted fields can't be searched on.  The data goes into the database in a serialized and encrypted packet format.  There is no way to search such data.  If the field later is set to unencrypted, the user has to log in before the data becomes unencrypted and will show up in search results in the admin interface.  The "Reset All Sessions" option can force everyone to login again if they are already logged in.

When a field is mapped in a provider, the provider protects the field from being edited in the administrative interface and across the API endpoint.  This happens because the information will be overwritten the next time the user logs in using that provider.  It is up to each provider to provide editing capabilities for protected fields (if possible).

SSO Server Tag Setup
--------------------

A tag is generally an administrative permission but a tag can also change the behavior of an account in some specific way.  Tags are usually added manually to user accounts but they can also be added via automation.

There are three default tags that are set up during installation that can't be deleted or easily changed:  'sso_site_admin', 'sso_admin', and 'sso_locked'.

The 'sso_site_admin' tag is intended to be used for an all-powerful admin account.  A user with this tag can grant and revoke privileges for any user in the system, including themselves via the SSO server administration interface.  As such, this tag is passed along automatically to SSO clients if the user has this tag associated with their account.

The 'sso_admin' tag is intended to be used to give certain users partial access to the SSO server administration interface.  These users have the ability to find users in the system and edit fields that aren't protected by a Provider.

The 'sso_locked' tag is used to lock an account.  Locked accounts stop a user from logging in while retaining their information.  This tag can be used to temporarily disable a user account for a misbehaving user or as a pre-deletion step.

Before a client is set up, at least one provider needs to be set up.

SSO Server Provider:  Generic Login
-----------------------------------

The SSO server comes with a generic login provider with a creative name of Generic Login.  Behind the scenes, this is known as 'sso_login' and is the largest, most flexible, and the most complex provider of all the included SSO server providers.

Installing this provider is easy but there is one critical choice to make:  Do you want users to sign up with a username, e-mail, or both? It depends heavily on your needs as to what gets selected during installation.  However, changing this later is basically impossible and the default setting is recommended.

After installing, configure the Generic Login provider.  Note that the default settings are set to be fairly liberal in terms of website security.  If e-mail address verification is desired, use a subject line like "[websitename] Verify your e-mail address" and a message along the lines of:

```html
<html>
<body>
@USERNAME@,<br />
<br />
In order to verify your new WebsiteName account, please use the link below:<br />
<br />
@VERIFY@<br />
<br />
If clicking the link doesn't work, try copying it and then pasting it into your web browser's address bar.<br />
<br />
If you did not sign up for a WebsiteName account, please ignore this e-mail.<br />
<br />
Your anti-phishing phrase is:<br />
<br />
@ANTIPHISH@
</body>
</html>
```

Replace "WebsiteName" with your website's name.  Also, the above example depends on the anti-phishing module being enabled.

If account recovery via e-mail is desired, use either the same subject line as the e-mail address verification and a message along the lines of:

```html
<html>
<body>
@USERNAME@,<br />
<br />
In order to recover your WebsiteName account, use the link below:<br />
<br />
@VERIFY@<br />
<br />
If clicking the link doesn't work, try copying it and then pasting it into your web browser's address bar.<br />
<br />
If you did not request recovery for a WebsiteName account, please ignore this e-mail.<br />
<br />
Your anti-phishing phrase is:<br />
<br />
@ANTIPHISH@
</body>
</html>
```

The two e-mails are very similar-looking.  These are just simple examples of HTML that might be used.  Obviously, they can be made to look much fancier with a minor bit of effort.  The HTML is automatically transformed into text upon saving and both the HTML and text versions are sent in a multipart e-mail so that users who disable HTML e-mail still see the message.

There are several modules included with the Generic Login provider:

* Anti-Phishing - When enabled, users select an anti-phishing phrase that appears on the login screen and can optionally be included in e-mails.
* Two-Factor Authentication via E-mail - Sends an e-mail containing a two-factor authentication code after the user signs in.
* Two-Factor Authentication via Google Authenticator - Allows the user to use Google Authenticator (or a compatible app) for two-factor authentication.
* Two-Factor Authentication via reverse SMS and a Twilio-compatible service - Allows the user to send a SMS message for two-factor authentication.  Supports Twilio and SignalWire.  This module implements the reverse of traditional SMS 2FA where, instead of receiving a SMS, the user sends a SMS message since doing so [can be much cheaper](https://signalwire.com/pricing/messaging).
* Password Requirements - Adds algorithms that calculate a password's actual strength and requires a minimum strength for all passwords.  Optionally, passwords can be expired after some set amount of time.  Expired passwords require using an account recovery mechanism.
* Rate Limiting - Adds rate limits for the Generic Login provider to stop bots and other forms of automation.
* reCAPTCHA - Adds reCAPTCHA to stop most bots from registering or attempting to login.
* Remember Me - Adds a set of options that lets the user remember their sign in on a specific computer for the amount of time the user configures it for.
* Account Recovery via Free SMS - Users with a service that offers a [free e-mail to SMS gateway](https://github.com/cubiclesoft/email_sms_mms_gateways) can optionally set up their login to be able to recover access to their account via a SMS text message.
* Terms of Service - Adds a checkbox to the sign up process to confirm having read a Terms of Service and/or Privacy Policy statement.

Enabling a module adds more options to the configuration and immediately activates it, if possible.

The Password Requirements module calculates [password strength](http://en.wikipedia.org/wiki/Password_strength) and then requires users to meet a minimum strength for each password.  It can optionally display a set of randomly selected words to the user that they can use to more easily construct a password that meets the minimum strength threshold.  The following thresholds are recommended:

* 18 bits of entropy - The average website.
* 25 bits of entropy - A popular website with a top 25,000 Alexa ranking.
* 30 bits of entropy - A web service with business critical applications (e.g. SAAS).
* 40 bits of entropy - A bank or other financial institution.

At 18 bits of entropy, approximately 99% of all poorly selected passwords are rejected outright and the brute force password cracking tools out there become useless hunks of junk.

Passwords in the Generic Login provider are hashed using a [bcrypt](http://en.wikipedia.org/wiki/Bcrypt)-like hashing mechanism.  Passwords are first salted and then as many rounds as possible are executed according to the minimum rules of the Generic Login configuration.  If more rounds can be executed in the specified time frame, then the password will be that much more secure.  As computer hardware gets faster, the number of rounds gets higher, thus taking the same amount of time to create the hash for new passwords.

Before moving on, most SSO server users won't want the words "Generic Login" to appear but something more familiar such as the website name.  The easiest way to change this while making upgrading easy to do is to create a language pack that maps the string "Generic Login" to whatever is desired.

SSO Server Providers:  Third-Party/Social Media
-----------------------------------------------

A third-party provider relies on a completely remote system to perform account authentication and authorization by redirecting to it.  The SSO server comes with the following third-party providers:

* Facebook - Lets users sign in using their Facebook account.
* Google - Lets users sign in using a Google account.
* LinkedIn - Lets users sign in using their LinkedIn account.

A third-party provider generally requires access keys, tokens, etc. before it will start functioning properly.  For example, the Facebook provider requires setting up a Facebook application at which point Facebook will generate an application ID and application secret that gets put into the provider.  A well-written provider of this nature will include instructions on how to successfully set it up.

SSO Server Providers:  Enterprise
---------------------------------

An enterprise provider relies on a remote system to perform account authentication and authorization by directly connecting to it.  The SSO server comes with the following enterprise providers:

* LDAP - Lets users sign in using LDAP or Active Directory credentials via a LDAP server.
* Remote Login - Lets users sign in using a remote login mechanism (e.g. behind a corporate firewall).

An enterprise provider generally requires firewall settings to be configured to allow the SSO server to have access to the target host and the provider has to be configured correctly before it will connect to the target host.  Also, setting up such providers can require a level of technical expertise that may result in hair-pulling.  For example, the LDAP provider requires setting up a rather finicky server URL and Distinguished Name.  If the setup isn't perfect, cryptic error messages will show up when someone attempts to sign in.

The Remote Login provider is documented in depth elsewhere.  It is an incredibly useful tool for signing into an application that relies on a public SSO server instance outside a firewall using a private server behind a firewall (e.g. via VPN) without opening any ports on the corporate firewall.  The private server is responsible for authenticating the user (e.g. LDAP or SSPI), pushing the user's information to the public SSO server, and then redirecting the browser back to the public SSO server, which completes the sign in process.  In addition, multiple "remotes" can be set up with the Remote Login provider.

SSO Server API Key Setup
------------------------

After setting up the server configuration and providers as desired, it is time to prepare for the first SSO client installation.  An API key and secret are required for proper operation.  For security reasons, every SSO client should have its own API key.

To create a new API key, select "Manage API Keys" under the "SSO Server Options" menu and click "Add API Key".  Fill in the namespace, the reason for this key and a URL where an end-user will access the SSO system - not where the SSO client will reside but an obvious location that uses the client.  Click "Create" and then set up an IP address whitelist, field mappings, and tag mappings.  Setting up an IP address whitelist of what web server IP addresses can use the API key is highly recommended, but the system is generally secure even with the default settings.

Now for a brief word on namespaces:  Namespaces allow for seamless sign in sharing between API keys.  If a user signs into one application (e.g. the SSO client uses API key #1) and then makes their way over to another application (e.g. the SSO client for the second application uses API key #2), the user would normally have to sign in again.  With namespaces, if two API keys share the same namespace, the SSO server will see that there is an active session in the namespace already, automatically activate the same account, and attempt to automate the validation phase of the sign in process.  In theory, the sign in for the second application will be completely transparent, behind-the-scenes browser redirects.  If the session has expired, the SSO client sent the special 'invalid_permissions' message, or the user's IP address has changed, the user will have to sign in again.

To avoid confusing users, all SSO client installations using API keys in the same namespace should have similar cookie timeout settings.  If the cookie timeout settings for a client are not the same, users may have to sign in again at weird times and may perceive the system as flaky.

A good strategy is to use the following namespaces:

* [blank string] - For access to end-user application(s).  Most API keys will likely use this.
* admin - For administrative interface access.

If you wish to isolate each API key into its own namespace, you can use the API key's numerical ID.  The API key ID is guaranteed to be unique and generated upon creation of the API key.

SSO Client Installation
-----------------------

Installing the SSO client is fairly straightforward:

* Download or 'git pull' the latest version of the SSO client.
* Extract the contents and upload everything to the destination host where your application is located, preferably in a subdirectory for easy upgrading.
* Run the installer.

During installation, the installer will ask for an endpoint URL, an API key, and an API secret.

All three required bits of information can be obtained by editing an API key in the SSO server.  Copy and paste the information from the SSO server into the installer.  Be sure to test the settings before installing to make sure that everything works properly.

Ideally, there should be one SSO client installation for every single application so that only absolutely required information is passed to each client.  However, each time a new client is encountered, the user will have to log in again, which could get to be rather annoying when using several different systems.  Convenience will unfortunately win out over security.  A good, balanced approach is to install two clients:  One for regular end-user activities and one for access to administrative interfaces.

A fair bit of warning:  The SSO client has a lot of options and some of them are not obvious as to how to set them.  It really depends heavily on what you want to do with the software that will utilize the client.  Of particular note is the SSO client cookie path, which is almost guaranteed to be wrong for your needs - it should point at the root of your application, not the root of the SSO client.  But since software isn't magical, it has to be pointed at the right location manually.

Testing the SSO Client
----------------------

Once the SSO client has been successfully installed, it is time to try it out and make sure everything is in working order.  Create a file called 'test_oo.php' wherever the application will reside and copy and paste the following code:

```php
<?php
	// These first four lines should be executed as soon as possible.
	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";

	$sso_client = new SSO_Client;
	$sso_client->Init(array("sso_impersonate", "sso_remote_id"));

	// The rest of this code can be executed whenever.
	$extra = array();
	if (isset($_REQUEST["sso_impersonate"]) && is_string($_REQUEST["sso_impersonate"]))  $extra["sso_impersonate"] = $_REQUEST["sso_impersonate"];
	else if (isset($_REQUEST["sso_remote_id"]) && is_string($_REQUEST["sso_remote_id"]))
	{
		$extra["sso_provider"] = "sso_remote";
		$extra["sso_remote_id"] = $_REQUEST["sso_remote_id"];
	}
	if (!$sso_client->LoggedIn())  $sso_client->Login("", "You must login to use this system.", $extra);

	// Fields names from the SSO server API key mapping.
	$fields = array(
		"username",
		"firstname",
	);

	// Reads user information from the browser cookie, session,
	// and/or the SSO server into a more convenient user object.
	$user = $sso_client->GetMappedUserInfo($fields);

	// Test permissions for the user.
	if (!$sso_client->IsSiteAdmin())
	{
		$sso_client->Login("", ($sso_client->FromSSOServer() ? "insufficient_permissions" : "You must login with an account with sufficient permissions to use this system."), $extra);
	}

	// Get the internal token for use with XSRF defenses.
	// Not used in this example.
	$bb_usertoken = $sso_client->GetSecretToken();

	// A simple example.
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout")
	{
		$sso_client->Logout();

		$url = $sso_client->GetFullRequestURLBase();

		header("Location: " . $url);
		exit();
	}
	else
	{
		echo "User ID:  " . $user->ID . "<br />";
		echo "Username:  " . htmlspecialchars($user->username) . "<br />";
		echo "First Name:  " . htmlspecialchars($user->firstname) . "<br />";
		echo "<br />";
		echo "<a href=\"test_oo.php\">Test local access</a><br />";
		echo "<a href=\"test_oo.php?action=logout\">Logout</a>";
	}
?>
```

For those who would rather use plain functions instead of the `SSO_Client` class, name the file 'test_flat.php' and copy and paste the following code:

```php
<?php
	// These first four lines should be executed as soon as possible.
	$sso_removekeys = array("sso_impersonate", "sso_remote_id");
	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";
	require_once SSO_CLIENT_ROOT_PATH . "/functions.php";

	// The rest of this code can be executed whenever.
	$extra = array();
	if (isset($_REQUEST["sso_impersonate"]) && is_string($_REQUEST["sso_impersonate"]))  $extra["sso_impersonate"] = $_REQUEST["sso_impersonate"];
	else if (isset($_REQUEST["sso_remote_id"]) && is_string($_REQUEST["sso_remote_id"]))
	{
		$extra["sso_provider"] = "sso_remote";
		$extra["sso_remote_id"] = $_REQUEST["sso_remote_id"];
	}
	if (!SSO_LoggedIn())  SSO_Login("", "You must login to use this system.", $extra);

	// Fields names from the SSO server API key mapping.
	$fields = array(
		"username",
		"firstname",
	);

	// Reads user information from the browser cookie, session,
	// and/or the SSO server into a more convenient user object.
	$user = SSO_GetMappedUserInfo($fields);

	// Test permissions for the user.
	if (!SSO_IsSiteAdmin())
	{
		SSO_Login("", (SSO_FromSSOServer() ? "insufficient_permissions" : "You must login with an account with sufficient permissions to use this system."), $extra);
	}

	// Get the internal token for use with XSRF defenses.
	// Not used in this example.
	$bb_usertoken = SSO_GetSecretToken();

	// A simple example.
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout")
	{
		SSO_Logout();

		$url = SSO_GetFullRequestURLBase();

		header("Location: " . $url);
		exit();
	}
	else
	{
		echo "User ID:  " . $user["ID"] . "<br />";
		echo "Username:  " . htmlspecialchars($user["username"]) . "<br />";
		echo "First Name:  " . htmlspecialchars($user["firstname"]) . "<br />";
		echo "<br />";
		echo "<a href=\"test_flat.php\">Test local access</a><br />";
		echo "<a href=\"test_flat.php?action=logout\">Logout</a>";
	}
?>
```

Change the highlighted line with 'config.php' to point at the 'config.php' in the SSO client directory.  The first few lines should be executed as soon as is possible in an application so the SSO client has the best chance of operating transparently to the application (especially true when implementing this as a third-party software plugin).

This example uses all sorts of features of the SSO client.  Specifically, extensive use of cookie caching to minimize the number of requests to the SSO server for user information.  At any rate, go ahead and visit the 'test_oo.php' or 'test_flat.php' page.

If everything is successful and at least one provider is configured correctly, you will be rewarded with an ugly-looking sign in screen.

Build a Custom Header and Footer
--------------------------------

The ugly sign in screen can be remedied with a little HTML and CSS.  Fortunately, in the 'examples' directory included with the SSO server is a very nice-looking, modern CSS layout.  You don't have to use it, but it will save some time.  The only part you actually have to build is a header and footer.

![Example SSO server login screen](https://user-images.githubusercontent.com/1432111/39400265-0eab58a4-4ae2-11e8-88a8-b712df213468.png)

Simply create 'header.php' and 'footer.php' in the SSO server's main directory and write a standard header and footer.  Then link to the 'examples/main.css' file in the header as one does with a CSS file and...voilà!  The result is a really, really ridiculously good-looking interface.  Here is an example 'header.php':

```php
<?php
	// A simple header for the SSO server.

	if (!defined("SSO_FILE"))  exit();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>SSO Login</title>
<link rel="stylesheet" href="<?php echo htmlspecialchars(SSO_ROOT_URL); ?>/examples/main.css" type="text/css" media="all" />
</head>
<body>
```

```php
<?php
	// A simple footer for the SSO server.

	if (!defined("SSO_FILE"))  exit();
?>
</body>
</html>
```

The header and footer files are loaded very early on and stored into $sso_header and $sso_footer variables in the SSO server frontend to accommodate early error messages that might arise (e.g. database connection failures).  This makes major customizations a bit difficult.  There are two functions that can be created which can be placed either in 'header.php' or 'footer.php' that allow $sso_header and $sso_footer to be completely or partially replaced with something else after the user's session information is loaded up but before any actions are processed:

* FrontendHook_PreHeaderMessage() - Called before `$sso_header` is modified with 'initmsg' data.
* FrontendHook_PostHeaderMessage() - Called after 'initmsg' data has been appended to `$sso_header`.

The recommended approach is to use a single header and footer so users aren't confused as to which sign in they need to use and which login information to provide.

Create a Site Admin Account
---------------------------

The next step is to create an account that will have access to the SSO server admin.  Right now, the admin is only accessible because 'admin_hook.php' is granting access but it still needs to be secured.  This is the next step of that whole process.

Creating a valid account is easy:  Just go through the motions of successfully signing in and returning to the test page.  Be sure to use a secure password.  If you are using the example test page, you will be kicked back to the login screen with the message, "Your account has insufficient permissions to access that resource."

Switch over to the SSO server admin and use "Find User" to locate the account.  Add the 'sso_site_admin' tag to the account.  Switch back to the login screen and sign in again.  Logging in should now succeed.

Understanding Field Flow
------------------------

Understanding how user account field information flows from and through the SSO server to the SSO client is critical to making sure each application gets the information it needs to operate correctly.

![SSO Server Field Flowchart](https://user-images.githubusercontent.com/1432111/39400280-5a1c5496-4ae2-11e8-806b-7b1ea246db46.png)

Providers supply some field information.  When a SSO server provider supplies a field, it also protects that information from being modified.  For instance, the Generic Login provider passes an e-mail address and username onto the SSO server and also protects those fields from modification at the user account level.  This is because the next time the user would log in, the provider will overwrite the value for each field in the user account.  It is up to the provider to make it possible to change the associated fields that it protects if it is possible to do so.

The other source of field information is user-supplied.  There are two ways to set user-supplied information:

* From the SSO server.  Upon signing in but before returning to the SSO client, an 'index_hook.php' file can be constructed to ask users to fill in missing information that the client requires.
* From a SSO client.  If the API key supports "Read/Write" permissions on a specific field, the SSO client can remotely push changes to the user account.

These two sets of information form the entire user account.  This information is transformed by the SSO server endpoint when a client connects with a specific API key.  The API key determines what fields and tags flow through from the server to the client and what the target field names will be.  Each API key restricts what fields and tags flow through by the mapping for each field and tag.  If there is no mapping, the field/tag will not be sent.  If there is a mapping, the field/tag will be sent.

The application can retrieve a field's value with the SSO_GetField() function and if the user has a specific tag with the SSO_HasTag() function.

If you logged into the system with the test page using the Generic Login provider, there will likely be no information for the Username and First Name fields.  This happens because there is either no information or the mapping for the API key is incorrect.  Feel free to experiment a bit.

Using Versioned Accounts
------------------------

One of the nice features of the SSO server is that each user account has a version number associated with it.  Every account starts at version zero (0) and it is entirely up to you to decide how you want to deal with version numbers.  Checking the version of an account and doing something based on the current value is done by creating an 'index_hook.php' file.  Here is a non-working example of a possible 'index_hook.php' implementation:

```php
<?php
	// An example index hook for the SSO server.

	if (!defined("SSO_FILE"))  exit();

	$versions = array(
		"legal" => 4,
		"marketing_ads" => 6
	);
	$latestversion = max($versions);
	if ($sso_userrow->version == 0)
	{
		// Awesome.
		if (SSO_FrontendFieldValue("submit") !== false)
		{
			// Process form submission.
//			$sso_user_info["first_name"] = "Colonel";
//			$sso_user_info["last_name"] = "Sanders";

			// Save changes.
			SSO_SetUserVersion($latestversion);

			header("Location: " . $sso_target_url);
			exit();
		}

		echo "New account!  You rock!";
	}
	else if ($sso_userrow->version < $versions["legal"])
	{
		// Legal sent this down the other day.
		echo "New Terms of Service and Privacy Policy - BORING!";
	}
	else if ($sso_userrow->version < $versions["marketing_ads"])
	{
		// Because we want our users to give us their money.
		echo "Latest promotion/advertisement/feature!  Slobbery hugs and kisses!";
	}
	else
	{
		// Automate some fields here.
		$changed = false;

		// ...

		// Save changes.
		if ($changed)
		{
			SSO_SetUserVersion($latestversion);

			header("Location: " . $sso_target_url);
			exit();
		}

		if (count($sso_missingfields))
		{
			// Have the user fill in the remaining missing fields.
			if (SSO_FrontendFieldValue("submit") !== false)
			{
				// Process form submission.

				// Save changes.
				SSO_SetUserVersion($latestversion);

				header("Location: " . $sso_target_url);
				exit();
			}

			// Display form here.
			echo "Need some additional information to continue.  Sell your soul (or privacy) here.";
		}
		else
		{
			SSO_ValidateUser();

			SSO_DisplayError("Error:  Unable to validate the new session.  Most likely cause:  Internal error.");
		}
	}
?>
```

The code starts off with checking to see if this is a brand new account.  This is where you might offer the user the option to sign up for a newsletter or agree to your terms of service or whatever else you might dream of.  When they submit the form (not shown), it sets the user version to the latest version, redirects back to itself, and then moves along to the latest version tasks.

If the user account is not on the latest version, the code checks to see what the user needs to see or do first.  In this case, 'legal' issues come first, then 'marketing and advertising' initiatives.  The user only sees one or the other but not both during the same login attempt.

Finally, the code checks to see which fields are being sent to the client that are empty and aren't protected by the associated provider.  First, it tries to automatically fill in the missing fields.  If it fails to do that, the user gets to fill the fields in.  Once everything looks okay, SSO_ValidateUser() is called, which finalizes the session and returns to the SSO client.

And now for a working implementation used on [barebonescms.com](http://barebonescms.com/):

```php
<?php
	// Barebones CMS website index hook.

	if (!defined("SSO_FILE"))  exit();

	foreach ($sso_missingfields as $field)  $sso_user_info[$field] = "";

	$knownfields = array(
		"email" => "E-mail Address",
		"username" => "Username",
		"first_name" => "First Name",
		"last_name" => "Last Name",
		"mybb_usertitle" => "",
		"mybb_gid" => ""
	);

	foreach ($sso_apikey_info["field_map"] as $key => $info)
	{
		if (!isset($knownfields[$key]))
		{
			echo htmlspecialchars(BB_Translate("Unknown required user field '%s'.  Someone broke this system.  Oops!", $key));
			exit();
		}
	}

	// Add miscellaneous protected fields.
	$protectedfields = array(
		"mybb_usertitle", "mybb_gid"
	);
	foreach ($protectedfields as $key)  $sso_protectedfields[$key] = true;

	// Check for editable fields.
	$found = false;
	foreach ($knownfields as $key => $disp)
	{
		if ((!isset($sso_protectedfields[$key]) || !$sso_protectedfields[$key]) && ((isset($sso_user_info[$key]) && $sso_user_info[$key] != "") || isset($sso_apikey_info["field_map"][$key])))
		{
			if (!$sso_automate || !isset($sso_user_info[$key]) || $sso_user_info[$key] == "")  $found = true;

			break;
		}
	}

	// Skip the verification if there are no editable fields.
	if (!$found)
	{
		SSO_ValidateUser();

		SSO_DisplayError("Error:  Unable to validate the new session.  Most likely cause:  Internal error.");
	}

	$messages = array();

	// Process form submission.
	if (SSO_FrontendFieldValue("submit") !== false)
	{
		foreach ($knownfields as $key => $disp)
		{
			if ((!isset($sso_protectedfields[$key]) || !$sso_protectedfields[$key]) && ((isset($sso_user_info[$key]) && $sso_user_info[$key] != "") || isset($sso_apikey_info["field_map"][$key])))
			{
				$sso_user_info[$key] = SSO_FrontendFieldValue($key, "");
				if ($sso_user_info[$key] == "")  $messages[] = BB_Translate("Fill in '%s'.", BB_Translate($disp));
			}
		}

		if (!count($messages))
		{
			// Save changes.
			SSO_SetUserVersion(0);

			// Proceed.
			SSO_ValidateUser();

			SSO_DisplayError("Error:  Unable to validate the new session.  Most likely cause:  Internal error.");
		}
	}

	echo $sso_header;

	SSO_OutputHeartbeat();

?>
<div class="sso_main_wrap">
<div class="sso_main_wrap_inner">
<?php
	if (count($messages))
	{
?>
	<div class="sso_main_messages_wrap">
		<div class="sso_main_messages_header"><?php echo htmlspecialchars(BB_Translate(count($messages) == 1 ? "Please correct the following problem:" : "Please correct the following problems:")); ?></div>
		<div class="sso_main_messages">
<?php
		foreach ($messages as $message)
		{
?>
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($message); ?></div>
<?php
		}
?>
		</div>
	</div>
<?php
	}
?>
	<div class="sso_main_form_wrap sso_login_signup_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Verify Information")); ?></div>
		<form class="sso_main_form" name="sso_main_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url); ?>">

<?php
	foreach ($knownfields as $key => $disp)
	{
		if ($disp == "")  continue;

		if (isset($sso_protectedfields[$key]) && $sso_protectedfields[$key])
		{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate($disp)); ?></div>
				<div class="sso_main_formdata"><div class="sso_main_static"><?php echo htmlspecialchars($sso_user_info[$key]); ?></div></div>
			</div>
<?php
		}
		else if ((isset($sso_user_info[$key]) && $sso_user_info[$key] != "") || isset($sso_apikey_info["field_map"][$key]))
		{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate($disp)); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField($key); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue($key, $sso_user_info[$key])); ?>" /></div>
			</div>
<?php
		}
	}
?>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Continue")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php

	echo $sso_footer;
?>
```

This code looks very different because it shows the actual form construction and the approach is entirely different from the previous approach.  Here versioned accounts are not being used but the interception of the login avoids having to create a separate user profile page elsewhere.  This approach allows the user to evaluate the stored information they are allowed to modify and make changes to it before continuing to the application.  As a result, partial GDPR compliance can be achieved with minimal effort.

One significant benefit of implementing 'index_hook.php' is that bots will have to get through it before continuing.  Custom-built code breaks most bots.  Therefore, doing virtually anything here will throw a monkey wrench into some bot operator's life.

Securing the Admin Interface
----------------------------

The last key step to most installations is to secure the SSO server administration interface.  The original access script just allowed a specific IP address.  If this is the desired behavior, there is some authentication risk but that can be mitigated by deleting 'admin_hook.php' and then no one can access the admin interface until 'admin_hook.php' is re-uploaded to the server.

The simpler method is to install a second SSO client into the SSO server admin directory.  When setting up the API key configuration, map the 'sso_admin' tag to itself.  Then use the following for the 'admin_hook.php' script:

```php
<?php
	if (!defined("SSO_FILE"))  exit();

	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";

	$sso_client = new SSO_Client;
	$sso_client->Init(array("sso_impersonate", "sso_remote_id"));

	if (!$sso_client->LoggedIn())  $sso_client->Login("", "You must login to use this system.");

	// Send the browser cookies.
	$sso_client->SaveUserInfo();

	// Test permissions for the user.
	if (!$sso_client->IsSiteAdmin() && !$sso_client->HasTag("sso_admin"))  $sso_client->Login("", "insufficient_permissions");

	// Get the internal token for use with XSRF defenses.
	$bb_usertoken = $sso_client->GetSecretToken();

	$sso_site_admin = $sso_client->IsSiteAdmin();
	$sso_user_id = $sso_client->GetUserID();

	// Add a menu option to logout.
	function AdminHook_MenuOpts()
	{
		global $sso_menuopts, $sso_client;

		$sso_menuopts["SSO Server Options"]["Logout"] = BB_GetRequestURLBase() . "?action=logout&sec_t=" . BB_CreateSecurityToken("logout");

		if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "logout")
		{
			$sso_client->Logout();

			header("Location: " . BB_GetFullRequestURLBase());
			exit();
		}
	}
?>
```

This code is fairly simple and only allows users with 'sso_site_admin' and 'sso_admin' tags to access the system.

Miscellaneous Customization
---------------------------

When more than one provider is available, the user gets a selection screen.  At this point, the user starts seeing some of the internal names such as "Generic Login" show up but this is not necessarily desired behavior.  Customization of display strings is accomplished through the multilingual support options.  Using IANA language codes (e.g. 'en-us'), a language pack can be built to transform nearly all display strings in order to display whatever output you want users to see.  Using the language pack approach allows for easier future upgrades of the SSO server and client.

The multilingual support in the SSO server is more robust than displaying alternate strings.  The SSO client also supports passing an IANA language code along to the SSO server for a seamless transition across hosts and complete translation support of almost every string (including those in a header and footer).  The configuration files and installers can also select default languages for various scenarios.

When multiple providers are active and the CSS file is used from the 'examples' directory, the default icons may not be what you want to use.  There is a sample Photoshop PSD file included with the Generic Login icon pattern.  That should make it easier to construct alternate or additional icons to use with the various SSO server providers or your own provider.

There are several callbacks in the SSO server that hook scripts can utilize.  An example of this is found in the "Securing the Admin Interface" section above.

The SSO server endpoint supports Custom API keys.  These are similar to Remote API keys but won't do anything until you write code to support this key type but they allow for complete customization of the server via the same secure communication mechanisms of other API keys.  For example, they could be used to set and retrieve fields and/or tags that are only available to a private API for server-to-server communications.
