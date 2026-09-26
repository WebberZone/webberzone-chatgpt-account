=== WebberZone ChatGPT Account ===
Contributors: webberzone, ajaydsouza
Tags: ai, chatgpt, openai, ai client, connectors
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: ai-provider-for-openai
Stable tag: 1.0.0
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

In the `wzcga_tokens` option, encrypted with a key derived from your site's authentication salts. Changing the salts in wp-config.php disconnects the account. Deactivating keeps the connection; uninstalling removes it.

= The sign-in says device code login is turned off =

Enable it in ChatGPT under Settings → Security, then try again. Workspace (Business, Enterprise, Edu) accounts may need an admin to allow it.

== Changelog ==

= 1.0.0 =
* Initial release.
