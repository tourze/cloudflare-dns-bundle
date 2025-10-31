<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Exception\DnsDomainException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 域名同步服务
 * 封装了域名同步的核心业务逻辑，从Command层抽取复杂性
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
readonly class DomainSynchronizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DnsDomainRepository $dnsDomainRepository,
        private DnsDomainService $dnsDomainService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 同步单个域名的详细信息
     */
    public function syncDomainInfo(DnsDomain $domain, ?SymfonyStyle $io = null): bool
    {
        try {
            $result = $this->dnsDomainService->listDomains($domain);
            $resultData = $this->extractDomainListData($result);

            $matchingDomainData = $this->findMatchingDomainData($resultData, $domain->getName());
            if (null === $matchingDomainData) {
                return $this->handleDomainNotFound($domain, $io);
            }

            $domainName = $matchingDomainData['name'] ?? '';
            $detailData = $this->fetchDomainDetails($domain, is_string($domainName) ? $domainName : '');
            $this->updateDomainDetails($domain, $detailData, $io);

            $this->persistDomainChanges($domain);
            $io?->text("域名 {$domain->getName()} 同步完成");

            return true;
        } catch (\Throwable $e) {
            return $this->handleSyncException($domain, $e, $io);
        }
    }

    /**
     * 提取域名列表数据
     * @param array<string, mixed> $result
     * @return array<int, array<string, mixed>>
     */
    private function extractDomainListData(array $result): array
    {
        $resultData = $result['result'] ?? [];
        if (!is_array($resultData)) {
            return [];
        }

        return $this->normalizeArrayItems($resultData);
    }

    /**
     * 标准化数组项，确保所有key都是字符串
     * @param array<mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeArrayItems(array $items): array
    {
        $typedData = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $stringKeyedItem = [];
            foreach ($item as $key => $value) {
                $stringKeyedItem[is_string($key) ? $key : (string) $key] = $value;
            }
            $typedData[] = $stringKeyedItem;
        }

        return $typedData;
    }

    /**
     * 查找匹配的域名数据
     * @param array<int, array<string, mixed>> $resultData
     * @return array<string, mixed>|null
     */
    private function findMatchingDomainData(array $resultData, ?string $targetDomainName): ?array
    {
        if (null === $targetDomainName) {
            return null;
        }

        foreach ($resultData as $item) {
            $itemName = $item['name'] ?? null;
            if (is_string($itemName) && $itemName === $targetDomainName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * 获取域名详细信息
     * @return array<string, mixed>
     */
    private function fetchDomainDetails(DnsDomain $domain, string $domainName): array
    {
        $detail = $this->dnsDomainService->getDomain($domain, $domainName);
        $detailData = $detail['result'] ?? [];

        if (!is_array($detailData)) {
            return [];
        }

        // Ensure proper string keys for array<string, mixed>
        $stringKeyedDetailData = [];
        foreach ($detailData as $key => $value) {
            $stringKeyedDetailData[is_string($key) ? $key : (string) $key] = $value;
        }

        return $stringKeyedDetailData;
    }

    /**
     * 持久化域名变更
     */
    private function persistDomainChanges(DnsDomain $domain): void
    {
        $this->entityManager->persist($domain);
        $this->entityManager->flush();
    }

    /**
     * 处理域名未找到情况
     */
    private function handleDomainNotFound(DnsDomain $domain, ?SymfonyStyle $io): bool
    {
        $this->logger->warning('未在API返回中找到指定域名', [
            'domain' => $domain->getName(),
        ]);

        $io?->warning("域名 {$domain->getName()} 在CloudFlare账户中未找到");

        return false;
    }

    /**
     * 处理同步异常
     */
    private function handleSyncException(DnsDomain $domain, \Throwable $e, ?SymfonyStyle $io): bool
    {
        $this->logger->error('同步域名信息失败', [
            'domain' => $domain->getName(),
            'exception' => $e,
        ]);

        $io?->error("处理域名 {$domain->getName()} 失败: {$e->getMessage()}");

        return false;
    }

    /**
     * 更新域名详细信息
     *
     * @param array<string, mixed> $detailData
     */
    public function updateDomainDetails(DnsDomain $domain, array $detailData, ?SymfonyStyle $io = null): void
    {
        $this->syncZoneIdAndShowChanges($domain, $detailData, $io);
        $this->updateDomainStatus($domain, $detailData);
        $this->updateDomainAutoRenew($domain, $detailData);
        $this->updateDomainTimeFields($domain, $detailData);
    }

    /**
     * @param array<string, mixed> $detailData
     */
    private function syncZoneIdAndShowChanges(DnsDomain $domain, array $detailData, ?SymfonyStyle $io): void
    {
        $oldZoneId = $domain->getZoneId();
        $zoneId = $this->dnsDomainService->syncZoneId($domain, $detailData);

        $this->logZoneIdChanges($domain, $oldZoneId, $zoneId, $io);
    }

    /**
     * 记录Zone ID变更
     */
    private function logZoneIdChanges(DnsDomain $domain, ?string $oldZoneId, ?string $zoneId, ?SymfonyStyle $io): void
    {
        if (null === $io) {
            return;
        }

        if (null !== $zoneId && $oldZoneId !== $zoneId) {
            $io->text("  更新Zone ID: {$oldZoneId} -> {$zoneId}");
        } elseif (null === $zoneId && null === $oldZoneId) {
            $io->warning("  未能获取域名 {$domain->getName()} 的Zone ID");
        }
    }

    /**
     * 更新域名状态
     *
     * @param array<string, mixed> $detailData
     */
    private function updateDomainStatus(DnsDomain $domain, array $detailData): void
    {
        if (!isset($detailData['status']) || !is_string($detailData['status'])) {
            return;
        }

        $statusEnum = $this->mapStringToStatusEnum($detailData['status']);
        if (null !== $statusEnum) {
            $domain->setStatus($statusEnum);
        }
    }

    /**
     * 更新域名自动续费设置
     *
     * @param array<string, mixed> $detailData
     */
    private function updateDomainAutoRenew(DnsDomain $domain, array $detailData): void
    {
        if (isset($detailData['auto_renew']) && is_bool($detailData['auto_renew'])) {
            $domain->setAutoRenew($detailData['auto_renew']);
        }
    }

    /**
     * 更新域名时间字段
     *
     * @param array<string, mixed> $detailData
     */
    private function updateDomainTimeFields(DnsDomain $domain, array $detailData): void
    {
        $this->updateCreatedTime($domain, $detailData);
        $this->updateExpiresTime($domain, $detailData);
        $this->updateLockedUntilTime($domain, $detailData);
    }

    /**
     * 更新创建时间
     *
     * @param array<string, mixed> $detailData
     */
    private function updateCreatedTime(DnsDomain $domain, array $detailData): void
    {
        if (isset($detailData['created_at']) && is_string($detailData['created_at'])) {
            $domain->setCreateTime(new \DateTimeImmutable($detailData['created_at']));
        }
    }

    /**
     * 更新过期时间
     *
     * @param array<string, mixed> $detailData
     */
    private function updateExpiresTime(DnsDomain $domain, array $detailData): void
    {
        if (isset($detailData['expires_at']) && is_string($detailData['expires_at'])) {
            $domain->setExpiresTime(new \DateTimeImmutable($detailData['expires_at']));
        }
    }

    /**
     * 更新锁定时间
     *
     * @param array<string, mixed> $detailData
     */
    private function updateLockedUntilTime(DnsDomain $domain, array $detailData): void
    {
        if (isset($detailData['locked_until']) && is_string($detailData['locked_until'])) {
            $domain->setLockedUntilTime(new \DateTimeImmutable($detailData['locked_until']));
        }
    }

    private function mapStringToStatusEnum(string $statusString): ?DomainStatus
    {
        foreach (DomainStatus::cases() as $case) {
            if ($case->value === $statusString) {
                return $case;
            }
        }

        return null;
    }

    /**
     * 创建或更新域名
     *
     * @param IamKey               $iamKey     IAM密钥
     * @param array<string, mixed> $domainData API返回的域名数据
     * @param SymfonyStyle|null    $io         用于输出信息的IO接口
     *
     * @return DnsDomain 创建或更新的域名对象
     */
    public function createOrUpdateDomain(IamKey $iamKey, array $domainData, ?SymfonyStyle $io = null): DnsDomain
    {
        $this->validateDomainData($domainData);

        $domain = $this->findOrCreateDomain($iamKey, $domainData, $io);
        $this->updateDomainDetails($domain, $domainData, $io);
        $domain->setValid(true);

        return $domain;
    }

    /**
     * 验证域名数据
     *
     * @param array<string, mixed> $domainData
     */
    private function validateDomainData(array $domainData): void
    {
        if (!isset($domainData['name'])) {
            throw new DnsDomainException('Domain data must contain a "name" field');
        }

        if (!is_string($domainData['name'])) {
            throw new DnsDomainException('Domain name must be a string');
        }
    }

    /**
     * 查找或创建域名
     *
     * @param array<string, mixed> $domainData
     */
    private function findOrCreateDomain(IamKey $iamKey, array $domainData, ?SymfonyStyle $io): DnsDomain
    {
        $existingDomain = $this->dnsDomainRepository->findOneBy([
            'name' => $domainData['name'],
            'iamKey' => $iamKey,
        ]);

        if (null !== $existingDomain) {
            $this->logDomainUpdate($domainData, $io);

            return $existingDomain;
        }

        return $this->createNewDomain($iamKey, $domainData, $io);
    }

    /**
     * 创建新域名
     *
     * @param array<string, mixed> $domainData
     */
    private function createNewDomain(IamKey $iamKey, array $domainData, ?SymfonyStyle $io): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setIamKey($iamKey);

        $domainName = $domainData['name'];
        if (!is_string($domainName)) {
            throw new DnsDomainException('Domain name must be a string');
        }

        $domain->setName($domainName);
        $this->logDomainCreation($domainData, $io);

        return $domain;
    }

    /**
     * 记录域名创建
     *
     * @param array<string, mixed> $domainData
     */
    private function logDomainCreation(array $domainData, ?SymfonyStyle $io): void
    {
        if (null !== $io && is_string($domainData['name'])) {
            $io->text(sprintf('新增域名: %s', $domainData['name']));
        }
    }

    /**
     * 记录域名更新
     *
     * @param array<string, mixed> $domainData
     */
    private function logDomainUpdate(array $domainData, ?SymfonyStyle $io): void
    {
        if (null !== $io && is_string($domainData['name'])) {
            $io->text(sprintf('更新域名: %s', $domainData['name']));
        }
    }

    /**
     * 查找域名
     *
     * @param string|null $specificDomain 指定域名名称
     *
     * @return array<int, DnsDomain> 找到的域名列表
     */
    public function findDomains(?string $specificDomain = null): array
    {
        if (null !== $specificDomain) {
            return $this->dnsDomainRepository->findBy(['name' => $specificDomain]);
        }

        return $this->dnsDomainRepository->findAll();
    }
}
