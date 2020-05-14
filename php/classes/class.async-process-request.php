<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.wp-async-request.php');
require_once coyote_plugin_file('classes/class.batch-post-processor.php');

use \WP_Async_Request;
use Coyote\BatchPostProcessor;

class AsyncProcessRequest extends WP_Async_Request {
    protected $action = 'coyote_process_post_async';

    protected function handle() {
        $batch_size = $_POST['batch_size'];

        $processor = new BatchPostProcessor($batch_size);

        if (!$processor->is_finished()) {
            $processor->process_next();
            $this->data(array('batch_size' => $batch_size))->dispatch();
        }
    }
}

