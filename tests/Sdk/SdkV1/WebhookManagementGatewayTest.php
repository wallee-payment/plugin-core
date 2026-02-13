<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\WebhookManagementGateway;
use Wallee\PluginCore\Webhook\Enum\WebhookListener as WebhookListenerEnum;
use Wallee\PluginCore\Webhook\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookUrl;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\WebhookListener as SdkWebhookListener;
use Wallee\Sdk\Model\WebhookListenerCreate as SdkWebhookListenerCreate;
use Wallee\Sdk\Model\WebhookListenerUpdate;
use Wallee\Sdk\Model\WebhookUrl as SdkWebhookUrl;
use Wallee\Sdk\Model\WebhookUrlCreate as SdkWebhookUrlCreate;
use Wallee\Sdk\Model\WebhookUrlUpdate;
use Wallee\Sdk\Service\WebhookListenerService as SdkWebhookListenerService;
use Wallee\Sdk\Service\WebhookUrlService as SdkWebhookUrlService;

class WebhookManagementGatewayTest extends TestCase
{
    private WebhookManagementGateway $gateway;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkWebhookUrlService $urlService;
    private MockObject|SdkWebhookListenerService $listenerService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->urlService = $this->createMock(SdkWebhookUrlService::class);
        $this->listenerService = $this->createMock(SdkWebhookListenerService::class);

        $this->sdkProvider->method('getService')
            ->willReturnMap([
                [SdkWebhookUrlService::class, $this->urlService],
                [SdkWebhookListenerService::class, $this->listenerService],
            ]);

