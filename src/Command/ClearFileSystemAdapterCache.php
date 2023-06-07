<?php

namespace App\Command;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'clear-fs-adapter-cache',
    description: 'Clears cache of the FileSystemAdapter',
)]
class ClearFileSystemAdapterCache extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = new FilesystemAdapter();

        return $cache->clear();
    }
}
