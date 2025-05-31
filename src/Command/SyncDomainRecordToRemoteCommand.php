<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: SyncDomainRecordToRemoteCommand::NAME,
    description: '将DNS记录同步到远程Cloudflare'
)]
class SyncDomainRecordToRemoteCommand extends Command
{
    public const NAME = 'cloudflare:sync-dns-domain-record-to-remote';

    public function __construct(
        private readonly DnsRecordRepository $recordRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dnsRecordId', InputArgument::OPTIONAL, 'DNS本地记录ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, '同步所有未同步的记录');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 检查是否同步所有记录
        if ($input->getOption('all')) {
            return $this->syncAllRecords($io);
        }

        // 如果没有提供recordId且没有--all选项，显示错误
        $recordId = $input->getArgument('dnsRecordId');
        if (!$recordId) {
            $io->error('请提供DNS记录ID或使用--all选项同步所有未同步的记录');
            return Command::FAILURE;
        }

        // 同步单个记录
        $record = $this->recordRepository->find($recordId);

        if (!$record) {
            $io->error(sprintf('找不到ID为%s的DNS记录', $recordId));
            return Command::FAILURE;
        }

        // 创建消息并发送到队列
        $message = new SyncDnsRecordToRemoteMessage($record->getId());
        $this->messageBus->dispatch($message);

        $io->success(sprintf(
            '已将DNS记录【%s】加入同步队列，类型: %s，内容: %s', 
            $record->getFullName(), 
            $record->getType()->value, 
            $record->getContent()
        ));

        return Command::SUCCESS;
    }

    /**
     * 同步所有未同步的记录
     */
    private function syncAllRecords(SymfonyStyle $io): int
    {
        // 查询所有未同步的记录
        $records = $this->recordRepository->findBy([
            'synced' => false,
        ]);

        if (empty($records)) {
            $io->info('没有需要同步的DNS记录');
            return Command::SUCCESS;
        }

        $count = count($records);
        $io->info(sprintf('找到%d条未同步的DNS记录', $count));

        $io->progressStart($count);

        foreach ($records as $record) {
            // 创建消息并发送到队列
            $message = new SyncDnsRecordToRemoteMessage($record->getId());
            $this->messageBus->dispatch($message);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('已将%d条DNS记录加入同步队列', $count));

        return Command::SUCCESS;
    }
}
