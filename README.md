# Coyote WordPress plugin

The Coyote WordPress plugin allows for images in posts and pages to have their text description ("alt attribute") be managed by a connected [Coyote description service](https://www.coyote.pics).

## How it works

When the plugin is installed and its filters are enabled, it replaces image alt attributes in your posts with those retrieved from a Coyote service.
When Coyote service moderators change image descriptions and remote updates are enabled, those filters will also reflect these updates.

This plugin does *not* modify your stored posts or pages, it is purely a content filter. When the plugin is deactivated, the filter is also deactivated and any pre-existing alt attributes will henceforth be used.

### I want to go back to my original alt attributes.

You can deactivate the plugin in your WordPress plugin settings page.

### I want to stop using the Coyote service but retain the alt attributes provided so far.

Don't deactivate the plugin: disable remote updates instead (see [the plugin configuration](#configuration)).

## Installation

[Download the plugin](http://wordpress.org/plugins/coyote/) from the WordPress plugin registry and install it, or use the built-in WordPress plugin manager.

## Configuration

Navigate to `Settings > Coyote` to configure the plugin. The following settings are available:

### General plugin settings

* Enable filters - this ensures that post and page content gets filtered through the plugin and alt text gets injected.
* Enable remote updates - this allows for Coyote to update alt text for an image once a description gets approved or an approved description changes.
* Processor endpoint - this is the address where the processor service is used when processing existing posts.

### API specific settings

Some of these settings become available once a working `Endpoint` and `Token` configuration has been provided.

* Endpoint - the address of the Coyote API to use.
* Token - your Coyote API token.
* Metum - the Coyote API metum used for alt attributes.
* Organization - the organization to which to send new resources and receive resource updates.

### Tools

* Process existing posts

Usually when first installing the plugin, you might want to do a first indexation of all the images in you WordPress installation and create associated resources on the Coyote API side.
This tool uses a remote processor (See "Processor endpoint" in the general settings) to process your existing posts in batches. It can take a few minutes depending on the size of your WordPress installation.
