<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\PaymentMethod;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\State;

class PaymentMethodTest extends TestCase
{
    /**
     * Factory helper to construct a PaymentMethod with only the imageUrl varying.
     */
    private function createMethodWithImageUrl(?string $imageUrl): PaymentMethod
    {
        return new PaymentMethod(
            id: 1,
            spaceId: 1,
            state: State::ACTIVE,
            title: new LocalizedString([]),
            description: new LocalizedString(null),
            sortOrder: 0,
            imageUrl: $imageUrl,
        );
    }

    /**
     * Verifies that the relative path is correctly extracted after the 'resource/' segment.
     */
    public function testExtractsRelativePathCorrectly(): void
    {
        $method = $this->createMethodWithImageUrl('https://app-wallee.com/s/123/resource/payment/icon.svg');

        $this->assertSame('payment/icon.svg', $method->getRelativeImagePath());
    }

    /**
     * A null imageUrl must produce an empty string without triggering PHP 8.2 deprecation warnings.
     */
    public function testHandlesNullUrl(): void
    {
        $method = $this->createMethodWithImageUrl(null);

        $this->assertSame('', $method->getRelativeImagePath());
    }

    /**
     * When the URL does not contain a 'resource/' segment, the full URL is returned as-is.
     */
    public function testReturnsOriginalStringIfResourceNotFound(): void
    {
        $method = $this->createMethodWithImageUrl('https://app-wallee.com/images/icon.svg');

        $this->assertSame('https://app-wallee.com/images/icon.svg', $method->getRelativeImagePath());
    }

    /**
     * Verifies that query parameters (e.g. for cache busting) are stripped from the path.
     */
    public function testStripsQueryParameters(): void
    {
        $method = $this->createMethodWithImageUrl('https://app-wallee.com/s/123/resource/payment/twint.svg?strategy=snapshot');

        $this->assertSame('payment/twint.svg', $method->getRelativeImagePath());
    }
    public function testToString(): void
    {
        $method = new PaymentMethod(
            85,
            1,
            State::ACTIVE,
            new LocalizedString(['en-US' => 'Credit Card', 'de-DE' => 'Kreditkarte']),
            new LocalizedString(['en-US' => 'Pay securely with CC']),
            10,
            'https://example.com/cc.png',
        );

        $json = (string) $method;
        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertEquals(85, $decoded['id']);
        $this->assertEquals(1, $decoded['spaceId']);
        $this->assertEquals('ACTIVE', $decoded['state']);
        $this->assertEquals(10, $decoded['sortOrder']);
        $this->assertEquals('https://example.com/cc.png', $decoded['imageUrl']);

        // The VO serializes to its raw data, so the title should be the array
        $this->assertIsArray($decoded['title']);
        $this->assertEquals('Credit Card', $decoded['title']['en-US']);
    }
}
