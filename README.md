Single Sign On (SSO) Server
===========================

Do you need a PHP login system that rocks?  Well, you found it.

This is Barebones SSO Server.  An awesome, scalable, secure, flexible login system.

![Example SSO server login screen](https://user-images.githubusercontent.com/1432111/39400265-0eab58a4-4ae2-11e8-88a8-b712df213468.png)

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/) [![Discord](https://img.shields.io/discord/777282089980526602?label=chat&logo=discord)](https://cubiclesoft.com/product-support/github/)

Features
--------

* Cross-domain and cross-server capable.  The SSO server can reside on its own domain and host.
* Massively scalable architecture.  Scale out to as many boxes/virtuals as you have available.
* Resilient architecture.  Authenticated users can continue to work even if the server becomes unavailable.
* Resource friendly.  Small memory footprint.
* Enables partial to complete compliance with various bodies of rules and laws including HIPAA, GDPR, PCI.  Work in progress to achieve complete compliance.
* Integrates with a variety of backend databases via [CSDB](https://github.com/cubiclesoft/csdb).
* And much, much more.  See the [full feature list](https://github.com/cubiclesoft/sso-server/blob/master/docs/all-features.md).
* Also has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

SSO Clients
-----------

* [PHP](https://github.com/cubiclesoft/sso-client-php)
* [ASP.NET (C#)](https://github.com/cubiclesoft/sso-client-aspnet)
* [Go (Golang)](https://github.com/gbl08ma/ssoclient)

Getting Started
---------------

The fastest way to get started without reading a lot of documentation is to download/'git pull' the server from this repository and a SSO client from the list above and then follow along with the four part video tutorial series:

[![SSO server/client tutorial series](https://user-images.githubusercontent.com/1432111/39399682-1ac2d3de-4ad7-11e8-8ba7-6f1bf284e0c0.png)](https://www.youtube.com/watch?v=xjPp_YVGttw&list=PLIvucSFZRDjgiSfsm707zn-bqKd64Eikb)

And use the [installation documentation](https://github.com/cubiclesoft/sso-server/blob/master/docs/install.md) as necessary.

According to users of this software, it takes about 3 hours to get a functional SSO server/client setup for the first time.  Building an equivalent system from scratch would take approximately six months for a team of several people, have less flexibility, and probably have multiple security vulnerabilities.

Related Projects
----------------

* [Native app framework/API](https://github.com/cubiclesoft/sso-native-apps)
* [Native app demos](http://barebonescms.com/sso_native_app_demos.zip) - Precompiled versions of the above
* [Disqus provider](https://github.com/khachin/sso-disqus-provider)
* [MyBB plugin](http://barebonescms.com/MyBB_SSOClient-2.5.zip) - Direct download

More Information
----------------

* [The PHP SSO client](https://github.com/cubiclesoft/sso-server/blob/master/docs/php-sso-client.md) - Official documentation for the the PHP SSO client.
* [Upgrading](https://github.com/cubiclesoft/sso-server/blob/master/docs/upgrade.md) - Important information regarding upgrades.
* [Integrating SSO clients with third-party software](https://github.com/cubiclesoft/sso-server/blob/master/docs/integrating-with-third-party-software.md) - Instructions for integrating with forums, CMS products, etc.  Dealing with any software that comes with its own login system.
* [Import existing user accounts](https://github.com/cubiclesoft/sso-server/blob/master/docs/import-existing-user-accounts.md) - Instructions for migrating from another product or a homegrown login system.
* [Enabling user impersonation](https://github.com/cubiclesoft/sso-server/blob/master/docs/user-impersonation.md) - For managing hopeless users who regularly forget their sign in information and require constant password resets.
* [Remote Login Provider documentation](https://github.com/cubiclesoft/sso-server/blob/master/docs/remote-login-provider-setup.md) - Set up "remote" API keys to allow trusted hosts with their own login system and users (e.g. Active Directory/LDAP), to sign in.
* [Creating a SSO server provider](https://github.com/cubiclesoft/sso-server/blob/master/docs/creating-providers.md) - The high-level interface for developing a new provider.
* [Creating a Generic Login module](https://github.com/cubiclesoft/sso-server/blob/master/docs/creating-generic-login-modules.md) - Modules extend the Generic Login provider to allow it to do more.
* [Porting the SSO client](https://github.com/cubiclesoft/sso-server/blob/master/docs/porting-the-sso-client.md) - Instructions on porting the official PHP client to your preferred programming/scripting language.
* [Endpoint API](https://github.com/cubiclesoft/sso-server/blob/master/docs/endpoint-api.md) - The SSO server endpoint API.
* [Using custom API keys](https://github.com/cubiclesoft/sso-server/blob/master/docs/using-custom-api-keys.md) - Here be dragons.  The not recommended last resort workaround for dealing with encountered SSO server endpoint limitations.
* [Reserved global variables](https://github.com/cubiclesoft/sso-server/blob/master/docs/reserved-global-variables.md) - Global variables defined by the SSO server and some clients.  Useful information for provider and module developers.
* [SSO server global functions](https://github.com/cubiclesoft/sso-server/blob/master/docs/server-global-functions.md) - Global functions defined by the SSO server.  Useful information for provider and module developers.
