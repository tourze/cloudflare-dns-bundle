<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Service\DomainSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: self::NAME, description: '同步域名信息（状态、过期时间、Zone ID等）')]
class SyncDomainInfoCommand extends Command
{
    public const NAME = 'cloudflare:sync-domain-info';

    public function __construct(
        private readonly DomainSynchronizer $domainSynchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, '只同步指定名称的域名');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificDomain = $input->getOption('domain');

        // 查找指定域名或所有域名
        $domains = $this->domainSynchronizer->findDomains($specificDomain);

        if (empty($domains)) {
            if ($specificDomain !== null) {
                $io->error(sprintf('未找到指定的域名: %s', $specificDomain));
                return Command::FAILURE;
            } else {
                $io->info('没有找到任何域名');
                return Command::SUCCESS;
            }
        }

        if ($specificDomain !== null) {
            $io->info(sprintf('只同步域名: %s', $specificDomain));
        } else {
            $io->info(sprintf('同步所有域名，共 %d 个', count($domains)));
        }

        $successCount = 0;
        $errorCount = 0;

        $io->section('开始同步域名信息');

        foreach ($domains as $domain) {
            $io->text("处理域名：{$domain->getName()}");

            // 同步域名信息
            $success = $this->domainSynchronizer->syncDomainInfo($domain, $io);

            if ($success) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        if ($successCount > 0) {
            $io->success("同步完成，成功: {$successCount}，失败: {$errorCount}");
        } else {
            $io->warning("没有同步任何域名，失败: {$errorCount}");
        }

        return Command::SUCCESS;
    }
}
