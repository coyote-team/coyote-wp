<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use \WP_Async_Request;
use Coyote\BatchPostProcessor;

class AsyncProcessRequest extends WP_Async_Request {
    public function __construct($method = 'post') {
        $this->post = ($method == 'post');
        parent::__construct();
    }

    protected $action = 'coyote_process_post_async';

    protected function handle() {
        $batch_size = $this->post ? $_POST['batch_size'] : $_GET['batch_size'];

        $processor = new BatchPostProcessor($batch_size);

        if ($processor->is_active()) {
            $processor->process_next();
            if ($this->post) {
                $this->data(array('batch_size' => $batch_size));
            } else {
                $this->query_args = ['batch_size' => $batch_size];
            }
            $this->dispatch();
        }
    }
}

