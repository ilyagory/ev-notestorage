<?php

use Phalcon\Cli\Task;

class CleanTask extends Task
{
    public function mainAction()
    {
        $dt = gmdate('Y-m-d H:i');
        $this->db->delete('store', "till <= '{$dt}'");
    }
}