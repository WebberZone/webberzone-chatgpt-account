---
title: WebberZone ChatGPT Account
description: Use your ChatGPT subscription for text and image generation in the WordPress AI Client. Sign in with a device code, no OpenAI API key needed.
permalink: /
---

<div class="hero">
  <div class="eyebrow">Free &middot; Open Source &middot; No API Key</div>
  <h1>Use your <em>ChatGPT</em> plan in the WordPress AI Client</h1>
  <p class="lead">WebberZone ChatGPT Account adds a <strong>ChatGPT Account</strong> provider to the WordPress AI Client. Sign in with your ChatGPT account from Settings&nbsp;→&nbsp;Connectors, and text and image generation run against your ChatGPT plan instead of a separately billed OpenAI API key.</p>
  <div class="hero-ctas">
    <a href="#installation" class="btn-primary">Installation</a>
    <a href="https://github.com/WebberZone/webberzone-chatgpt-account/releases/latest" target="_blank" class="btn-outline">Download Latest Release</a>
    <a href="https://github.com/WebberZone/webberzone-chatgpt-account" target="_blank" class="btn-outline">View on GitHub</a>
  </div>
</div>

<div class="home-section">
  <div class="eyebrow">Overview</div>
  <h2 class="section-title" style="margin-bottom:8px;">Sign in once, use it everywhere</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">The provider plugs into the AI Client that ships with WordPress 7.0, so any feature built on it, including the WordPress AI plugin, can use your ChatGPT plan. It builds on the official AI Provider for OpenAI plugin for request and response handling.</p>

  <div class="feature-grid">
    <div class="feature-card">
      <h3>Device-code sign-in</h3>
      <p>Click "Sign in with ChatGPT" on the Connectors screen, enter the one-time code at OpenAI, and you're connected. No API key, no command-line helper, no public endpoint. Tokens are stored encrypted and refreshed automatically.</p>
    </div>
    <div class="feature-card">
      <h3>Text generation</h3>
      <p>The GPT models available to your plan, with chat history, structured JSON output and function calling.</p>
    </div>
    <div class="feature-card">
      <h3>Image generation</h3>
      <p>GPT Image 2, the same image model Codex uses with a ChatGPT sign-in.</p>
    </div>
  </div>

  <img class="screenshot" src="{{ '/site-assets/img/screenshot-connector.png' | relative_url }}" alt="The ChatGPT Account card on the WordPress Settings → Connectors screen, showing it connected with a Disconnect button" width="652" height="117">
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Read this first</div>
  <h2 class="section-title" style="margin-bottom:8px;">How it works, and the risk</h2>
  <div class="callout">
    <h3>Not an official OpenAI integration</h3>
    <p>This plugin signs in with the public OAuth client of OpenAI's Codex CLI and calls the same undocumented ChatGPT backend that Codex uses with a ChatGPT login.</p>
    <ul>
      <li>OpenAI may change or block this at any time.</li>
      <li>Using a ChatGPT subscription outside OpenAI's own apps may conflict with OpenAI's Terms of Use and could put your ChatGPT account at risk.</li>
      <li>Anyone with administrator access to your site, or any code running on it, can use your ChatGPT plan while you are signed in. Use it on sites you control.</li>
    </ul>
  </div>
</div>

<div class="home-section" id="installation" style="padding-top:0;">
  <div class="eyebrow">Get started</div>
  <h2 class="section-title" style="margin-bottom:8px;">Installation</h2>

  <ol class="step-list">
    <li>
      <h3>Install AI Provider for OpenAI</h3>
      <p>Install and activate the <a href="https://wordpress.org/plugins/ai-provider-for-openai/" target="_blank">AI Provider for OpenAI</a> plugin from WordPress.org.</p>
    </li>
    <li>
      <h3>Install this plugin</h3>
      <p>Download the <a href="https://github.com/WebberZone/webberzone-chatgpt-account/releases/latest" target="_blank">latest release</a>, upload it under Plugins → Add New → Upload Plugin, and activate it.</p>
    </li>
    <li>
      <h3>Allow device code login</h3>
      <p>In ChatGPT, open <a href="https://chatgpt.com/#settings/Security" target="_blank">Settings → Security</a> and enable device code login for Codex.</p>
    </li>
    <li>
      <h3>Sign in</h3>
      <p>Go to <strong>Settings → Connectors</strong>, click <strong>Sign in with ChatGPT</strong> on the ChatGPT Account card, and follow the prompts. If the AI plugin shows a connector approval notice, approve ChatGPT Account for the plugins that should use it.</p>
    </li>
  </ol>
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Limits</div>
  <h2 class="section-title" style="margin-bottom:8px;">What it doesn't do</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">Embeddings and text-to-speech aren't available through a ChatGPT sign-in; use the AI Provider for OpenAI with an API key for those. Image editing isn't supported yet, and sampling options such as temperature are accepted but ignored, because the ChatGPT backend rejects them.</p>
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Requirements</div>
  <h2 class="section-title" style="margin-bottom:8px;">What you need</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">WordPress 7.0+, PHP 7.4+, the AI Provider for OpenAI plugin, and a ChatGPT account. Also see <a href="https://webberzone.github.io/webberzone-grok-account/">WebberZone Grok Account</a> for SuperGrok and X Premium, and the <a href="https://github.com/WebberZone/webberzone-chatgpt-account/releases" target="_blank">releases</a> page for the changelog.</p>
</div>
