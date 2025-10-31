<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainRecordToRemoteCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDomainRecordToRemoteCommand::class)]
#[RunTestsInSeparateProcesses]
final class SyncDomainRecordToRemoteCommandTest extends AbstractCommandTestCase
{
    private SyncDomainRecordToRemoteCommand $command;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->command = self::getService(SyncDomainRecordToRemoteCommand::class);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertEquals('cloudflare:sync-dns-domain-record-to-remote', $this->command->getName());
        $this->assertEquals('将DNS记录同步到远程Cloudflare', $this->command->getDescription());
    }

    public function testCommandHasCorrectArgumentsAndOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('dnsRecordId'));
        $this->assertFalse($definition->getArgument('dnsRecordId')->isRequired());

        $this->assertTrue($definition->hasOption('all'));
        $this->assertFalse($definition->getOption('all')->acceptValue());
    }

    public function testExecuteWithNonexistentRecord(): void
    {
        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute([
            'dnsRecordId' => 999,
        ]);

        $this->assertEquals(1, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('找不到ID为999的DNS记录', $output);
    }

    public function testExecuteSyncAllWithUnsyncedRecords(): void
    {
        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute([
            '--all' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('已将', $output);
        $this->assertStringContainsString('条DNS记录加入同步队列', $output);
    }

    public function testArgumentDnsRecordId(): void
    {
        $result = $this->commandTester->execute(['dnsRecordId' => 999]);
        $this->assertEquals(1, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找不到ID为999的DNS记录', $output);
    }

    public function testOptionAll(): void
    {
        $result = $this->commandTester->execute(['--all' => true]);
        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('已将', $output);
        $this->assertStringContainsString('条DNS记录加入同步队列', $output);
    }
}