        $this->gateway = new WebhookManagementGateway($this->sdkProvider, $this->logger);
    }

    public function testCreateUrl(): void
    {
        $spaceId = 1;
        $url = 'http://test.com';
        $name = 'Test URL';

        $sdkUrl = new SdkWebhookUrl();
        $sdkUrl->setId(100);

        $this->urlService->expects($this->once())
            ->method('create')
            ->with($this->equalTo($spaceId), $this->callback(function (SdkWebhookUrlCreate $create) use ($url, $name) {
                return $create->getUrl() === $url &&
                    $create->getName() === $name &&
                    $create->getState() === CreationEntityState::ACTIVE;
            }))
            ->willReturn($sdkUrl);

        $id = $this->gateway->createUrl($spaceId, $url, $name);

        $this->assertEquals(100, $id);
    }

    /**
     * Tests that createListener accepts WebhookListenerEnum and array of states,
     * and correctly maps the enum's int value to SDK v1's setEntity().
     */
    public function testCreateListener(): void
    {
        $spaceId = 1;
        $urlId = 100;
        // Use the enum — its backing int value is the entity ID for SDK v1
        $entityEnum = WebhookListenerEnum::PAYMENT_CONNECTOR_CONFIGURATION;
        $entityId = $entityEnum->value; // 1472041843695
        $stateId = 'SUCCESSFUL';
        $name = 'Listener';

        $sdkListener = new SdkWebhookListener();
        $sdkListener->setId(200);

        $this->listenerService->expects($this->once())
            ->method('create')
            ->with($this->equalTo($spaceId), $this->callback(function (SdkWebhookListenerCreate $create) use ($urlId, $entityId, $stateId, $name) {
                return $create->getUrl() === $urlId &&
                    $create->getEntity() === $entityId &&
                    $create->getEntityStates() === [$stateId] &&
                    $create->getName() === $name;
            }))
            ->willReturn($sdkListener);

        // Call with enum + array (new interface signature)
        $id = $this->gateway->createListener($spaceId, $urlId, $entityEnum, [$stateId], $name);

        $this->assertEquals(200, $id);
    }

    public function testUpdateUrl(): void
    {
        $spaceId = 1;
        $id = 100;
        $newUrl = 'http://updated.com';

        $currentUrl = new SdkWebhookUrl();
        $currentUrl->setId($id);
        $currentUrl->setVersion(10);

        $this->urlService->expects($this->once())->method('read')->with($spaceId, $id)->willReturn($currentUrl);

        $this->urlService->expects($this->once())
            ->method('update')
            ->with($this->equalTo($spaceId), $this->callback(function (WebhookUrlUpdate $update) use ($id, $newUrl) {
                return $update->getId() === $id &&
                    $update->getUrl() === $newUrl &&
                    $update->getVersion() === 10;
            }));

        $this->gateway->updateUrl($spaceId, $id, $newUrl);
    }

    /**
     * Tests that updateListener accepts WebhookListenerEnum and array of states,
     * and correctly passes the event states to SDK v1.
     */
    public function testUpdateListener(): void
    {
        $spaceId = 1;
        $id = 200;
        // Use the enum — SDK v1 update does not change the entity, but the
        // interface still requires it for consistency with v2
        $entityEnum = WebhookListenerEnum::PAYMENT_CONNECTOR_CONFIGURATION;
        $newState = 'FAILED';

        $currentListener = new SdkWebhookListener();
        $currentListener->setId($id);
        $currentListener->setVersion(20);

        $this->listenerService->expects($this->once())->method('read')->with($spaceId, $id)->willReturn($currentListener);

        $this->listenerService->expects($this->once())
            ->method('update')
            ->with($this->equalTo($spaceId), $this->callback(function (WebhookListenerUpdate $update) use ($id, $newState) {
                return $update->getId() === $id &&
                    $update->getEntityStates() === [$newState] &&
                    $update->getVersion() === 20;
            }));

        // Call with enum + array (new interface signature)
        $this->gateway->updateListener($spaceId, $id, $entityEnum, [$newState]);
    }

    public function testDeleteUrl(): void
    {
        $this->urlService->expects($this->once())->method('delete')->with(1, 100);
        $this->gateway->deleteUrl(1, 100);
    }

    public function testDeleteListener(): void
    {
        $this->listenerService->expects($this->once())->method('delete')->with(1, 200);
        $this->gateway->deleteListener(1, 200);
    }

    /**
     * Tests that getWebhookListeners returns typed WebhookListener DTOs
     * instead of raw SDK objects.
     */
    public function testGetWebhookListeners(): void
    {
        $spaceId = 1;
        $urlId = 100;

        $listener = new SdkWebhookListener();
        $listener->setId(200);
        $listener->setName('Test Listener');

        $this->listenerService->expects($this->once())
            ->method('search')
            ->with($this->equalTo($spaceId), $this->callback(function ($query) use ($urlId) {
                $filter = $query->getFilter();
                return $filter instanceof EntityQueryFilter &&
                    $filter->getFieldName() === 'url.id' &&
                    $filter->getValue() === $urlId;
            }))
            ->willReturn([$listener]);

        $results = $this->gateway->getWebhookListeners($spaceId, $urlId);

        $this->assertCount(1, $results);
        // Assert the returned object is a domain DTO, not an SDK object
        $this->assertInstanceOf(WebhookListener::class, $results[0]);
        $this->assertEquals(200, $results[0]->id);
        $this->assertEquals('Test Listener', $results[0]->name);
    }

    /**
     * Tests that getUrl reads a single webhook URL from SDK v1
     * and returns a typed WebhookUrl DTO.
     */
    public function testGetUrl(): void
    {
        $spaceId = 1;
        $webhookUrlId = 100;

        $sdkUrl = new SdkWebhookUrl();
        $sdkUrl->setId($webhookUrlId);
        $sdkUrl->setName('Test URL');
        $sdkUrl->setUrl('http://test.com');
        $sdkUrl->setState(CreationEntityState::ACTIVE);

        $this->urlService->expects($this->once())
            ->method('read')
            ->with($spaceId, $webhookUrlId)
            ->willReturn($sdkUrl);

        $result = $this->gateway->getUrl($spaceId, $webhookUrlId);

        // Assert the returned object is a domain DTO, not an SDK object
        $this->assertInstanceOf(WebhookUrl::class, $result);
        $this->assertEquals($webhookUrlId, $result->id);
        $this->assertEquals('Test URL', $result->name);
        $this->assertEquals('http://test.com', $result->url);
    }
}
