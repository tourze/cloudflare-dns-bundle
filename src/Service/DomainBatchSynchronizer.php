<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 域名批量同步服务
 * 处理批量域名同步的业务逻辑
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
readonly class DomainBatchSynchronizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DnsDomainRepository $dnsDomainRepository,
        private DnsDomainService $dnsDomainService,
        private DomainSynchronizer $domainSynchronizer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 过滤域名列表
     *
     * @param array<string, mixed> $domains        API返回的所有域名数据
     * @param string|null          $specificDomain 指定过滤的域名名称
     * @param SymfonyStyle         $io             用于输出信息的IO接口
     *
     * @return array<int, array<string, mixed>> 过滤后的域名数据数组
     */
    public function filterDomains(array $domains, ?string $specificDomain, SymfonyStyle $io): array
    {
        $resultData = $this->extractResultData($domains, $io);
        if ([] === $resultData) {
            return [];
        }

        $domainsToSync = $this->filterBySpecificDomain($resultData, $specificDomain, $io);
        if (null === $domainsToSync) {
            return [];
        }

        return $this->normalizeDomainsData($domainsToSync);
    }

    /**
     * 提取API响应中的结果数据
     *
     * @param array<string, mixed> $domains
     *
     * @return array<mixed>
     */
    private function extractResultData(array $domains, SymfonyStyle $io): array
    {
        $resultData = $domains['result'] ?? [];
        if (!is_array($resultData) || [] === $resultData) {
            $io->info('没有找到任何域名');

            return [];
        }

        return $resultData;
    }

    /**
     * 根据指定域名过滤
     *
     * @param array<mixed> $resultData
     *
     * @return array<mixed>|null
     */
    private function filterBySpecificDomain(array $resultData, ?string $specificDomain, SymfonyStyle $io): ?array
    {
        if (null === $specificDomain) {
            return $resultData;
        }

        $filteredDomains = array_filter(
            $resultData,
            fn ($domain) => $this->isDomainMatchingName($domain, $specificDomain)
        );

        if ([] === $filteredDomains) {
            $io->error(sprintf('未找到指定的域名: %s', $specificDomain));

            return null;
        }

        return $filteredDomains;
    }

    /**
     * 检查域名是否匹配指定名称
     *
     * @param mixed $domain
     */
    private function isDomainMatchingName($domain, string $specificDomain): bool
    {
        if (!is_array($domain)) {
            return false;
        }

        $domainName = $domain['name'] ?? null;

        return is_string($domainName) && $domainName === $specificDomain;
    }

    /**
     * 标准化域名数据，确保键为字符串类型
     *
     * @param array<mixed> $domainsToSync
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDomainsData(array $domainsToSync): array
    {
        $filteredDomains = [];
        foreach ($domainsToSync as $domain) {
            if (is_array($domain)) {
                $filteredDomains[] = $this->normalizeArrayKeys($domain);
            }
        }

        return $filteredDomains;
    }

    /**
     * 标准化数组键为字符串类型
     *
     * @param array<mixed, mixed> $array
     *
     * @return array<string, mixed>
     */
    private function normalizeArrayKeys(array $array): array
    {
        $stringKeyedArray = [];
        foreach ($array as $key => $value) {
            $stringKeyedArray[is_string($key) ? $key : (string) $key] = $value;
        }

        return $stringKeyedArray;
    }

    /**
     * 显示域名同步预览
     *
     * @param array<int, array<string, mixed>> $domainsToSync 要同步的域名数据
     * @param IamKey                           $iamKey        IAM密钥
     * @param OutputInterface                  $output        用于输出的接口
     * @param SymfonyStyle                     $io            用于格式化输出的接口
     *
     * @return array<int, array<int, string>> 预览数据，用于显示给用户
     */
    public function showSyncPreview(array $domainsToSync, IamKey $iamKey, OutputInterface $output, SymfonyStyle $io): array
    {
        $syncPreview = [];
        foreach ($domainsToSync as $domain) {
            if (!is_array($domain)) {
                continue;
            }

            $domainName = $domain['name'] ?? '';
            if (!is_string($domainName)) {
                continue;
            }

            $existingDomain = $this->dnsDomainRepository->findOneBy([
                'name' => $domainName,
                'iamKey' => $iamKey,
            ]);

            $status = null !== $existingDomain ? '更新' : '新增';
            $currentZoneId = null !== $existingDomain ? ($existingDomain->getZoneId() ?? '未设置') : '未设置';

            $newZoneId = $domain['id'] ?? '未知';
            if (!is_string($newZoneId)) {
                $newZoneId = '未知';
            }

            $domainStatus = $domain['status'] ?? '未知';
            if (!is_string($domainStatus)) {
                $domainStatus = '未知';
            }

            $syncPreview[] = [
                $domainName,
                $currentZoneId,
                $newZoneId,
                $domainStatus,
                $status,
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
     * @param bool         $force  是否强制同步
     * @param bool         $dryRun 是否仅验证
     * @param SymfonyStyle $io     用于用户交互的接口
     *
     * @return bool 是否继续同步操作
     */
    public function confirmSync(bool $force, bool $dryRun, SymfonyStyle $io): bool
    {
        // 在非强制模式下，询问用户是否继续
        if (!$force && !$dryRun) {
            $question = new ConfirmationQuestion('确认进行同步操作? (y/n) ', false);

            if (false === $io->askQuestion($question)) {
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
     * @param array<int, array<string, mixed>> $domainsToSync 要同步的域名数据
     * @param IamKey                           $iamKey        IAM密钥
     * @param SymfonyStyle                     $io            用于输出信息的IO接口
     *
     * @return array{0: int, 1: int, 2: int} 同步结果统计 [成功数, 失败数, 跳过数]
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
                ++$syncCount;
            } catch (\Throwable $e) {
                $domainName = $domainData['name'] ?? 'unknown';
                if (!is_string($domainName)) {
                    $domainName = 'unknown';
                }

                $io->error(sprintf('同步域名 %s 失败: %s', $domainName, $e->getMessage()));
                $this->logger->error('同步域名失败', [
                    'domainName' => $domainName,
                    'error' => $e->getMessage(),
                ]);
                ++$errorCount;
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
     *
     * @return array<string, mixed>
     */
    public function listAllDomains(IamKey $iamKey): array
    {
        $tempDomain = $this->createTempDomain($iamKey);

        return $this->dnsDomainService->listDomains($tempDomain);
    }
}
