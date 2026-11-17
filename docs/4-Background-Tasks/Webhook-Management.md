# Webhook Management

The Webhook Management module allows developers to programmatically manage webhook subscriptions in the Wallee Portal and validate incoming payloads.

## Overview

A webhook consists of two parts in the Wallee Portal:

1. **Webhook URL**: The endpoint where the Wallee Portal will send notifications.
2. **Webhook Listener**: The rule that defines which entity and state changes trigger a notification to a specific URL.

The module provides a unified `WebhookService` to handle these entities.

## Key Components

- **WebhookService**: Orchestrates installation (URL + Listener), synchronization against your listener registry, uninstallation, and updates.
- **WebhookConfig**: DTO carrying configuration data (URL, name, entity ID, state ID).
- **WebhookManagementGatewayInterface**: Connects `WebhookService` to the API. Pass it in when you create the service, then use the service for every call.
- **WebhookSignatureGatewayInterface**: Validates payload signatures during webhook processing.

## Installation Flow

When installing a webhook, the service:

1. Creates the **Webhook URL** definition.
2. Uses the resulting ID to create a **Webhook Listener**.

```php
$config = new WebhookConfig(
    url: 'https://your-shop.com/webhook/callback', name: 'Order Authorization Listener',
    entity: WebhookListener::TRANSACTION, eventStates: [TransactionState::AUTHORIZED->value],
);
$webhookUrl = $webhookService->installWebhook($spaceId, $config); // returns the created WebhookUrl
```

👉 **See this in action:** [manage_webhooks.php](../examples/4-Background-Tasks/manage_webhooks.php)

## Synchronizing Listeners from Your Registry

`synchronizeWebhooks()` subscribes the space to exactly the webhooks your plugin handles. Pass it the `WebhookListenerRegistry` you build for the [Webhook Processor](Webhook-Processor.md): it finds the webhook URL by its address, or creates it, then makes sure there is one listener per registered entity, listening to the states you registered for that entity. Run it when the plugin is installed or upgraded, or when the merchant saves the configuration.

```php
$webhookService->synchronizeWebhooks(
    $spaceId,
    'https://your-shop.com/webhook/callback',
    'My Plugin', // prefix for the URL name and the listener names
    $registry,
);
```

Listeners that already exist are left alone, so running it again is safe. After registering new states for an entity, pass `force: true` to recreate its listener with the new state list.

👉 **See this in action:** [sync_webhooks.php](../examples/4-Background-Tasks/sync_webhooks.php)

## Management Operations

### Updating the URL

If you need to move your endpoint, you can update the URL definition. The implementation handles the required **Optimistic Locking** (Read-Modify-Write) automatically.

```php
$webhookService->updateWebhookUrl($spaceId, $webhookUrlId, 'https://new-url.com/callback');
```

### Uninstallation

Deletes the listener, then the URL definition. If listener deletion fails, the operation stops there and the URL is left in place, so you can safely retry.

```php
$webhookService->uninstallWebhook($spaceId, $webhookUrlId, $listenerId);
```

## Usage Example

See the [example](../examples/4-Background-Tasks) directory for a fully working CLI script that demonstrates the full lifecycle:

1. Creating a Webhook.
2. Updating its URL.
3. Cleaning up (Uninstallation).

> [!TIP]
> Use the `WebhookConfig` to manage different states (e.g., FAILED, SUCCESSFUL) by creating multiple listeners pointing to the same Webhook URL.
