# Coyote WordPress plugin

The Coyote WordPress plugin allows for images in posts and pages to have their text description ("alt attribute") be managed by a connected [Coyote description service](https://www.coyote.pics). This means that all the image descriptions on your WordPress website can be managed remotely in Coyote.

Once the plugin is set up to connect to a valid Coyote API account, image meta-data stored in your WordPress installation may potentially be stored by Coyote. The plugin may also query the Coyote service to provide necessary functionality. Please familiarise yourself with the [Coyote Terms of Service](https://www.coyote.pics/terms-of-service/).

## How it works

When the plugin is installed and its filters are enabled, it replaces image alt attributes in your posts with those retrieved from the Coyote service. When Coyote users change image descriptions, those descriptions are approved, and remote updates are enabled, those filters will also reflect these updates. The plugin effectively functions as a content filter; it does not permanently change the content of your WordPress posts or pages.

## Installation

[Download the plugin](https//wordpress.org/plugins/coyote/) from the WordPress plugin registry and install it, or use the built-in WordPress plugin manager.

## Configuration

Navigate to `Settings > Coyote` to configure the plugin. The following settings are available:

### General plugin settings

* Enable filters - this ensures that post and page content gets filtered through the plugin and alt text gets injected.
* Enable remote updates - this allows for Coyote to update alt text for an image once a description gets approved or an approved description changes.
* Stand-alone mode - this disables any outgoing querying against the Coyote API.
* Processor endpoint - this is the address of the processor service used when processing existing posts.

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

Usually when first installing the plugin, you might want to do a first indexation of all the images in your WordPress installation and create associated resources on the Coyote API side. This tool uses a remote processor (See "Processor endpoint" in the general settings) to process your existing posts in batches. It can take a few minutes depending on the size of your WordPress installation.

## What If
### I want to go back to manually managing original alt attributes

You can deactivate the plugin in your WordPress plugin settings page. Alternatively, on the Coyote plugin settings page, you can disable the "Filter posts through Coyote" option.

### I want to stop using the Coyote service but retain the alt attributes provided so far

Don't deactivate the plugin: disable remote updates instead (see [the plugin configuration](#configuration)).
