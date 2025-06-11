<?php

namespace CloudflareDnsBundle\MessageHandler;

use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理从远程同步DNS记录到本地的消息
 */
#[AsMessageHandler]
class SyncDnsDomainsFromRemoteMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsRecordRepository $recordRepository,
        private readonly DnsRecordService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncDnsDomainsFromRemoteMessage $message): void
    {
        // 查找域名
        $domainId = $message->getDomainId();
        $domain = $this->domainRepository->find($domainId);

        if (!$domain) {
            $this->logger->warning('找不到要同步的域名', [
                'domainId' => $domainId,
            ]);
            return;
        }

        if (!$domain->isValid() || !$domain->getZoneId()) {
            $this->logger->warning('域名无效或缺少Zone ID，无法同步', [
                'domain' => $domain->getName(),
                'valid' => $domain->isValid(),
                'zoneId' => $domain->getZoneId(),
            ]);
            return;
        }

        try {
            $this->logger->info('开始从Cloudflare获取域名解析记录', [
                'domain' => $domain->getName(),
                'zoneId' => $domain->getZoneId(),
            ]);

            // 分页获取所有记录
            $page = 1;
            $perPage = 50;
            $allRemoteRecords = [];

            while (true) {
                $response = $this->dnsService->listRecords($domain, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                if (!$response['success']) {
                    $this->logger->warning('获取DNS记录列表失败', [
                        'domain' => $domain->getName(),
                        'result' => $response,
                    ]);
                    break;
                }

                if (empty($response['result'])) {
                    break;
                }

                $allRemoteRecords = array_merge($allRemoteRecords, $response['result']);

                // 检查是否有更多页
                if (!isset($response['result_info']['total_pages']) ||
                    $response['result_info']['total_pages'] <= $page) {
                    break;
                }

                $page++;
            }

            if (empty($allRemoteRecords)) {
                $this->logger->info('域名在Cloudflare上没有任何解析记录', [
                    'domain' => $domain->getName(),
                ]);
                return;
            }

            $this->logger->info('从Cloudflare获取到解析记录', [
                'domain' => $domain->getName(),
                'count' => count($allRemoteRecords),
            ]);

            // 获取本地已存在的记录，并建立索引
            $localRecords = $this->recordRepository->findBy(['domain' => $domain]);
            $localRecordMap = [];
            $localRecordByCloudflareId = [];

            foreach ($localRecords as $record) {
                $key = $record->getType()->value . '_' . $record->getRecord();
                $localRecordMap[$key] = $record;

                if ($record->getRecordId()) {
                    $localRecordByCloudflareId[$record->getRecordId()] = $record;
                }
            }

            $createCount = 0;
            $updateCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            // 处理远程记录
            foreach ($allRemoteRecords as $remoteRecord) {
                try {
                    $recordType = $remoteRecord['type'] ?? null;
                    $recordName = $remoteRecord['name'] ?? '';
                    $recordContent = $remoteRecord['content'] ?? '';
                    $recordId = $remoteRecord['id'] ?? '';

                    // 去除域名后缀，获取子域名部分
                    $domainSuffix = '.' . $domain->getName();
                    if (str_ends_with($recordName, $domainSuffix)) {
                        $recordName = substr($recordName, 0, -strlen($domainSuffix));
                    }
                    // 处理根域名的情况
                    if ($recordName === $domain->getName()) {
                        $recordName = '@';
                    }

                    // 构建记录键名用于查找
                    $key = $recordType . '_' . $recordName;

                    // 如果本地已有此记录，则更新
                    if (isset($localRecordMap[$key]) || isset($localRecordByCloudflareId[$recordId])) {
                        // 优先使用ID匹配，其次使用类型和名称匹配
                        $localRecord = isset($localRecordByCloudflareId[$recordId])
                            ? $localRecordByCloudflareId[$recordId]
                            : $localRecordMap[$key];

                        // 检查是否需要更新
                        $needUpdate = false;

                        // 更新记录ID（可能在本地已有记录但没有ID的情况）
                        if (!$localRecord->getRecordId() && $recordId) {
                            $localRecord->setRecordId($recordId);
                            $needUpdate = true;
                        }

                        // 检查内容是否有变化
                        if ($localRecord->getContent() !== $recordContent) {
                            $localRecord->setContent($recordContent);
                            $needUpdate = true;
                        }

                        // 更新其他字段
                        if (isset($remoteRecord['ttl']) && $localRecord->getTtl() != $remoteRecord['ttl']) {
                            $localRecord->setTtl($remoteRecord['ttl']);
                            $needUpdate = true;
                        }

                        if (isset($remoteRecord['proxied']) && $localRecord->isProxy() !== $remoteRecord['proxied']) {
                            $localRecord->setProxy($remoteRecord['proxied']);
                            $needUpdate = true;
                        }

                        if ($needUpdate) {
                            $localRecord->setSynced(true); // 设置为已同步
                            $localRecord->setLastSyncedTime(new \DateTime());
                            $this->entityManager->persist($localRecord);
                            $updateCount++;

                            $this->logger->info('更新本地DNS记录', [
                                'record' => $localRecord->getFullName(),
                                'type' => $recordType,
                                'content' => $recordContent,
                            ]);
                        } else {
                            $skipCount++;
                        }
                    } else {
                        // 本地没有此记录，创建新记录
                        // 尝试将字符串类型转换为枚举类型
                        $enumType = null;
                        foreach (DnsRecordType::cases() as $case) {
                            if ($case->value === $recordType) {
                                $enumType = $case;
                                break;
                            }
                        }

                        if ($enumType) {
                            $newRecord = new DnsRecord();
                            $newRecord->setDomain($domain);
                            $newRecord->setType($enumType);
                            $newRecord->setRecord($recordName);
                            $newRecord->setRecordId($recordId);
                            $newRecord->setContent($recordContent);
                            $newRecord->setTtl($remoteRecord['ttl'] ?? 60);
                            $newRecord->setProxy($remoteRecord['proxied'] ?? false);
                            $newRecord->setSynced(true); // 设置为已同步
                            $newRecord->setLastSyncedTime(new \DateTime());

                            $this->entityManager->persist($newRecord);
                            $createCount++;

                            $this->logger->info('创建本地DNS记录', [
                                'record' => $domain->getName() . '.' . $recordName,
                                'type' => $recordType,
                                'content' => $recordContent,
                            ]);
                        } else {
                            $this->logger->warning('未知的DNS记录类型，跳过创建', [
                                'type' => $recordType,
                                'record' => $recordName,
                            ]);
                            $skipCount++;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('处理DNS记录时出错', [
                        'record' => $remoteRecord['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            // 提交所有变更
            $this->entityManager->flush();

            $this->logger->info('域名DNS记录同步完成', [
                'domain' => $domain->getName(),
                'created' => $createCount,
                'updated' => $updateCount,
                'skipped' => $skipCount,
                'errors' => $errorCount,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('同步DNS记录时发生错误', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
