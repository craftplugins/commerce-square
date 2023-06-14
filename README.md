<h1 align="center">
  <img src="src/icon.svg" alt="Icon" width="128">
  <div>Square for Craft Commerce</div>
</h1>

This plugin provides a [Square](https://square.com) integration for [Craft Commerce](https://craftcms.com/commerce).

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Square for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# Go to the project directory
cd /path/to/craft-project

# Tell Composer to load the plugin
composer require craftplugins/square

# Tell Craft to install the plugin
./craft install/plugin square
```

## Setup

To add the Square payment gateway, go to Commerce → System Settings → Gateways, create a new gateway, and set the gateway type to “Square”.

## Webhooks

The plugin currently only supports the `refund.updated` event type.

Setting up webhooks support requires configuration within the [Square Developer Dashboard](https://developer.squareup.com).

1. Go to the [Square Applications Dashboard](https://developer.squareup.com/apps)
1. Go to your application, or create one
1. Go to Webhooks
1. Add a webhook using the URL in your configured Square gateway (Commerce → System Settings → Gateways)
