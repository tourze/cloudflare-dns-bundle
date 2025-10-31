<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * DNS记录同步处理器
 *
 * 负责处理DNS记录的创建和更新逻辑
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
class RecordSyncProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理远程记录
     *
     * @param array<string, mixed>                                            $remoteRecord
     * @param array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>} $localRecordMaps
     * @param array<string, int>                                              $counters
     *
     * @return array<string, int>
     */
    public function processRemoteRecord(DnsDomain $domain, array $remoteRecord, array $localRecordMaps, array $counters): array
    {
        $recordData = $this->extractRemoteRecordData($remoteRecord, $domain);
        $existingRecord = $this->findExistingRecord($recordData, $localRecordMaps);

        if (null !== $existingRecord) {
            return $this->updateExistingRecord($existingRecord, $remoteRecord, $counters);
        }

        return $this->createNewRecord($domain, $remoteRecord, $recordData['recordName'], $counters);
    }

    /**
     * 提取远程记录数据
     *
     * @param array<string, mixed> $remoteRecord
     * @return array{recordType: string, recordName: string, recordId: string, key: string}
     */
    private function extractRemoteRecordData(array $remoteRecord, DnsDomain $domain): array
    {
        $recordType = $remoteRecord['type'] ?? null;
        $rawName = $remoteRecord['name'] ?? '';
        $recordName = $this->normalizeRecordName(is_string($rawName) ? $rawName : '', $domain);
        $recordId = $remoteRecord['id'] ?? '';

        $safeRecordType = is_string($recordType) ? $recordType : '';
        $safeRecordId = is_string($recordId) ? $recordId : '';
        $key = $safeRecordType . '_' . $recordName;

        return [
            'recordType' => $safeRecordType,
            'recordName' => $recordName,
            'recordId' => $safeRecordId,
            'key' => $key,
        ];
    }

    /**
     * 查找现有记录
     *
     * @param array{recordType: string, recordName: string, recordId: string, key: string} $recordData
     * @param array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>} $localRecordMaps
     */
    private function findExistingRecord(array $recordData, array $localRecordMaps): ?DnsRecord
    {
        [$localRecordMap, $localRecordByCloudflareId] = $localRecordMaps;

        if (isset($localRecordMap[$recordData['key']])) {
            return $localRecordMap[$recordData['key']];
        }

        if ('' !== $recordData['recordId'] && isset($localRecordByCloudflareId[$recordData['recordId']])) {
            return $localRecordByCloudflareId[$recordData['recordId']];
        }

        return null;
    }

    /**
     * 标准化记录名称
     */
    private function normalizeRecordName(string $recordName, DnsDomain $domain): string
    {
        $domainSuffix = '.' . $domain->getName();
        if (str_ends_with($recordName, $domainSuffix)) {
            $recordName = substr($recordName, 0, -strlen($domainSuffix));
        }

        if ($recordName === $domain->getName()) {
            $recordName = '@';
        }

        return $recordName;
    }

    /**
     * 更新现有记录
     *
     * @param array<string, mixed> $remoteRecord
     * @param array<string, int> $counters
     * @return array<string, int>
     */
    private function updateExistingRecord(DnsRecord $localRecord, array $remoteRecord, array $counters): array
    {
        $changes = $this->detectRecordChanges($localRecord, $remoteRecord);

        if ([] === $changes) {
            ++$counters['skip'];

            return $counters;
        }

        $this->updateRecordFromRemote($localRecord, $changes);
        $this->logRecordUpdate($localRecord, $remoteRecord);

        ++$counters['update'];

        return $counters;
    }

    /**
     * 检测记录变更
     *
     * @param array<string, mixed> $remoteRecord
     * @return array<string, mixed>
     */
    private function detectRecordChanges(DnsRecord $localRecord, array $remoteRecord): array
    {
        $changes = [];

        $changes = array_merge($changes, $this->checkRecordIdChange($localRecord, $remoteRecord));
        $changes = array_merge($changes, $this->checkContentChange($localRecord, $remoteRecord));
        $changes = array_merge($changes, $this->checkTtlChange($localRecord, $remoteRecord));

        return array_merge($changes, $this->checkProxyChange($localRecord, $remoteRecord));
    }

    /**
     * 检查记录ID变更
     *
     * @param array<string, mixed> $remoteRecord
     * @return array<string, mixed>
     */
    private function checkRecordIdChange(DnsRecord $localRecord, array $remoteRecord): array
    {
        $recordId = $remoteRecord['id'] ?? '';
        if ((null === $localRecord->getRecordId() || '' === $localRecord->getRecordId()) && '' !== $recordId) {
            return ['recordId' => $recordId];
        }

        return [];
    }

    /**
     * 检查内容变更
     *
     * @param array<string, mixed> $remoteRecord
     * @return array<string, mixed>
     */
    private function checkContentChange(DnsRecord $localRecord, array $remoteRecord): array
    {
        $recordContent = $remoteRecord['content'] ?? '';
        if ($localRecord->getContent() !== $recordContent) {
            return ['content' => $recordContent];
        }

        return [];
    }

    /**
     * 检查TTL变更
     *
     * @param array<string, mixed> $remoteRecord
     * @return array<string, mixed>
     */
    private function checkTtlChange(DnsRecord $localRecord, array $remoteRecord): array
    {
        if (isset($remoteRecord['ttl']) && $localRecord->getTtl() !== $remoteRecord['ttl']) {
            return ['ttl' => $remoteRecord['ttl']];
        }

        return [];
    }

    /**
     * 检查代理变更
     *
     * @param array<string, mixed> $remoteRecord
     * @return array<string, mixed>
     */
    private function checkProxyChange(DnsRecord $localRecord, array $remoteRecord): array
    {
        if (isset($remoteRecord['proxied']) && $localRecord->isProxy() !== $remoteRecord['proxied']) {
            return ['proxy' => $remoteRecord['proxied']];
        }

        return [];
    }

    /**
     * 从远程更新记录
     *
     * @param array<string, mixed> $changes
     */
    private function updateRecordFromRemote(DnsRecord $localRecord, array $changes): void
    {
        $this->applyRecordChanges($localRecord, $changes);
        $this->markRecordAsSynced($localRecord);
        $this->entityManager->persist($localRecord);
    }

    /**
     * 应用记录变更
     *
     * @param array<string, mixed> $changes
     */
    private function applyRecordChanges(DnsRecord $localRecord, array $changes): void
    {
        foreach ($changes as $field => $value) {
            match ($field) {
                'recordId' => $localRecord->setRecordId(is_string($value) ? $value : null),
                'content' => is_string($value) ? $localRecord->setContent($value) : null,
                'ttl' => is_int($value) ? $localRecord->setTtl($value) : null,
                'proxy' => is_bool($value) ? $localRecord->setProxy($value) : null,
                default => null,
            };
        }
    }

    /**
     * 标记记录为已同步
     */
    private function markRecordAsSynced(DnsRecord $record): void
    {
        $record->setSynced(true);
        $record->setLastSyncedTime(new \DateTimeImmutable());
    }

    /**
     * 记录更新日志
     *
     * @param array<string, mixed> $remoteRecord
     */
    private function logRecordUpdate(DnsRecord $localRecord, array $remoteRecord): void
    {
        $this->logger->info('更新本地DNS记录', [
            'record' => $localRecord->getFullName(),
            'type' => $remoteRecord['type'] ?? null,
            'content' => $remoteRecord['content'] ?? '',
        ]);
    }

    /**
     * 创建新记录
     *
     * @param array<string, mixed> $remoteRecord
     * @param array<string, int>   $counters
     *
     * @return array<string, int>
     */
    private function createNewRecord(DnsDomain $domain, array $remoteRecord, string $recordName, array $counters): array
    {
        $validationResult = $this->validateAndGetRecordType($remoteRecord, $recordName);
        if (null === $validationResult['enumType']) {
            $counters['skip'] += $validationResult['skipCount'];

            return $counters;
        }

        $newRecord = $this->buildNewRecord($domain, $remoteRecord, $recordName, $validationResult['enumType']);
        $this->entityManager->persist($newRecord);
        ++$counters['create'];

        $this->logRecordCreation($domain, $recordName, $remoteRecord);

        return $counters;
    }

    /**
     * 验证并获取记录类型
     *
     * @param array<string, mixed> $remoteRecord
     * @return array{enumType: ?DnsRecordType, skipCount: int}
     */
    private function validateAndGetRecordType(array $remoteRecord, string $recordName): array
    {
        $recordType = $remoteRecord['type'] ?? null;
        $safeRecordType = is_string($recordType) ? $recordType : null;
        $enumType = $this->findRecordTypeEnum($safeRecordType);

        if (null === $enumType) {
            $this->logger->warning('未知的DNS记录类型，跳过创建', [
                'type' => $recordType,
                'record' => $recordName,
            ]);

            return ['enumType' => null, 'skipCount' => 1];
        }

        return ['enumType' => $enumType, 'skipCount' => 0];
    }

    /**
     * 构建新记录
     *
     * @param array<string, mixed> $remoteRecord
     */
    private function buildNewRecord(DnsDomain $domain, array $remoteRecord, string $recordName, DnsRecordType $enumType): DnsRecord
    {
        $newRecord = new DnsRecord();
        $newRecord->setDomain($domain);
        $newRecord->setType($enumType);
        $newRecord->setRecord($recordName);

        $this->setRecordProperties($newRecord, $remoteRecord);
        $this->markRecordAsSynced($newRecord);

        return $newRecord;
    }

    /**
     * 设置记录属性
     *
     * @param array<string, mixed> $remoteRecord
     */
    private function setRecordProperties(DnsRecord $newRecord, array $remoteRecord): void
    {
        $recordId = $remoteRecord['id'] ?? '';
        $newRecord->setRecordId(is_string($recordId) ? $recordId : null);

        $content = $remoteRecord['content'] ?? '';
        if (is_string($content)) {
            $newRecord->setContent($content);
        }

        $ttl = $remoteRecord['ttl'] ?? 60;
        if (is_int($ttl)) {
            $newRecord->setTtl($ttl);
        }

        $proxy = $remoteRecord['proxied'] ?? false;
        if (is_bool($proxy)) {
            $newRecord->setProxy($proxy);
        }
    }

    /**
     * 记录创建日志
     *
     * @param array<string, mixed> $remoteRecord
     */
    private function logRecordCreation(DnsDomain $domain, string $recordName, array $remoteRecord): void
    {
        $this->logger->info('创建本地DNS记录', [
            'record' => $domain->getName() . '.' . $recordName,
            'type' => $remoteRecord['type'] ?? null,
            'content' => $remoteRecord['content'] ?? '',
        ]);
    }

    /**
     * 查找记录类型枚举
     */
    private function findRecordTypeEnum(?string $recordType): ?DnsRecordType
    {
        if (null === $recordType) {
            return null;
        }

        foreach (DnsRecordType::cases() as $case) {
            if ($case->value === $recordType) {
                return $case;
            }
        }

        return null;
    }
}
