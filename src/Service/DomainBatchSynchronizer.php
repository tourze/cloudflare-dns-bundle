<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 域名批量同步服务
 * 处理批量域名同步的业务逻辑
 */
class DomainBatchSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $dnsDomainRepository,
        private readonly DnsDomainService $dnsDomainService,
        private readonly DomainSynchronizer $domainSynchronizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 过滤域名列表
     *
     * @param array $domains API返回的所有域名数据
     * @param string|null $specificDomain 指定过滤的域名名称
     * @param SymfonyStyle $io 用于输出信息的IO接口
     * @return array 过滤后的域名数据数组
     */
    public function filterDomains(array $domains, ?string $specificDomain, SymfonyStyle $io): array
    {
        if (empty($domains['result'])) {
            $io->info('没有找到任何域名');
            return [];
        }

        // 如果有指定域名，先过滤
        $domainsToSync = $domains['result'];
        if ($specificDomain !== null) {
            $domainsToSync = array_filter(
                $domains['result'],
                fn($domain) => $domain['name'] === $specificDomain
            );

            if (empty($domainsToSync)) {
                $io->error(sprintf('未找到指定的域名: %s', $specificDomain));
                return [];
            }
        }

        return $domainsToSync;
    }

    /**
     * 显示域名同步预览
     *
     * @param array $domainsToSync 要同步的域名数据
     * @param IamKey $iamKey IAM密钥
     * @param OutputInterface $output 用于输出的接口
     * @param SymfonyStyle $io 用于格式化输出的接口
     * @return array 预览数据，用于显示给用户
     */
    public function showSyncPreview(array $domainsToSync, IamKey $iamKey, OutputInterface $output, SymfonyStyle $io): array
    {
        $syncPreview = [];
        foreach ($domainsToSync as $domain) {
            $existingDomain = $this->dnsDomainRepository->findOneBy([
                'name' => $domain['name'],
                'iamKey' => $iamKey,
            ]);

            $status = $existingDomain !== null ? '更新' : '新增';
            $currentZoneId = $existingDomain !== null ? ($existingDomain->getZoneId() ?: '未设置') : '未设置';
            $newZoneId = $domain['id'] ?? '未知';
            $syncPreview[] = [
                $domain['name'],
                $currentZoneId,
                $newZoneId,
                $domain['status'] ?? '未知',
                $status
            ];
        }

        // 显示同步预览
        $io->section('同步预览');
        $table = new Table($output);
        $table->setHeaders(['域名', '当前Zone ID', '新Zone ID', '状态', '操作']);
        $table->setRows($syncPreview);
        $table->render();

        return $syncPreview;
    }

    /**
     * 确认同步操作
     *
     * @param bool $force 是否强制同步
     * @param bool $dryRun 是否仅验证
     * @param SymfonyStyle $io 用于用户交互的接口
     * @return bool 是否继续同步操作
     */
    public function confirmSync(bool $force, bool $dryRun, SymfonyStyle $io): bool
    {
        // 在非强制模式下，询问用户是否继续
        if (!$force && !$dryRun) {
            $question = new ConfirmationQuestion('确认进行同步操作? (y/n) ', false);

            if ($io->askQuestion($question) === false) {
                $io->info('操作已取消');
                return false;
            }
        }

        // 如果是干运行模式，则直接返回
        if ($dryRun) {
            $io->info('验证完成，干运行模式下未执行实际同步');
            return false;
        }

        return true;
    }

    /**
     * 执行域名批量同步
     *
     * @param array $domainsToSync 要同步的域名数据
     * @param IamKey $iamKey IAM密钥
     * @param SymfonyStyle $io 用于输出信息的IO接口
     * @return array 同步结果统计 [成功数, 失败数, 跳过数]
     */
    public function executeBatchSync(array $domainsToSync, IamKey $iamKey, SymfonyStyle $io): array
    {
        $syncCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($domainsToSync as $domainData) {
            try {
                // 创建或更新域名
                $domain = $this->domainSynchronizer->createOrUpdateDomain($iamKey, $domainData, $io);

                // 保存域名
                $this->entityManager->persist($domain);
                $syncCount++;
            } catch (\Throwable $e) {
                $io->error(sprintf('同步域名 %s 失败: %s', $domainData['name'] ?? 'unknown', $e->getMessage()));
                $this->logger->error('同步域名失败', [
                    'domainName' => $domainData['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        // 提交事务
        $this->entityManager->flush();

        return [$syncCount, $errorCount, $skippedCount];
    }

    /**
     * 获取临时域名对象
     * 用于API调用
     */
    public function createTempDomain(IamKey $iamKey): DnsDomain
    {
        $tempDomain = new DnsDomain();
        $tempDomain->setIamKey($iamKey);
        return $tempDomain;
    }

    /**
     * 获取账户下所有域名
     */
    public function listAllDomains(IamKey $iamKey): array
    {
        $tempDomain = $this->createTempDomain($iamKey);
        return $this->dnsDomainService->listDomains($tempDomain);
    }
}
