<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Psr\Log\LoggerInterface;

/**
 * DnsRecordService 的测试装饰器，不通过继承实现
 */
class TestDnsRecordService
{
    private DnsRecordService $service;
    private TestHttpResponse $response;
    private bool $success;
    private LoggerInterface $logger;
    private DnsDomainRepository $repository;

    public function __construct(
        LoggerInterface     $logger,
        DnsDomainRepository $repository,
        bool                $success = true
    )
    {
        $this->logger = $logger;
        $this->repository = $repository;
        $this->success = $success;
        $this->response = new TestHttpResponse($success);

        // 创建真实的 DnsRecordService
        $this->service = new DnsRecordService($logger, $repository);

        // 使用反射修改 service 中的 getCloudFlareClient 方法
        $this->overrideGetCloudFlareClient();
    }

    /**
     * 使用反射覆盖原始服务的 getCloudFlareClient 方法
     */
    private function overrideGetCloudFlareClient(): void
    {
        // 不能直接覆盖，因为 DnsRecordService 是 final 类
        // 这里我们通过装饰器模式转发所有方法调用
    }

    /**
     * 提取域名的方法
     */
    public function extractDomain(string $name): ?DnsDomain
    {
        return $this->service->extractDomain($name);
    }

    /**
     * 删除记录的方法
     */
    public function removeRecord(DnsRecord $record): void
    {
        // 这里不直接调用原始服务，而是模拟其行为
        $this->logger->info('删除CloudFlare域名记录成功', ['record' => $record]);
    }

    /**
     * 创建记录的方法
     */
    public function createRecord(DnsDomain $domain, DnsRecord $record): array
    {
        if (!$this->success) {
            $this->logger->error('创建CloudFlare域名记录失败', [
                'domain' => $domain,
                'record' => $record,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('创建CloudFlare域名记录失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('创建CloudFlare域名记录成功', [
            'domain' => $domain,
            'record' => $record
        ]);

        return $this->response->toArray();
    }

    /**
     * 更新记录的方法
     */
    public function updateRecord(DnsRecord $record): array
    {
        if (!$this->success) {
            $this->logger->error('更新CloudFlare域名记录失败', [
                'record' => $record,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('更新CloudFlare域名记录失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('更新CloudFlare域名记录成功', ['record' => $record]);

        return $this->response->toArray();
    }

    /**
     * 批量操作记录的方法
     */
    public function batchRecords(DnsDomain $domain, array $operations): array
    {
        if (!$this->success) {
            $this->logger->error('批量操作CloudFlare域名记录失败', [
                'domain' => $domain,
                'operations' => $operations,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('批量操作CloudFlare域名记录失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('批量操作CloudFlare域名记录成功', [
            'domain' => $domain,
            'operations' => $operations
        ]);

        return $this->response->toArray();
    }

    /**
     * 导出记录的方法
     */
    public function exportRecords(DnsDomain $domain): string
    {
        return $this->response->getContent();
    }

    /**
     * 导入记录的方法
     */
    public function importRecords(DnsDomain $domain, string $bindConfig): array
    {
        if (!$this->success) {
            $this->logger->error('导入CloudFlare域名记录失败', [
                'domain' => $domain,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('导入CloudFlare域名记录失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('导入CloudFlare域名记录成功', ['domain' => $domain]);

        return $this->response->toArray();
    }

    /**
     * 扫描记录的方法
     */
    public function scanRecords(DnsDomain $domain): array
    {
        if (!$this->success) {
            $this->logger->error('扫描CloudFlare域名记录失败', [
                'domain' => $domain,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('扫描CloudFlare域名记录失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('扫描CloudFlare域名记录成功', ['domain' => $domain]);

        return $this->response->toArray();
    }

    /**
     * 列出记录的方法
     */
    public function listRecords(DnsDomain $domain, array $params = []): array
    {
        if (!$this->success) {
            $this->logger->error('获取CloudFlare域名记录列表失败', [
                'domain' => $domain,
                'params' => $params,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']]
            ]);
            throw new \RuntimeException('获取CloudFlare域名记录列表失败: [{"code":1003,"message":"Invalid access token"}]');
        }

        $this->logger->info('获取CloudFlare域名记录列表成功', [
            'domain' => $domain,
            'params' => $params
        ]);

        return $this->response->toArray();
    }
}
