<?php

namespace Coyote;

use \WP_Async_Request;
use Coyote\BatchRestoreProcessor;

class AsyncRestoreRequest extends WP_Async_Request {
    protected $action = 'coyote_restore_post_async';

    protected function handle() {
        $batch_size = $_POST['batch_size'];

        $processor = new BatchRestoreProcessor($batch_size);

        if ($processor->is_active()) {
            $processor->process_next();
            $this->data(array('batch_size' => $batch_size))->dispatch();
        }
    }
}

