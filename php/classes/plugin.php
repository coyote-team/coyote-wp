<?php

use Coyote\Handlers\PostUpdateHandler;

namespace Coyote;

class Plugin {
    private $file;
    private $version;

    private $post_update_handler;

    public function __construct(string $file, string $version) {
        $this->file = $file;
        $this->version = $version;

        $this->post_update_handler = new PostUpdateHandler();

        $this->setup();
    }

    private function setup() {
        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load'));
        add_action('save_post', array($this->post_update_handler, 'run'), 10, 3);
    }

    public function activate() {
        // run sql
        // sweep posts
    }

    public function deactivate() {
        // run sql
    }

    public function load() {
    }
}


