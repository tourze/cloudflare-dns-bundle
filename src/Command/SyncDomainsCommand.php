<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\IamKeyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cloudflare:sync-domains',
    description: '同步指定IAM Key ID下的所有域名到本地',
)]
class SyncDomainsCommand extends Command
{
    public function __construct(
        private readonly IamKeyService $iamKeyService,
        private readonly DomainBatchSynchronizer $batchSynchronizer,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('iamKeyId', InputArgument::REQUIRED, 'IAM Key ID')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, '只同步指定名称的域名')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '仅验证而不实际同步')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制同步，不进行确认');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $iamKeyId = $input->getArgument('iamKeyId');
        $specificDomain = $input->getOption('domain');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        // 查找并验证IAM Key
        $iamKey = $this->iamKeyService->findAndValidateKey($iamKeyId, $io);
        if (!$iamKey) {
            return Command::FAILURE;
        }

        // 验证IAM Key中是否设置了Account ID
        if (!$this->iamKeyService->validateAccountId($iamKey, $io)) {
            return Command::FAILURE;
        }

        try {
            $io->info(sprintf('正在同步 IAM Key [%s] 的域名...', $iamKey->getName()));
            if ($specificDomain) {
                $io->info(sprintf('只同步域名: %s', $specificDomain));
            }

            if ($dryRun) {
                $io->warning('以验证模式运行，不会实际保存数据');
            }

            // 获取账户下所有域名
            $domains = $this->batchSynchronizer->listAllDomains($iamKey);

            // 过滤域名
            $domainsToSync = $this->batchSynchronizer->filterDomains($domains, $specificDomain, $io);
            if (empty($domainsToSync)) {
                return $specificDomain ? Command::FAILURE : Command::SUCCESS;
            }

            // 显示同步预览
            $this->batchSynchronizer->showSyncPreview($domainsToSync, $iamKey, $output, $io);

            // 确认同步操作
            if (!$this->batchSynchronizer->confirmSync($force, $dryRun, $io)) {
                return Command::SUCCESS;
            }

            // 执行批量同步
            [$syncCount, $errorCount, $skippedCount] = $this->batchSynchronizer->executeBatchSync($domainsToSync, $iamKey, $io);

            $io->success(sprintf('同步完成，成功: %d，失败: %d，跳过: %d', $syncCount, $errorCount, $skippedCount));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('同步域名时发生错误: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
