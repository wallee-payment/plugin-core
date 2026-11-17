<?php

namespace MyPlugin\ExampleTransactionCommentImplementation;

/**
 * Transaction Comment Example
 *
 * Reads the comments attached to a transaction in the Wallee
 * Portal, the notes a plugin can show in the shop's order view.
 *
 * USAGE:
 * php transaction_comments.php [transaction_id]
 */

use Wallee\PluginCore\Examples\Common\TransactionIdLoader;
use Wallee\PluginCore\Sdk\WebServiceAPIV2\TransactionCommentGateway;
use Wallee\PluginCore\Transaction\Exception\TransactionCommentException;
use Wallee\PluginCore\Transaction\TransactionCommentService;

// 📖 Concept documentation: See docs/3-Post-Payment/TransactionComment.md

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../Common/bootstrap.php';

$spaceId = (int)$common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];

// Load Transaction ID from command line arguments or the session file.
try {
    $transactionId = TransactionIdLoader::load($argv);
} catch (\Exception $e) {
    exit($e->getMessage());
}

echo "Reading comments of Transaction ID: $transactionId\n";

$commentService = new TransactionCommentService(new TransactionCommentGateway($sdkProvider, $logger), $logger);

try {
    $comments = $commentService->getComments($spaceId, $transactionId);
} catch (TransactionCommentException $e) {
    exit("\n[FAILED] " . $e->getMessage() . "\n");
}

if ($comments->isEmpty()) {
    exit("No comments on this transaction (add one in the Wallee Portal and run again).\n");
}

echo "\n[Comments] ({$comments->count()})\n";
foreach ($comments as $comment) {
    $date = $comment->createdOn?->format(\DATE_ATOM) ?? 'no date';
    echo " > #{$comment->id} ($date): {$comment->content}\n";
}
