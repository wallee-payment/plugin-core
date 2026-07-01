<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV1;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\DateTimeMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Exception\TransactionCommentException;
use Wallee\PluginCore\Transaction\TransactionComment;
use Wallee\PluginCore\Transaction\TransactionCommentCollection;
use Wallee\PluginCore\Transaction\TransactionCommentGatewayInterface;
use Wallee\Sdk\Model\TransactionComment as SdkTransactionComment;
use Wallee\Sdk\Service\TransactionCommentService as SdkTransactionCommentService;

/**
 * Gateway for retrieving transaction comments.
 */
class TransactionCommentGateway implements TransactionCommentGatewayInterface
{
    use DateTimeMapperTrait;

    /**
     * @var SdkTransactionCommentService
     */
    private SdkTransactionCommentService $service;

    /**
     * TransactionCommentGateway constructor.
     *
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->service = $this->sdkProvider->getService(SdkTransactionCommentService::class);
    }

    /**
     * @inheritDoc
     */
    public function getComments(int $spaceId, int $transactionId): TransactionCommentCollection
    {
        try {
            $this->logger->debug(
                'Fetching comments for Transaction {transactionId} in Space {spaceId}.',
                [
                    'spaceId' => $spaceId,
                    'transactionId' => $transactionId,
                ],
            );
            $sdkComments = $this->service->all($spaceId, $transactionId);

            return new TransactionCommentCollection(...array_map([$this, 'mapToTransactionComment'], $sdkComments));
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to fetch transaction comments: {errorMessage}',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                    'spaceId' => $spaceId,
                    'transactionId' => $transactionId,
                ],
            );
            throw new TransactionCommentException(
                "Failed to fetch comments for transaction {$transactionId}: " . $e->getMessage(),
                new LocalizedString('An error occurred while fetching transaction comments.'),
                $e,
            );
        }
    }

    /**
     * Maps SDK TransactionComment to Domain object.
     *
     * @param SdkTransactionComment $sdkComment
     * @return TransactionComment
     */
    private function mapToTransactionComment(SdkTransactionComment $sdkComment): TransactionComment
    {
        $comment = new TransactionComment();
        $comment->id = $sdkComment->getId();
        $comment->content = $sdkComment->getContent();
        $comment->createdOn = $this->toDateTimeImmutable($sdkComment->getCreatedOn());

        return $comment;
    }
}
