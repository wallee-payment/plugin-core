# Transaction Comment

Comments are notes attached to a transaction in the Wallee Portal, for example a note a merchant's team added while reviewing the payment. This feature lets a plugin read them, so it can show them in the shop's order view next to the order's own history.

## Key Components

- **TransactionCommentService**: The entry point consumers use. Exposes `getComments(int $spaceId, int $transactionId): TransactionCommentCollection`.
- **TransactionCommentGatewayInterface**: Connects `TransactionCommentService` to the API. Pass it in when you create the service, then use the service for every call.
- **TransactionComment**: One comment: `id`, `content`, and `createdOn` (a `DateTimeImmutable`, or `null` when the API reports no date).
- **TransactionCommentCollection**: The comments of one transaction. Iterate it, `count()` it, or check `isEmpty()`.

## Usage

```php
use Wallee\PluginCore\Sdk\WebServiceAPIV2\TransactionCommentGateway;
use Wallee\PluginCore\Transaction\TransactionCommentService;

$commentService = new TransactionCommentService(new TransactionCommentGateway($sdkProvider, $logger), $logger);

foreach ($commentService->getComments($spaceId, $transactionId) as $comment) {
    echo $comment->createdOn?->format('Y-m-d H:i') . ' ' . $comment->content . "\n";
}
```

A transaction without comments returns an empty collection, not an error.

👉 **See this in action:** [transaction_comments.php](../examples/3-Post-Payment/transaction_comments.php)

## Errors

`getComments()` throws `Transaction\Exception\TransactionCommentException` when the comments cannot be read. Like every other PluginCore exception, it exposes `isRetryable()` — see [Error Handling](../1-Getting-Started/ErrorHandling.md).
