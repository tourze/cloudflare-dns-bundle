<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsDomainService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: SyncDomainInfoCommand::NAME)]
class SyncDomainInfoCommand extends Command
{
    public const NAME = 'cloudflare:sync-domain-info';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsDomainService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domains = $this->domainRepository->findAll();
        foreach ($domains as $domain) {
            try {
                $output->writeln("开始处理域名：{$domain->getName()}");

                // 获取域名列表
                $result = $this->dnsService->listDomains($domain);
                foreach ($result as $item) {
                    // 获取域名详情
                    $detail = $this->dnsService->getDomain($domain, $item['name']);
                    var_dump($detail);

                    // 更新域名信息
                    $domain->setRegistrar($detail['registrar'] ?? null);
                    $domain->setStatus($detail['status'] ?? null);
                    $domain->setCreateTime(new \DateTime($detail['created_at']));
                    $domain->setExpiresAt(new \DateTime($detail['expires_at']));
                    $domain->setLockedUntil(isset($detail['locked_until']) ? new \DateTime($detail['locked_until']) : null);
                    $domain->setAutoRenew($detail['auto_renew'] ?? false);

                    $this->entityManager->persist($domain);
                    $this->entityManager->flush();
                }

                $output->writeln('处理完成');
            } catch (\Throwable $e) {
                $this->logger->error('同步域名信息失败', [
                    'domain' => $domain,
                    'exception' => $e,
                ]);
                $output->writeln("<error>处理失败: {$e->getMessage()}</error>");
            }
        }

        return Command::SUCCESS;
    }
}
