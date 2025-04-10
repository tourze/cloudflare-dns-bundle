<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: SyncDnsAnalyticsCommand::NAME)]
class SyncDnsAnalyticsCommand extends Command
{
    public const NAME = 'cloudflare:sync-dns-analytics';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsAnalyticsRepository $analyticsRepository,
        private readonly DnsAnalyticsService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, '开始时间(相对时间,如-24h)', '-24h')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, '结束时间(相对时间,如now)', 'now')
            ->addOption('time-delta', null, InputOption::VALUE_REQUIRED, '时间间隔(如1h)', '1h')
            ->addOption('cleanup-before', null, InputOption::VALUE_REQUIRED, '清理多少天前的数据', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 清理旧数据
        $cleanupDays = (int)$input->getOption('cleanup-before');
        if ($cleanupDays > 0) {
            $cleanupTime = new \DateTime("-{$cleanupDays} days");
            $count = $this->analyticsRepository->cleanupBefore($cleanupTime);
            $output->writeln("清理了 {$count} 条旧数据");
        }

        $domains = $this->domainRepository->findAll();
        foreach ($domains as $domain) {
            try {
                $output->writeln("开始处理域名：{$domain->getName()}");

                // 获取DNS分析数据
                $result = $this->dnsService->getDnsAnalyticsByTime($domain, [
                    'dimensions' => ['queryName', 'queryType'],
                    'metrics' => ['queryCount', 'responseTimeAvg'],
                    'since' => $input->getOption('since'),
                    'until' => $input->getOption('until'),
                    'time_delta' => $input->getOption('time-delta'),
                ]);

                // 保存数据
                foreach ($result['data'] as $item) {
                    foreach ($item['data'] as $data) {
                        $analytics = new DnsAnalytics();
                        $analytics->setDomain($domain);
                        $analytics->setQueryName($data['dimensions'][0]);
                        $analytics->setQueryType($data['dimensions'][1]);
                        $analytics->setQueryCount($data['metrics'][0]);
                        $analytics->setResponseTimeAvg($data['metrics'][1]);
                        $analytics->setStatTime(new \DateTime($item['time']));
                        $this->entityManager->persist($analytics);
                        $this->entityManager->flush();
                    }
                }

                $output->writeln('处理完成');
            } catch (\Throwable $e) {
                $this->logger->error('同步DNS分析数据失败', [
                    'domain' => $domain,
                    'exception' => $e,
                ]);
                $output->writeln("<error>处理失败: {$e->getMessage()}</error>");
            }
        }

        return Command::SUCCESS;
    }
}
