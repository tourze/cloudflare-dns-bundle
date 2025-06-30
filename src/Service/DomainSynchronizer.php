<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Exception\DnsDomainException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 域名同步服务
 * 封装了域名同步的核心业务逻辑，从Command层抽取复杂性
 */
class DomainSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $dnsDomainRepository,
        private readonly DnsDomainService $dnsDomainService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 同步单个域名的详细信息
     */
    public function syncDomainInfo(DnsDomain $domain, ?SymfonyStyle $io = null): bool
    {
        try {
            // 获取域名列表
            $result = $this->dnsDomainService->listDomains($domain);

            foreach ($result['result'] as $item) {
                if ($item['name'] === $domain->getName()) {
                    // 获取域名详情
                    $detail = $this->dnsDomainService->getDomain($domain, $item['name']);
                    $detailData = $detail['result'] ?? [];

                    // 同步域名信息
                    $this->updateDomainDetails($domain, $detailData, $io);

                    $this->entityManager->persist($domain);
                    $this->entityManager->flush();

                    $io?->text("域名 {$domain->getName()} 同步完成");

                    return true;
                }
            }

            // 如果未找到域名
            $this->logger->warning('未在API返回中找到指定域名', [
                'domain' => $domain->getName(),
            ]);

            $io?->warning("域名 {$domain->getName()} 在CloudFlare账户中未找到");

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('同步域名信息失败', [
                'domain' => $domain->getName(),
                'exception' => $e,
            ]);

            $io?->error("处理域名 {$domain->getName()} 失败: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * 更新域名详细信息
     */
    public function updateDomainDetails(DnsDomain $domain, array $detailData, ?SymfonyStyle $io = null): void
    {
        // 同步Zone ID
        $oldZoneId = $domain->getZoneId();
        $zoneId = $this->dnsDomainService->syncZoneId($domain, $detailData);

        if ($io !== null) {
            if ($zoneId !== null && $oldZoneId !== $zoneId) {
                $io->text("  更新Zone ID: {$oldZoneId} -> {$zoneId}");
            } elseif ($zoneId === null && $oldZoneId === null) {
                $io->warning("  未能获取域名 {$domain->getName()} 的Zone ID");
            }
        }

        // 更新域名其他信息
        if (isset($detailData['status'])) {
            // 尝试转换字符串状态为枚举
            $statusString = $detailData['status'];
            $statusEnum = null;
            foreach (DomainStatus::cases() as $case) {
                if ($case->value === $statusString) {
                    $statusEnum = $case;
                    break;
                }
            }
            if ($statusEnum !== null) {
                $domain->setStatus($statusEnum);
            }
        }

        if (isset($detailData['created_at'])) {
            $domain->setCreateTime(new \DateTimeImmutable($detailData['created_at']));
        }

        if (isset($detailData['expires_at'])) {
            $domain->setExpiresTime(new \DateTime($detailData['expires_at']));
        }

        if (isset($detailData['locked_until'])) {
            $domain->setLockedUntilTime(new \DateTime($detailData['locked_until']));
        }

        if (isset($detailData['auto_renew'])) {
            $domain->setAutoRenew($detailData['auto_renew']);
        }
    }

    /**
     * 创建或更新域名
     *
     * @param IamKey $iamKey IAM密钥
     * @param array $domainData API返回的域名数据
     * @param SymfonyStyle|null $io 用于输出信息的IO接口
     * @return DnsDomain 创建或更新的域名对象
     */
    public function createOrUpdateDomain(IamKey $iamKey, array $domainData, ?SymfonyStyle $io = null): DnsDomain
    {
        // 验证必需的字段
        if (!isset($domainData['name'])) {
            throw new DnsDomainException('Domain data must contain a "name" field');
        }
        
        // 检查域名是否已存在
        $existingDomain = $this->dnsDomainRepository->findOneBy([
            'name' => $domainData['name'],
            'iamKey' => $iamKey,
        ]);

        if ($existingDomain === null) {
            // 创建新域名
            $existingDomain = new DnsDomain();
            $existingDomain->setIamKey($iamKey);
            $existingDomain->setName($domainData['name']);

            if ($io !== null) {
                $io->text(sprintf('新增域名: %s', $domainData['name']));
            }
        } else if ($io !== null) {
            $io->text(sprintf('更新域名: %s', $domainData['name']));
        }

        // 更新域名信息
        $this->updateDomainDetails($existingDomain, $domainData, $io);

        // 默认设置为有效
        $existingDomain->setValid(true);

        return $existingDomain;
    }

    /**
     * 查找域名
     *
     * @param string|null $specificDomain 指定域名名称
     * @return DnsDomain[] 找到的域名列表
     */
    public function findDomains(?string $specificDomain = null): array
    {
        if ($specificDomain !== null) {
            return $this->dnsDomainRepository->findBy(['name' => $specificDomain]);
        }

        return $this->dnsDomainRepository->findAll();
    }
}
