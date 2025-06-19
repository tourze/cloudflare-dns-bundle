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
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: self::NAME, description: '同步 Cloudflare DNS 分析数据')]
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
            ->addOption('cleanup-before', null, InputOption::VALUE_REQUIRED, '清理多少天前的数据', '30')
            ->addOption('domain-id', null, InputOption::VALUE_OPTIONAL, '指定单个域名ID')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, '跳过错误，继续处理其他域名');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 清理旧数据
        $cleanupDays = (int)$input->getOption('cleanup-before');
        if ($cleanupDays > 0) {
            $cleanupTime = new \DateTime("-{$cleanupDays} days");
            $count = $this->analyticsRepository->cleanupBefore($cleanupTime);
            $io->success("清理了 {$count} 条旧数据");
        }

        // 域名查询条件
        $criteria = [];
        $domainId = $input->getOption('domain-id');
        if ($domainId !== null) {
            $criteria['id'] = $domainId;
        }

        // 只选择有效且有zoneId的域名
        $criteria['valid'] = true;
        $domains = $this->domainRepository->findBy($criteria);

        if (empty($domains)) {
            $io->warning('没有找到符合条件的域名');
            return Command::SUCCESS;
        }

        $io->title('开始同步DNS分析数据');
        $io->progressStart(count($domains));

        $skipErrors = $input->getOption('skip-errors');
        $successCount = 0;
        $errorCount = 0;

        // 准备API请求参数
        $params = [
            'dimensions' => ['queryName', 'queryType'],
            'metrics' => ['queryCount', 'responseTimeAvg', 'uncachedCount'],
            'since' => $input->getOption('since'),
            'until' => $input->getOption('until'),
            'time_delta' => $input->getOption('time-delta'),
            'limit' => 10000 // 设置合理的限制
        ];

        foreach ($domains as $domain) {
            $io->progressAdvance();

            try {
                $this->logger->info("开始处理域名", [
                    'domain' => $domain->getName(),
                    'zoneId' => $domain->getZoneId()
                ]);

                // 检查域名是否有zoneId
                if ($domain->getZoneId() === null || $domain->getZoneId() === '') {
                    $this->logger->warning("域名没有zoneId，跳过", [
                        'domain' => $domain->getName()
                    ]);
                    continue;
                }

                // 尝试先确认域名状态和权限
                try {
                    $zoneDetails = $this->dnsService->getZoneDetails($domain);

                    // 检查计划类型，DNS Analytics可能需要企业计划
                    $plan = $zoneDetails['result']['plan']['name'] ?? 'Unknown';
                    if (!in_array($plan, ['Enterprise', 'Business', 'Pro'])) {
                        $this->logger->warning("域名计划可能不支持DNS Analytics", [
                            'domain' => $domain->getName(),
                            'plan' => $plan
                        ]);
                    }

                    // 检查域名状态
                    $status = $zoneDetails['result']['status'] ?? 'unknown';
                    if ($status !== 'active') {
                        $this->logger->warning("域名不是活跃状态，可能无法获取分析数据", [
                            'domain' => $domain->getName(),
                            'status' => $status
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning("获取域名详情失败，但将继续尝试获取分析数据", [
                        'domain' => $domain->getName(),
                        'error' => $e->getMessage()
                    ]);
                }

                // 获取DNS分析数据 (使用正确的API路径和更合理的参数)
                $result = $this->dnsService->getDnsAnalytics($domain, $params);

                if (empty($result['data']) || !isset($result['success']) || $result['success'] !== true) {
                    $this->logger->warning("API返回成功但无分析数据", [
                        'domain' => $domain->getName(),
                        'response' => $result
                    ]);
                    continue;
                }

                // 保存数据
                $recordCount = 0;
                foreach ($result['data'] as $item) {
                    if (!isset($item['data']) || !is_array($item['data'])) {
                        continue;
                    }

                    foreach ($item['data'] as $data) {
                        if (!isset($data['dimensions']) || !isset($data['metrics']) || 
                            count($data['dimensions']) < 2 || count($data['metrics']) < 2) {
                            continue;
                        }

                        $analytics = new DnsAnalytics();
                        $analytics->setDomain($domain);
                        $analytics->setQueryName($data['dimensions'][0] ?? 'unknown');
                        $analytics->setQueryType($data['dimensions'][1] ?? 'unknown');
                        $analytics->setQueryCount($data['metrics'][0] ?? 0);
                        $analytics->setResponseTimeAvg($data['metrics'][1] ?? 0);
                        $analytics->setStatTime(new \DateTime($item['time']));
                        $this->entityManager->persist($analytics);
                        $recordCount++;

                        // 每100条记录刷新一次，避免内存问题
                        if ($recordCount % 100 === 0) {
                            $this->entityManager->flush();
                        }
                    }
                }

                // 最终刷新
                $this->entityManager->flush();
                $this->logger->info("域名DNS分析数据同步成功", [
                    'domain' => $domain->getName(),
                    'recordCount' => $recordCount
                ]);

                $successCount++;

            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('同步DNS分析数据失败', [
                    'domain' => $domain->getName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                if ((bool)$skipErrors === false) {
                    $io->progressFinish();
                    $io->error("处理域名 {$domain->getName()} 时出错: {$e->getMessage()}");
                    return Command::FAILURE;
                }
            }
        }

        $io->progressFinish();

        if ($successCount > 0) {
            $io->success("成功同步 {$successCount} 个域名的DNS分析数据");
        }

        if ($errorCount > 0) {
            $io->warning("有 {$errorCount} 个域名同步失败");
        }

        return Command::SUCCESS;
    }
}
