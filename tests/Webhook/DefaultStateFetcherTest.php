<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Http\Request;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Settings\Settings;
use Wallee\PluginCore\Webhook\DefaultStateFetcher;
use Wallee\Sdk\Service\DeliveryIndicationsService;
use Wallee\Sdk\Service\ManualTasksService;
use Wallee\Sdk\Service\RefundsService;
use Wallee\Sdk\Service\TokensService;
use Wallee\Sdk\Service\TransactionCompletionsService;
use Wallee\Sdk\Service\TransactionInvoicesService;
use Wallee\Sdk\Service\TransactionsService;
use Wallee\Sdk\Service\TransactionVoidsService;
use Wallee\Sdk\Service\WebhookEncryptionKeysService;

/**
 * Unit tests for the DefaultStateFetcher class.
 */
class DefaultStateFetcherTest extends TestCase
{
    private WebhookEncryptionKeysService $encryptionServiceMock;

    private DefaultStateFetcher $fetcher;

    private SdkProvider $sdkProviderMock;

    /**
     * Registry of service class name to its mock instance.
     *
     * @var array<string, object>
     */
    private array $services = [];

    private Settings $settingsMock;

    /**
     * Helper method to create Request instances for tests using reflection.
     *
     * @param array<string, string> $headers The request headers.
     * @param array<string, mixed> $body The request body data.
     * @param string $rawBody The raw request body.
     * @return Request The created Request instance.
     */
    private function createRequest(array $headers, array $body, string $rawBody): Request
    {
        $reflection = new \ReflectionClass(Request::class, );
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true, );
        $request = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($request, $headers, $body, $rawBody, );
        return $request;
    }

    /**
     * Set up the test fixtures.
     */
    protected function setUp(): void
    {
        $this->sdkProviderMock = $this->createMock(SdkProvider::class, );
        $this->settingsMock = $this->createMock(Settings::class, );
        $this->encryptionServiceMock = $this->createMock(WebhookEncryptionKeysService::class, );

        $this->services = [
            WebhookEncryptionKeysService::class => $this->encryptionServiceMock,
        ];

        // Route all SDK provider service fetches through the internal services registry.
        $this->sdkProviderMock->method('getService', )
            ->willReturnCallback(function (string $serviceClass): object {
                if (isset($this->services[$serviceClass])) {
                    return $this->services[$serviceClass];
                }
                throw new \InvalidArgumentException("Service not mocked: " . $serviceClass, );
            }, );

        $this->settingsMock->method('getSpaceId', )->willReturn(1234, );

        $this->fetcher = new DefaultStateFetcher(
            $this->sdkProviderMock,
            $this->settingsMock,
        );
    }

    /**
     * Data provider for supported entity types and their corresponding SDK service classes.
     *
     * @return array<string, array{0: string, 1: string, 2: string}> The dataset.
     */
    public static function supportedEntitiesProvider(): array
    {
        return [
            'DeliveryIndication' => [
                'DeliveryIndication',
                DeliveryIndicationsService::class,
                'PENDING',
            ],
            'ManualTask' => [
                'ManualTask',
                ManualTasksService::class,
                'DONE',
            ],
            'Refund' => [
                'Refund',
                RefundsService::class,
                'SUCCESSFUL',
            ],
            'Token' => [
                'Token',
                TokensService::class,
                'ACTIVE',
            ],
            'Transaction' => [
                'Transaction',
                TransactionsService::class,
                'CONFIRMED',
            ],
            'TransactionCompletion' => [
                'TransactionCompletion',
                TransactionCompletionsService::class,
                'SUCCESSFUL',
            ],
            'TransactionInvoice' => [
                'TransactionInvoice',
                TransactionInvoicesService::class,
                'PAID',
            ],
            'TransactionVoid' => [
                'TransactionVoid',
                TransactionVoidsService::class,
                'SUCCESSFUL',
            ],
        ];
    }

    /**
     * Verifies that the correct service is invoked to fetch state for all supported entities.
     *
     * @dataProvider supportedEntitiesProvider
     *
     * @param string $technicalName The technical name of the entity.
     * @param class-string $serviceClass The SDK service class name.
     * @param string $expectedState The expected return state value.
     */
    public function testFetchStateCallsCorrectServiceForSupportedEntities(
        string $technicalName,
        string $serviceClass,
        string $expectedState,
    ): void {
        // --- Arrange ---
        $request = $this->createRequest(
            [],
            ['listenerEntityTechnicalName' => $technicalName,],
            '',
        );

        $mockEntity = $this->createMock(DefaultStateFetcherTestMockEntity::class, );
        $mockEntity->expects($this->once(), )
            ->method('getState', )
            ->willReturn($expectedState, );

        $serviceMock = $this->getMockBuilder($serviceClass, )
            ->disableOriginalConstructor()
            ->addMethods(['read', ])
            ->getMock();

        $serviceMock->expects($this->once(), )
            ->method('read', )
            ->with(1234, 567, )
            ->willReturn($mockEntity, );

        $this->services[$serviceClass] = $serviceMock;

        // --- Act ---
        $state = $this->fetcher->fetchState($request, 567, );

        // --- Assert ---
        $this->assertSame($expectedState, $state, );
    }

    /**
     * Verifies that the signed state is returned when a valid signature exists.
     */
    public function testFetchStateReturnsStateFromSignedPayloadWhenSignatureIsValid(): void
    {
        // --- Arrange ---
        $request = $this->createRequest(
            ['x-signature' => 'a-valid-signature-header',],
            ['state' => 'COMPLETED',],
            'raw-body-content',
        );

        $this->encryptionServiceMock
            ->expects($this->once(), )
            ->method('isContentValid', )
            ->with('a-valid-signature-header', 'raw-body-content', )
            ->willReturn(true, );

        // --- Act ---
        $state = $this->fetcher->fetchState($request, 567, );

        // --- Assert ---
        $this->assertSame('COMPLETED', $state, );
    }

    /**
     * Verifies that an exception is thrown when signature validation fails.
     */
    public function testFetchStateThrowsExceptionWhenSignatureIsInvalid(): void
    {
        // --- Assert ---
        $this->expectException(\Exception::class, );
        $this->expectExceptionMessage('Invalid webhook signature: validation check failed.', );

        // --- Arrange ---
        $request = $this->createRequest(
            ['x-signature' => 'an-invalid-signature-header',],
            [],
            'raw-body-content',
        );

        $this->encryptionServiceMock->method('isContentValid', )->willReturn(false, );

        // --- Act ---
        $this->fetcher->fetchState($request, 567, );
    }

    /**
     * Verifies that an exception is thrown when the technical name is unsupported.
     */
    public function testFetchStateThrowsExceptionWhenTechnicalNameIsUnsupported(): void
    {
        // --- Assert ---
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Legacy state fetching not supported for entity: UnsupportedEntity');

        // --- Arrange ---
        $request = $this->createRequest(
            [],
            ['listenerEntityTechnicalName' => 'UnsupportedEntity',],
            '',
        );

        // --- Act ---
        $this->fetcher->fetchState($request, 567, );
    }
}

/**
 * Helper interface to represent an SDK entity for mocking state retrieval.
 */
interface DefaultStateFetcherTestMockEntity
{
    /**
     * Retrieves the state of the mocked entity.
     *
     * @return string The state of the entity.
     */
    public function getState(): string;
}
