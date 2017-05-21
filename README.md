# Coyote Image Descriptions #
**Contributors:**      The Andy Warhol Museum
**Donate link:**       https://coyote.pics/
**Tags:**
**Requires at least:** 4.4
**Tested up to:**      4.7.2
**Stable tag:**        0.1.1
**License:**           GPLv2
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html

## Description ##

The open-source Coyote software was developed by the Museum of Contemporary Art Chicago to support a distributed workflow for describing images in our web CMS and publishing those descriptions to our public website.

This plugin integrates with Coyote software to provide different descriptions for your images based on their location on your website. To display images with the appropriate description, you have a few different options as a theme author or website content manager. You first define different types of descriptions (called `meta`) in Coyote. Then, for each image, you define a different description for each individual metum. You might have a standard `Alt` metum, for example, in addition to a `Long Description` metum.

As a theme developer, you have a couple of functions and a filter available to you. The filter takes an HTML image tag and a metum name and returns the HTML tag with the appropriate alt attribute.

    Filter usage:
        apply_filters( 'coyote_image_tag', $image_tag, $metum_name );

    Example:
        $img_tag = get_the_post_thumbnail( get_the_ID(), 'thumbnail' );
        echo apply_filters( 'coyote_image_tag', $img_tag, 'Long Description' );

You may also use the `coyote_get_image` function, which uses the `coyote_image_tag` filter under the hood with WordPress' `wp_get_attachment_image` function.

    Function usage:
        coyote_get_image( $image_id_or_object, $metum_name, $image_size );

    Example:
        echo coyote_get_image( $image_id, 'Alt', 'large' );

Finally, you may access the description directly with the `coyote_get_description` function:

    Function usage:
        coyote_get_description( $image_id_or_object, $metum_name );

    Example:
        <?php $description = coyote_get_description( $image_obj, 'Long Description' ); ?>
        <img alt="<?php echo esc_html( $description ); ?>">

There is also a shortcode available for use within the WordPress dashboard. This shortcode wraps either an image ID or an image HTML tag, and inserts the appropriate description:

    [coyote metum="Alt" size="full"]<img src="http://example.com/wp-content/uploads/2017/04/example.jpg" alt="Default Alt Text That Will Be Replaced" width="300" height="200" class="size-medium wp-image-12" />[/coyote]

    [coyote metum="Alt" size="full"]12[/coyote]

### Settings ###

The plugin comes with a few settings which must be configured.

#### User Email & API Key ####
For authentication, you must provide your Coyote user's email address and API Key.

#### Coyote Instance URL ####
This is the root URL of your instance of Coyote.

#### Default Group ID ####
The ID for the Coyote group where images will be registered when uploaded to WordPress.

#### Website ID ####
If your Coyote instance serves more than one website, you can set the website ID.

### Registering Images ###
After installing and activating Coyote, new image uploads will be automatically registered with Coyote. For existing images, you will find a bulk action in the Media Library to register images with Coyote. There is also a column to show which images have and have not been registered with Coyote yet.

## Installation ##

### Manual Installation ###

1. Upload the entire `/coyote-image-descriptions` directory to the `/wp-content/plugins/` directory.
2. Activate Coyote Image Descriptions through the 'Plugins' menu in WordPress.

## Changelog ##

### 0.1.0 ###
* First release
