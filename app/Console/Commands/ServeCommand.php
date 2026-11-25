<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;

class ServeCommand extends BaseServeCommand
{
    /**
     * Number of PHP server workers.
     *
     * @var int
     */
    protected $phpServerWorkers = 4;

    /**
     * Get the full server command.
     *
     * @return array
     */
    protected function serverCommand()
    {
        $command = parent::serverCommand();

        array_splice($command, 1, 0, [
            '-d', 'upload_max_filesize=1024M',
            '-d', 'post_max_size=1024M',
            '-d', 'memory_limit=1024M',
            '-d', 'max_execution_time=900',
        ]);

        return $command;
    }
}
