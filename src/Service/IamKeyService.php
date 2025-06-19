<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\IamKeyRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * IAM密钥服务
 * 处理IAM密钥的业务逻辑
 */
class IamKeyService
{
    public function __construct(
        private readonly IamKeyRepository $iamKeyRepository,
    ) {
    }

    /**
     * 查找IAM密钥并验证其有效性
     *
     * @param int $iamKeyId IAM密钥ID
     * @param SymfonyStyle|null $io 用于输出信息的IO接口
     * @return IamKey|null 找到的IAM密钥，如果未找到或无效则返回null
     */
    public function findAndValidateKey(int $iamKeyId, ?SymfonyStyle $io = null): ?IamKey
    {
        $iamKey = $this->iamKeyRepository->find($iamKeyId);

        if ($iamKey === null) {
            if ($io !== null) {
                $io->error(sprintf('找不到 IAM Key: %s', $iamKeyId));
            }
            return null;
        }

        if (!$iamKey->isValid()) {
            if ($io !== null) {
                $io->error(sprintf('IAM Key %s 未激活', $iamKeyId));
            }
            return null;
        }

        return $iamKey;
    }

    /**
     * 验证IAM Key是否设置了AccountId
     *
     * @param IamKey $iamKey IAM密钥
     * @param SymfonyStyle|null $io 用于输出信息的IO接口
     * @return bool 验证结果，true表示有效，false表示无效
     */
    public function validateAccountId(IamKey $iamKey, ?SymfonyStyle $io = null): bool
    {
        if (null === $iamKey->getAccountId()) {
            if ($io !== null) {
                $io->error('IamKey中未设置Account ID，请在IAM Key中设置Account ID');
            }
            return false;
        }

        if ($io !== null) {
            $io->info(sprintf('使用 IAM Key 中的 Account ID: %s', $iamKey->getAccountId()));
        }

        return true;
    }
}
