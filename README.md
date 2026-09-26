# WebberZone ChatGPT Account

[![License](https://img.shields.io/badge/license-GPL_v2%2B-orange.svg?style=flat-square)](https://opensource.org/licenses/GPL-2.0)
[![Coding Standards](https://img.shields.io/github/actions/workflow/status/WebberZone/webberzone-chatgpt-account/cs.yml?branch=master&label=coding%20standards&style=flat-square)](https://github.com/WebberZone/webberzone-chatgpt-account/actions/workflows/cs.yml)
[![PHP Compatibility](https://img.shields.io/github/actions/workflow/status/WebberZone/webberzone-chatgpt-account/phpcompat.yml?branch=master&label=php%207.4-8.6&style=flat-square)](https://github.com/WebberZone/webberzone-chatgpt-account/actions/workflows/phpcompat.yml)

_Requires:_ WordPress 7.0, PHP 7.4, [AI Provider for OpenAI](https://wordpress.org/plugins/ai-provider-for-openai/)
_License:_ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)

---

> [!WARNING]
> This plugin signs in with the public OAuth client of OpenAI's Codex CLI and calls the undocumented ChatGPT backend that Codex uses. It is not an official OpenAI integration. OpenAI may change or block it at any time, and using a ChatGPT subscription outside OpenAI's own apps may conflict with OpenAI's Terms of Use and put your ChatGPT account at risk. Anyone with admin access to the site can use your ChatGPT plan while you are signed in.

## Overview

_WebberZone ChatGPT Account_ adds a **ChatGPT Account** provider to the WordPress AI Client. You sign in with your ChatGPT account using a one-time device code, and text and image generation run against your ChatGPT plan instead of an OpenAI API key.

- _Sign in from Settings → Connectors:_ the provider replaces core's API-key field with a "Sign in with ChatGPT" card and modal. Settings → ChatGPT Account offers the same flow.
- _Device-code flow:_ the same flow as `codex login --device-auth`. Everything runs in wp-admin; no CLI helper and no public REST endpoint.
- _Text generation:_ streams from `chatgpt.com/backend-api/codex/responses`, reusing the AI Provider for OpenAI's message and response mapping.
- _Image generation:_ GPT Image 2 via `chatgpt.com/backend-api/codex/images/generations`.
- _Tokens:_ encrypted with libsodium using a key derived from the site's auth salt, refreshed automatically under a lock (refresh tokens rotate).
- _No build step:_ hand-written ES module for the Connectors card.

Not supported: embeddings, text-to-speech and image editing.

## Filters

| Filter | Purpose |
| --- | --- |
| `wzcga_codex_client_version` | Codex CLI version reported to the backend; it gates which models are listed. |
| `wzcga_fallback_models` | Text models offered when the live model list can't be fetched. |

## Contributing

- Fork the repository and create your branch from `master`.
- Run `composer test` (phpcs, PHP compatibility, phpstan) before opening a pull request.
- `composer zip` builds the installable zip into `build/`.

## For Users

See [readme.txt](./readme.txt) for installation and usage instructions.

## Changelog

See [releases](https://github.com/WebberZone/webberzone-chatgpt-account/releases).

## License

GPL v2 or later.
