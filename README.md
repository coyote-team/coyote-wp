# Coyote WordPress plugin

The Coyote WordPress plugin allows for images in posts and pages to have their text description ("alt attribute") be managed by a connected [Coyote description service](https://www.coyote.pics).

## How it works

When the plugin is installed and its filters are enabled, it replaces image alt attributes in your posts with those retrieved from a Coyote service.
When Coyote service moderators change image descriptions and remote updates are enabled, those filters will also reflect these updates.

While the plugin is mainly a content filter, it does filter public post content as well as content displayed in the post editor.
This may cause posts to retain coyote-managed image descriptions when updating posts.

### I want to go back to manually managing original alt attributes.

You can deactivate the plugin in your WordPress plugin settings page.
Alternatively, on the Coyote plugin settings page, you can disable the "Filter posts through Coyote" option.

### I want to stop using the Coyote service but retain the alt attributes provided so far.

Don't deactivate the plugin: disable remote updates instead (see [the plugin configuration](#configuration)).

## Installation

[Download the plugin](https//wordpress.org/plugins/coyote/) from the WordPress plugin registry and install it, or use the built-in WordPress plugin manager.

## Configuration

Navigate to `Settings > Coyote` to configure the plugin. The following settings are available:

### General plugin settings

* Enable filters - this ensures that post and page content gets filtered through the plugin and alt text gets injected.
* Enable remote updates - this allows for Coyote to update alt text for an image once a description gets approved or an approved description changes.
* Stand-alone mode - this disables any outgoing querying against the Coyote API.
* Processor endpoint - this is the address where the processor service is used when processing existing posts.

### Stand-alone mode

While stand-alone mode can be set manually, it is also automatically engaged when the plugin repeatedly fails to communicate with the Coyote API.
When this happens, administrators can manually disable stand-alone mode and attempt to remediate the failing configuration. The plugin will attempt to
recover from stand-alone mode mode by querying the API at a 5-minute interval.

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
