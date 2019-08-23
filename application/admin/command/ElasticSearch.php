<?php
/**
 * Created by PhpStorm.
 * User: cattong
 * Date: 2019-08-14
 * Time: 11:20
 */

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Log;

/**
 * Class ElasticSearch
 * @package app\admin\command
 */
class ElasticSearch extends Command
{
    protected function configure()
    {
        $this->setName('article:es')
            ->addOption('id', null, Option::VALUE_REQUIRED, 'The article to timing publish', 0)
            ->addOption('all', null, Option::VALUE_REQUIRED, 'The article to timing publish', 0)
            ->setDescription('search engine index');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('__article es index start...');
        Log::info('article es index start...');
    }
}