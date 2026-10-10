=== WebberZone ChatGPT Account ===
Contributors: webberzone, ajaydsouza
Tags: ai, chatgpt, openai, ai client, connectors
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: ai-provider-for-openai
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use your ChatGPT subscription for text and image generation in the WordPress AI Client, instead of an OpenAI API key.

== Description ==

WebberZone ChatGPT Account adds a "ChatGPT Account" provider to the WordPress AI Client. Instead of pasting an OpenAI API key, you sign in with your ChatGPT account and usage counts against your ChatGPT plan.

It sits alongside the AI Provider for OpenAI plugin, which it requires and reuses for request and response handling. Keep that plugin for anything that still needs an API key.

= Features =

* Sign in from Settings → Connectors using a one-time device code. No API key, no command-line helper, no public REST endpoint.
* Text generation with the GPT models available to your plan, including chat history, structured JSON output and function calling.
* Image generation with GPT Image 2.
* Tokens are stored encrypted and refreshed automatically.

= Not supported =

* Embeddings and text-to-speech. Use the AI Provider for OpenAI with an API key.
* Image editing (sending an image to modify).
* Sampling options such as temperature and top_p are accepted but ignored, as the ChatGPT backend rejects them.

= Important: how this works, and the risk =

This plugin signs in with the public OAuth client of OpenAI's Codex CLI and calls the same undocumented ChatGPT backend that Codex uses with a ChatGPT login. It is not an official OpenAI integration.

* OpenAI may change or block this at any time.
* Using a ChatGPT subscription outside OpenAI's own apps may conflict with OpenAI's Terms of Use, and could put your ChatGPT account at risk. Use it at your own discretion.
* Anyone with administrator access to your site, or any code running on it, can use your ChatGPT plan while you are signed in. Use it on sites you control.

== Installation ==

1. Install and activate the AI Provider for OpenAI plugin.
2. Upload this plugin to `/wp-content/plugins/webberzone-chatgpt-account` and activate it.
3. In ChatGPT, open Settings → Security and enable device code login for Codex.
4. In WordPress, go to Settings → Connectors, click "Sign in with ChatGPT" on the ChatGPT Account card, and follow the prompts.
5. If the AI plugin shows a connector approval notice, approve ChatGPT Account for the plugins that should use it.

== Frequently Asked Questions ==

= Where are my ChatGPT tokens stored? =

In the `wzcga_tokens` option, encrypted with a key derived from your site's authentication salts. Changing the salts in wp-config.php disconnects the account. Define `AUTH_KEY` and `AUTH_SALT` in wp-config.php; if they are missing, WordPress stores generated salts in the database and the plugin warns you on Settings → Connectors. Deactivating keeps the connection; uninstalling removes it.

= The sign-in says device code login is turned off =

Enable it in ChatGPT under Settings → Security, then try again. Workspace (Business, Enterprise, Edu) accounts may need an admin to allow it.

== Changelog ==

= 1.0.4 =

Release date: 10 October 2026

**Added**

* `wzcga_min_timeout` filter for the minimum request timeout.

**Changed**

* Text models keep the caller's request timeout, so a plugin can fall back to another provider sooner. Image requests and requests without a timeout, including the WordPress AI Client's 30-second default, still get 180 seconds.

**Fixed**

* Every text model was rejected by ChatGPT with HTTP 400 because an outdated Codex client version hid the current models. The fallback model is now GPT-5.5.

= 1.0.3 =

Release date: 9 October 2026

**Added**

* Settings → Connectors warning when the authentication salts are stored in the database.

**Security**

* Encrypted the pending sign-in code stored while waiting for approval.

**Fixed**

* A token refresh rejected with HTTP 400 for a reason other than an expired or revoked session disconnected the account.
* Disconnecting caused a fatal error when the WordPress AI Client was unavailable.
* Uninstalling left pending sign-in data in the database.

= 1.0.2 =

Release date: 3 October 2026

**Changed**

* Removed the `Domain Path` header, which pointed to a missing `languages` folder.

= 1.0.1 =

Release date: 3 October 2026

**Changed**

* Minified the Connectors script, retaining the readable version when `SCRIPT_DEBUG` is enabled.

**Fixed**

* Temporary network and server errors ended sign-in instead of retrying.
* Canceled or replaced sign-ins could reconnect the account when pending responses arrived.
* Concurrent token refreshes could time out unnecessarily.
* A request rejected with HTTP 401 could refresh a token another request had already refreshed.
* The Copy button failed when clipboard access was unavailable.

= 1.0.0 =

Release date: 26 September 2026

**Added**

* Initial release.

= Earlier versions =

For the changelog of earlier versions, please refer to the [releases page on GitHub](https://github.com/WebberZone/webberzone-chatgpt-account/releases).

== Upgrade Notice ==

= 1.0.4 =
Fixes ChatGPT rejecting every text model with HTTP 400, and lets plugins set a shorter request timeout to fall back to another provider. Update recommended.

= 1.0.3 =
Security release. Encrypts the pending sign-in code, keeps accounts connected after temporary token errors and fixes a fatal error when disconnecting without the AI Client. Update recommended.
