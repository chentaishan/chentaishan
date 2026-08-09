<?php
declare (strict_types = 1);

namespace app\job\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Job extends Command
{
    private $task;
    private $second = 5;
    protected function configure()
    {
        // 指令配置
        $this->setName('job')
            ->setDescription('定时任务');
    }

    protected function execute(Input $input, Output $output)
    {
        var_dump(date('Y-m-d H:i:s', time()) . '定时任务开始执行');
        event('JobScheduler');
        var_dump(date('Y-m-d H:i:s', time()) . '定时任务结束执行');
    }

    /**
     * 定时器执行的内容
     * @return false|int
     */
    public function start()
    {
    }
}
