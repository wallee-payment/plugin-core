<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook;

use Wallee\PluginCore\Http\Request;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Enum\WebhookListener as WebhookListenerEnum;
use Wallee\PluginCore\Webhook\Exception\CommandException;
use Wallee\PluginCore\Webhook\Exception\SkippedStepException;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerRegistry;

class WebhookProcessor
{
    public function __construct(
        private readonly WebhookListenerRegistry $listenerRegistry,
        private readonly StateValidator $stateValidator,
        private readonly WebhookLifecycleHandler $lifecycleHandler,
        private readonly StateFetcherInterface $stateFetcher,
        private readonly LoggerInterface $logger,
    ) { }

    public function process(Request $request): void
    {
        $context = null;
        $webhookListener = null;

        try {
            $technicalName = $request->get('listenerEntityTechnicalName');
            $entityId = (int)$request->get('entityId');
            $spaceId = (int)$request->get('spaceId');

            if (!$technicalName || !$entityId || !$spaceId) {
                // This IS an invalid argument (bad payload), so throwing here is fine.
                // It will be caught below and logged as a warning.
                throw new \InvalidArgumentException('Request body is missing required fields (technicalName, entityId, or spaceId).');
            }

            $remoteState = $this->stateFetcher->fetchState($request, $entityId);
            $webhookListener = WebhookListenerEnum::fromTechnicalName($technicalName);
            $lastProcessedState = $this->lifecycleHandler->getLastProcessedState($webhookListener, $entityId);
            
            $transitionPath = $this->stateValidator->getTransitionPath($webhookListener, $lastProcessedState, $remoteState);

            if ($transitionPath === null) {
                // This is often a normal "stale" webhook (e.g. AUTHORIZED arriving after FULFILL).
                $this->logger->debug(
                    sprintf(
                        'State transition from "%s" to "%s" is not possible or already passed. Ignoring webhook for entity %s/%d.', 
                        $lastProcessedState, 
                        $remoteState,
                        $technicalName, 
                        $entityId
                    )
                );
                return;
            }

            // Duplicate / No Action Needed
            if (empty($transitionPath)) {
                $this->logger->debug(sprintf('Webhook for entity %s/%d already processed. Ignoring duplicate.', $technicalName, $entityId));
                return;
            }

            // Valid Path
            $this->logger->info(sprintf('Processing transition path for entity %s/%d from %s to %s: [%s]', $technicalName, $entityId, $lastProcessedState, $remoteState, implode(' -> ', $transitionPath)));

            $currentStateInLoop = $lastProcessedState;
            
            foreach ($transitionPath as $stateToProcess) {
                $context = new WebhookContext($stateToProcess, $currentStateInLoop, $entityId, $spaceId);

                $shouldProceed = $this->lifecycleHandler->preProcess($webhookListener, $context);
                    
                if (!$shouldProceed) {
                    $this->logger->debug(sprintf('Race condition: Step %s/%s already processed. Skipping.', $technicalName, $stateToProcess));
                    // We must still call onFailure to roll back the (empty) transaction and release the lock
                    $this->lifecycleHandler->onFailure($webhookListener, $context, new SkippedStepException('Skipped due to race condition.'));
                    $currentStateInLoop = $stateToProcess; 
                    continue;
                }

                $commandResult = null;
                $listener = $this->listenerRegistry->findListener($webhookListener, $stateToProcess);
                
                if ($listener !== null) {
                    $this->logger->debug(sprintf('Processing step: %s/%s (Listener found)', $technicalName, $stateToProcess));
                    $command = $listener->getCommand($context);
                    $commandResult = $command->execute();
                } else {
                    $this->logger->debug(sprintf('Processing step: %s/%s (No listener registered, skipping command)', $technicalName, $stateToProcess));
                }

                $this->lifecycleHandler->postProcess($webhookListener, $context, $commandResult);

                $currentStateInLoop = $stateToProcess;
            }

        } catch (\InvalidArgumentException $e) {
            // This catches the missing fields in the request error
            $this->logger->warning('Webhook validation failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // "Failure" hook: Rollback, release lock
            if ($context && $webhookListener) {
                $this->lifecycleHandler->onFailure($webhookListener, $context, $e);
            }
            $this->logger->error('Webhook processing failed: ' . $e->getMessage(), ['exception' => $e]);
            
            // We re-throw CommandException so the Controller sends a 500 error
            // This causes the Portal to retry later (which is what we want for system errors like DB connection fails)
            throw new CommandException('Webhook command execution failed.', previous: $e);
        }
    }

    public function getListenerRegistry(): WebhookListenerRegistry
    {
        return $this->listenerRegistry;
    }
}
