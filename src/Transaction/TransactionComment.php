<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction;

/**
 * Domain object representing a Transaction Comment.
 */
class TransactionComment
{
    /**
     * @var int The comment ID.
     */
    public int $id;

    /**
     * @var string The comment content.
     */
    public string $content;

    /**
     * @var \DateTimeImmutable|null The creation date.
     */
    public ?\DateTimeImmutable $createdOn = null;
}
