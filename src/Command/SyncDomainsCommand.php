<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Entity\IamKey;
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
    name: self::NAME,
    description: '同步指定IAM Key ID下的所有域名到本地',
)]
class SyncDomainsCommand extends Command
{
    public const NAME = 'cloudflare:sync-domains';

    public function __construct(
        private readonly IamKeyService $iamKeyService,
        private readonly DomainBatchSynchronizer $batchSynchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('iamKeyId', InputArgument::REQUIRED, 'IAM Key ID')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, '只同步指定名称的域名')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '仅验证而不实际同步')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制同步，不进行确认')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $params = $this->extractParams($input);

        $iamKey = $this->validateIamKey($params['iamKeyId'], $io);
        if (null === $iamKey) {
            return Command::FAILURE;
        }

        if (!$this->iamKeyService->validateAccountId($iamKey, $io)) {
            return Command::FAILURE;
        }

        try {
            return $this->performDomainSync($iamKey, $params, $io, $output);
        } catch (\Throwable $e) {
            $io->error(sprintf('同步域名时发生错误: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * @return array{iamKeyId: int|string, specificDomain: string|null, dryRun: bool, force: bool}
     */
    private function extractParams(InputInterface $input): array
    {
        $iamKeyId = $input->getArgument('iamKeyId');
        $specificDomain = $input->getOption('domain');

        return [
            'iamKeyId' => is_int($iamKeyId) ? $iamKeyId : (is_string($iamKeyId) ? $iamKeyId : ''),
            'specificDomain' => is_string($specificDomain) ? $specificDomain : null,
            'dryRun' => (bool) $input->getOption('dry-run'),
            'force' => (bool) $input->getOption('force'),
        ];
    }

    private function validateIamKey(int|string $iamKeyId, SymfonyStyle $io): ?IamKey
    {
        return $this->iamKeyService->findAndValidateKey(is_int($iamKeyId) ? $iamKeyId : (int) $iamKeyId, $io);
    }

    /**
     * @param array{iamKeyId: int|string, specificDomain: string|null, dryRun: bool, force: bool} $params
     */
    private function performDomainSync(IamKey $iamKey, array $params, SymfonyStyle $io, OutputInterface $output): int
    {
        $this->showSyncInfo($iamKey, $params, $io);

        $domains = $this->batchSynchronizer->listAllDomains($iamKey);
        $domainsToSync = $this->batchSynchronizer->filterDomains($domains, $params['specificDomain'], $io);

        if ([] === $domainsToSync) {
            return null !== $params['specificDomain'] ? Command::FAILURE : Command::SUCCESS;
        }

        $this->batchSynchronizer->showSyncPreview($domainsToSync, $iamKey, $output, $io);

        if (!$this->batchSynchronizer->confirmSync($params['force'], $params['dryRun'], $io)) {
            return Command::SUCCESS;
        }

        $this->executeSyncAndShowResults($domainsToSync, $iamKey, $io);

        return Command::SUCCESS;
    }

    /**
     * @param array{iamKeyId: int|string, specificDomain: string|null, dryRun: bool, force: bool} $params
     */
    private function showSyncInfo(IamKey $iamKey, array $params, SymfonyStyle $io): void
    {
        $io->info(sprintf('正在同步 IAM Key [%s] 的域名...', $iamKey->getName()));

        if (null !== $params['specificDomain']) {
            $io->info(sprintf('只同步域名: %s', $params['specificDomain']));
        }

        if (true === $params['dryRun']) {
            $io->warning('以验证模式运行，不会实际保存数据');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $domainsToSync
     */
    private function executeSyncAndShowResults(array $domainsToSync, IamKey $iamKey, SymfonyStyle $io): void
    {
        [$syncCount, $errorCount, $skippedCount] = $this->batchSynchronizer->executeBatchSync($domainsToSync, $iamKey, $io);
        $io->success(sprintf('同步完成，成功: %d，失败: %d，跳过: %d', $syncCount, $errorCount, $skippedCount));
    }
}
