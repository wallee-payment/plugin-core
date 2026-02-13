# Wallee Plugin Core Library

**The canonical, framework-agnostic business logic engine for Wallee payment integrations.**

This library abstracts the complexity of the Wallee SDK and provides a standardized, robust implementation of payment flows. It is designed to be used as a core dependency by platform-specific plugins (Magento, WooCommerce, Shopware, etc.), decoupling **business logic** from **platform infrastructure**.

---

## Core Philosophy

The goal of this project is to centralize all payment business logic into a single, reusable library, decoupling it from the specific constraints of platforms like Magento or WooCommerce.

Instead of duplicating complex logic across different shop systems, `plugin-core` implements the payment workflows once, using pure PHP. This shifts the role of the shop-specific plugin:

* **Plugin Core:** Implements the business logic, manages state machines, and handles all API interactions via the SDK.
* **Shop Plugin:** Acts as an **adapter**. It interchanges data between the shop and the Core, handles database persistence, manages configuration, and integrates into the shop's frontend/backend events.

### Key Architectural Benefits
* **Pure PHP:** Framework-agnostic code that runs anywhere PHP runs.
* **Minimal Dependencies:** Depends only on the official `wallee/php-sdk`, making it lightweight and easy to port to any environment.
* **Type Safety:** Written with strict typing to catch errors early.
* **Testability:** Designed for 100% unit test coverage with isolated components.
* **PSR Standards:** Fully compliant with PSR-3 (Logging) and other standard interfaces.
* **Contract-Driven:** Clear Interfaces and Abstract Base Classes guide developers to implement the necessary platform-specific adapters correctly.
---

## Key Features

The library is divided into major functional components.

### 1. Webhook Processing
A robust engine for handling asynchronous events from the Wallee Portal. It is designed to handle **high-concurrency** environments and **out-of-order** delivery without data corruption.

* **Self-Healing State Machine:** Automatically detects missing or out-of-order webhooks and "catches up" the local state to match the remote reality.
* **Concurrency Safe:** Implements a sophisticated, two-stage locking strategy (Entity + Resource) to prevent race conditions between different webhook types modifying the same order.
* **Idempotent:** Intelligently handles duplicate events without redundant processing.
* **Data Integrity Guard:** Enforces "Safe Updates" by checking for protected states (e.g., preventing an automated process from overwriting a manual review status).

### 2. Portal Synchronization (Planned)
*Future implementation.*

### 3. Transaction Management (Planned)
*Future implementation.*
---

## Documentation

### 1. Webhook Processing
Everything you need to implement the robust, concurrent-safe webhook engine.

* **[Integration Guide](docs/Webhook/README.md):** A step-by-step guide to implementing the `WebhookProcessor` and its required adapters.
* **[Architecture Overview](docs/Webhook/ARCHITECTURE.md):** A deep dive into the concurrency, locking, and state management strategies.
* **[Running Example](docs/Webhook/example/):** A complete, runnable PHP implementation (CLI) showing how to wire up the Processor, Lifecycle Handler, and Commands. Use this as a reference blueprint before diving into a complex shop integration.

---

## Installation

```bash
composer require wallee/plugin-core
```

---

## Unit Tests
You can run the test suite to verify the library's behavior.

```bash
composer test
```

## License
[License Information Here]
