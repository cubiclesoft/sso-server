SSO Server/Client Features
==========================

The following is an extensive but not exhaustive list of features for both the SSO server, specific providers that have features worth mentioning (e.g. Generic Login), and the official PHP client.

SSO Server Features
-------------------

* Cross-domain and cross-server capable.  The SSO server can reside on its own domain and host.
* Massively scalable architecture.  The server understands master-slave database replication and generally delays execution of change queries until the end of a request to minimize queries against a master database.  It is built to easily scale out to as many boxes as you have available.
* Resilient architecture.  The server can go offline or become unavailable and SSO client authenticated users can continue to work without negatively affecting the integrity of this system.
* Resource friendly.  Each frontend user (someone signing in) uses an average footprint of 4MB RAM per connection in the out-of-the-box install.  The endpoint uses an average of 1MB RAM.  The server includes tips on how to keep the system running lean and mean under high-performance scenarios.
* Easy to use administrative interface.  Point and click to set up and manage fields, tags, providers, API keys, and user accounts.
* Enables partial to complete compliance with various rules and laws including PCI, HIPAA, GDPR.  Work in progress to achieve complete compliance.
* Integrates with a variety of backend databases via [CSDB](https://github.com/cubiclesoft/csdb).  MySQL, Maria DB, PostgreSQL, etc.
* A 'cron' interface is available for scheduled, automated database cleanup.
* Set up your own branded header and footer.  The examples include a stylesheet with a modern, clean look.
* Versioned accounts.  Display special messages to users such as new Terms of Service, a new product or newsletter or other marketing messages, and/or have the user fill in missing information after authenticating but before returning to the SSO client.  Doubles as an anti-bot measure.
* Anti-bot dynamic form field support.  Form fields are randomly named based on the randomly generated session ID.  Since the order of most fields is controlled by the admin interface, this becomes a bot operator's nightmare.
* Encrypted data storage of private data.  Protects against successful hacking attempts that only dump the database.
* Multiple encryption ciphers and optional [dual encryption mode](http://cubicspot.blogspot.com/2013/02/extending-block-size-of-any-symmetric.html) support.
* Set up and use tags as a permissions system or for special account flags for any purpose.
* Field setup and field mapping architecture allow for quickly managing user account fields.
* Simple API key setup and usage.  Easily map server fields to expected client fields.  API keys can be revoked or renewed in the event of a security breach.
* API key namespaces allow an active sign in to be shared across applications.
* User impersonation support.  One-click sign in.  Disabled by default but straightforward to set up if needed.
* Comes with several sign in providers:  Generic Login, Facebook, Google, LinkedIn, LDAP (Active Directory), and Remote Login.
* The Remote Login provider allows for signing in using a trusted host behind a firewall.  For example, sign in with LDAP or Active Directory via VPN and push the user's information to the SSO Server via a native SSO Client call.
* Supports simple third-party software integration via an OAuth2 shim.
* Carefully crafted defenses to deal with [CSRF/XSRF attacks](http://en.wikipedia.org/wiki/Cross-site_request_forgery), [timing attacks](http://en.wikipedia.org/wiki/Timing_attack), [session fixation attacks](http://en.wikipedia.org/wiki/Session_fixation), etc.
* HTTP [DNSRBL](http://en.wikipedia.org/wiki/DNSBL) IP address banning support.
* Geolocation IP address banning and automatic location mapping support (requires uploading an extra 15MB+ database).
* Trusted upstream proxy support.
* IPv4 and IPv6 filtering support.
* Multilingual support.
* A simple, easy-to-use installer.

Generic Login Provider Features
-------------------------------

* AJAX live checking.
* Strong 'bcrypt'-style password hashing.
* E-mail verification.
* Account recovery via e-mail and [SMS via e-mail](https://github.com/cubiclesoft/email_sms_mms_gateways).
* [Two-factor authentication](http://en.wikipedia.org/wiki/Two-factor_authentication) (2FA).  Works with [Google Authenticator](https://support.google.com/accounts/answer/1066447?hl=en) (Android and iOS), [Microsoft Authenticator](http://go.microsoft.com/fwlink/?LinkId=279710), [WinAuth](https://winauth.com/), and e-mail are supported 2FA options.
* Password expiration.
* Minimum required password strength.  Backed by a dictionary against weak password selection.
* reCAPTCHA support.
* Remember me support.
* Anti-phishing string support.
* Rate limiting.
* Blacklisting.
* Progressive enhancement.

SSO Client Features
-------------------

* Average memory footprint.  About 1MB RAM per connection.
* Classes and functions are carefully named to avoid naming conflicts with third-party software.  Makes integrating with third-party software a breeze.
* When authentication is required prior to executing some task (e.g. posting a comment), the SSO client encrypts and sends the current request data ($_GET, $_POST, etc.) to the SSO server for later retrieval and will resume exactly where it left off in most cases (e.g. the comment is posted).  File uploads are lost during this procedure.
* Encrypts communications over the network (even HTTP).
* Cookies are encrypted.
* Communicates with the server on a schedule set by the client.  Allows for significantly reduced network overhead without affecting system integrity.
* Supports both encrypted cookie (default 50 bytes max per key-value pair) and optional local database storage (virtually unlimited).
* Simple enough to port to other scripting and programming languages.  Currently available for:  PHP, ASP.NET (C#), and Go (Golang).
* A simple, easy-to-use installer.
