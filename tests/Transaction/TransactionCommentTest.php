<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Transaction;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Transaction\TransactionComment;

class TransactionCommentTest extends TestCase
{
    public function testToString(): void
    {
        $comment = new TransactionComment();
        $comment->id = 50;
        $comment->content = 'Test comment content';

        $json = (string) $comment;
        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertEquals(50, $decoded['id']);
        $this->assertEquals('Test comment content', $decoded['content']);
    }
}
