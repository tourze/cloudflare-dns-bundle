<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\DDNSContracts\DNSProviderInterface;
use Tourze\DDNSContracts\ExpectResolveResult;

/**
 * Cloudflare DNS提供商，处理DDNS更新请求
 */
class DNSProvider implements DNSProviderInterface
{
    public function __construct(
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsRecordRepository $recordRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'cloudflare-dns';
    }

    /**
     * 检查域名是否由Cloudflare管理
     *
     * 判断依据是：域名或其父域名是否在CloudflareDnsBundle的DnsDomain表中存在记录
     */
    public function check(ExpectResolveResult $result): bool
    {
        $domainName = $result->getDomainName();

        // 检查完整域名或父域名是否在Cloudflare管理中
        // 例如对于 sub.example.com，检查是否有 sub.example.com 或 example.com 的记录
        $domainParts = explode('.', $domainName);

        // 从完整域名开始依次检查
        $checkDomains = [];
        for ($i = 0; $i < count($domainParts) - 1; $i++) {
            $checkDomain = implode('.', array_slice($domainParts, $i));
            $checkDomains[] = $checkDomain;
        }

        // 查找Cloudflare管理的域名
        foreach ($checkDomains as $domain) {
            $exists = $this->domainRepository->findOneBy([
                'name' => $domain,
                'valid' => true
            ]);

            if ($exists) {
                $this->logger->info('域名由Cloudflare管理，将处理DDNS请求', [
                    'domain' => $domainName,
                    'rootDomain' => $domain,
                ]);
                return true;
            }
        }

        $this->logger->debug('域名不由Cloudflare管理，跳过处理', [
            'domain' => $domainName,
        ]);
        return false;
    }

    /**
     * 解析并更新DNS记录
     *
     * 将域名解析到指定的IP地址，如果记录不存在则创建
     */
    public function resolve(ExpectResolveResult $result): void
    {
        $domainName = $result->getDomainName();
        $ipAddress = $result->getIpAddress();

        $this->logger->info('开始处理DDNS解析请求', [
            'domain' => $domainName,
            'ip' => $ipAddress,
        ]);

        try {
            // 找到根域名
            $rootDomain = $this->findRootDomain($domainName);
            if (!$rootDomain) {
                throw new \RuntimeException("找不到匹配的根域名：{$domainName}");
            }

            // 计算子域名记录
            $recordName = $this->getRecordName($domainName, $rootDomain->getName());

            // 查找是否存在记录
            $record = $this->findOrCreateRecord($rootDomain, $recordName, $ipAddress);

            // 更新记录内容（如果已存在且不同）
            if ($record->getContent() !== $ipAddress) {
                $record->setContent($ipAddress);
                // 标记为未同步，等待后续操作同步到Cloudflare
                $record->setSynced(false);
                $this->entityManager->flush();

                $this->logger->info('DNS记录已更新', [
                    'domain' => $domainName,
                    'record' => $recordName,
                    'ip' => $ipAddress,
                ]);
            } else {
                $this->logger->info('DNS记录内容未变更，无需更新', [
                    'domain' => $domainName,
                    'record' => $recordName,
                    'ip' => $ipAddress,
                ]);
            }

            // 如果配置了立即同步，则同步到远程
            $this->syncToRemoteIfNeeded($record);

        } catch (\Exception $e) {
            $this->logger->error('处理DDNS请求失败', [
                'domain' => $domainName,
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 查找域名的根域名
     */
    private function findRootDomain(string $domainName): ?DnsDomain
    {
        $domainParts = explode('.', $domainName);

        // 从完整域名开始依次检查
        for ($i = 0; $i < count($domainParts) - 1; $i++) {
            $checkDomain = implode('.', array_slice($domainParts, $i));

            $rootDomain = $this->domainRepository->findOneBy([
                'name' => $checkDomain,
                'valid' => true
            ]);

            if ($rootDomain) {
                return $rootDomain;
            }
        }

        return null;
    }

    /**
     * 获取域名记录值（相对于根域名）
     *
     * 例如：sub.example.com 相对于 example.com 的记录名为 sub
     */
    private function getRecordName(string $fullDomain, string $rootDomain): string
    {
        if ($fullDomain === $rootDomain) {
            return '@'; // 根域名记录
        }

        // 去除根域名部分
        return substr($fullDomain, 0, -(strlen($rootDomain) + 1));
    }

    /**
     * 查找或创建DNS记录
     */
    private function findOrCreateRecord(DnsDomain $domain, string $recordName, string $ipAddress): DnsRecord
    {
        // 先根据域名和记录名查找A记录
        $record = $this->recordRepository->findOneBy([
            'domain' => $domain,
            'record' => $recordName,
            'type' => DnsRecordType::A
        ]);

        // 如果不存在，则创建新记录
        if (!$record) {
            $this->logger->info('域名记录不存在，创建新记录', [
                'domain' => $domain->getName(),
                'record' => $recordName,
            ]);

            $record = new DnsRecord();
            $record->setDomain($domain);
            $record->setRecord($recordName);
            $record->setType(DnsRecordType::A);
            $record->setContent($ipAddress);
            $record->setTtl(60); // 设置较短的TTL便于快速更新
            $record->setProxy(false); // 默认不使用Cloudflare代理

            $this->entityManager->persist($record);
            $this->entityManager->flush();
        }

        return $record;
    }

    /**
     * 如果需要则同步记录到远程
     */
    private function syncToRemoteIfNeeded(DnsRecord $record): void
    {
        // 检查是否需要同步
        if (!$record->isSynced()) {
            try {
                $domain = $record->getDomain();

                // 如果没有记录ID，需要创建新记录
                if (!$record->getRecordId()) {
                    // 这里应该注入DnsRecordService服务
                    // 由于当前服务中没有注入此服务，此处仅示例代码
                    // 实际应该在构造函数中注入DnsRecordService

                    $this->logger->info('DNS记录需要同步到远程，但当前未实现立即同步功能', [
                        'record' => $record->getFullName(),
                        'type' => $record->getType()->value,
                        'content' => $record->getContent(),
                    ]);

                    // 标记为未同步，等待命令行程序或其他过程处理
                    $record->setSynced(false);
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                $this->logger->error('同步DNS记录到远程失败', [
                    'record' => $record->getFullName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
