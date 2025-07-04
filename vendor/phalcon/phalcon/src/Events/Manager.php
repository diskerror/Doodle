<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Events;

use Closure;
use SplPriorityQueue;

use function call_user_func_array;
use function is_callable;
use function is_object;
use function method_exists;

/**
 * Phalcon Events Manager, offers an easy way to intercept and manipulate, if
 * needed, the normal flow of operation. With the EventsManager the developer
 * can create hooks or plugins that will offer monitoring of data, manipulation,
 * conditional execution and much more.
 */
class Manager implements ManagerInterface
{
    /**
     * @var bool
     */
    protected bool $collect = false;

    /**
     * @var bool
     */
    protected bool $enablePriorities = false;

    /**
     * @var array
     */
    protected array $events = [];

    /**
     * @var array<array-key, mixed>
     */
    protected array $responses = [];

    /**
     * Returns if priorities are enabled
     *
     * @return bool
     */
    public function arePrioritiesEnabled(): bool
    {
        return $this->enablePriorities;
    }

    /**
     * Attach a listener to the events manager
     *
     * @param string          $eventType
     * @param object|callable $handler
     * @param int             $priority
     */
    public function attach(
        string $eventType,
        callable | object $handler,
        int $priority = self::DEFAULT_PRIORITY
    ): void {
        /** @var SplPriorityQueue|null $priorityQueue */
        $priorityQueue = $this->events[$eventType] ?? null;
        if (null === $priorityQueue) {
            // Create a SplPriorityQueue to store the events with priorities
            $priorityQueue = new SplPriorityQueue();

            // Set extraction flags to extract only the Data
            $priorityQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

            // Append the events to the queue
            $this->events[$eventType] = $priorityQueue;
        }

        if (true !== $this->enablePriorities) {
            $priority = self::DEFAULT_PRIORITY;
        }

        // Insert the handler in the queue
        $priorityQueue->insert($handler, $priority);
    }

    /**
     * Tells the event manager if it needs to collect all the responses returned
     * by every registered listener in a single fire
     *
     * @param bool $collect
     */
    public function collectResponses(bool $collect): void
    {
        $this->collect = $collect;
    }

    /**
     * Detach the listener from the events manager
     *
     * @param string          $eventType
     * @param object|callable $handler
     *
     * @return void
     * @throws Exception
     */
    public function detach(string $eventType, object | callable $handler): void
    {
        /** @var SplPriorityQueue|null $priorityQueue */
        $priorityQueue = $this->events[$eventType] ?? null;
        if (null !== $priorityQueue) {
            /**
             * SplPriorityQueue doesn't have a method for element deletion so we
             * need to rebuild the queue
             */
            $newPriorityQueue = new SplPriorityQueue();

            $newPriorityQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
            $priorityQueue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
            $priorityQueue->top();

            while ($priorityQueue->valid()) {
                $data = $priorityQueue->current();
                $priorityQueue->next();

                if ($handler !== $data['data']) {
                    $newPriorityQueue->insert($data['data'], $data['priority']);
                }
            }

            $this->events[$eventType] = $newPriorityQueue;
        }
    }

    /**
     * Removes all events from the EventsManager
     *
     * @param string|null $type
     */
    public function detachAll(string | null $type = null): void
    {
        $this->processDetachAllNullType($type);
        $this->processDetachAllNotNullType($type);
    }

    /**
     * Set if priorities are enabled in the EventsManager.
     *
     * A priority queue of events is a data structure similar
     * to a regular queue of events: we can also put and extract
     * elements from it. The difference is that each element in a
     * priority queue is associated with a value called priority.
     * This value is used to order elements of a queue: elements
     * with higher priority are retrieved before the elements with
     * lower priority.
     *
     * @param bool $enablePriorities
     */
    public function enablePriorities(bool $enablePriorities): void
    {
        $this->enablePriorities = $enablePriorities;
    }

    /**
     * Fires an event in the events manager causing the active listeners to be
     * notified about it
     *
     *```php
     * $eventsManager->fire("db", $connection);
     *```
     *
     * @param string     $eventType
     * @param object     $source
     * @param mixed|null $data
     * @param bool       $cancelable
     *
     * @return mixed
     * @throws Exception
     */
    public function fire(
        string $eventType,
        object $source,
        mixed $data = null,
        bool $cancelable = true
    ): mixed {
        if (empty($this->events)) {
            return null;
        }

        // All valid events must have a colon separator
        if (!str_contains($eventType, ':')) {
            throw new Exception('Invalid event type ' . $eventType);
        }

        $eventParts = explode(':', $eventType);
        $type       = $eventParts[0];
        $eventName  = $eventParts[1];
        $status     = null;

        // Responses must be traced?
        if (true === $this->collect) {
            $this->responses = [];
        }

        // Create the event context
        $event = new Event($eventName, $source, $data, $cancelable);

        // Check if events are grouped by type
        $fireEvents = $this->events[$type] ?? null;
        if (is_object($fireEvents)) {
            // Call the events queue
            $status = $this->fireQueue($fireEvents, $event);
        }

        // Check if there are listeners for the event type itself
        $fireEvents = $this->events[$eventType] ?? null;
        if (is_object($fireEvents)) {
            // Call the events queue
            $status = $this->fireQueue($fireEvents, $event);
        }

        return $status;
    }

    /**
     * Internal handler to call a queue of events
     *
     * @param SplPriorityQueue $queue
     * @param EventInterface   $event
     *
     * @return mixed
     */
    final public function fireQueue(
        SplPriorityQueue $queue,
        EventInterface $event
    ): mixed {
        $status = null;

        // Tell if the event is cancelable
        $cancelable = $event->isCancelable();

        // Responses need to be traced?
        $collected = $this->collect;

        // We need to clone the queue before iterate over it
        $iterator = clone $queue;

        // Move the queue to the top
        $iterator->top();

        while ($iterator->valid()) {
            // Get the current data
            $handler = $iterator->current();

            $iterator->next();

            // Only handler objects are valid
            if (true === $this->isValidHandler($handler)) {
                $status = $this->checkFireHandlerClosure($status, $handler, $event);
                $status = $this->checkFireHandlerMethod($status, $handler, $event);

                // Trace the response
                if (true === $collected) {
                    $this->responses[] = $status;
                }

                // Check if the event was stopped by the user
                if (true === $cancelable && true === $event->isStopped()) {
                    break;
                }
            }
        }

        return $status;
    }

    /**
     * Returns all the attached listeners of a certain type
     *
     * @param string $type
     *
     * @return array<array-key, mixed>
     */
    public function getListeners(string $type): array
    {
        $listeners  = [];
        $fireEvents = $this->events[$type] ?? null;
        if (null !== $fireEvents) {
            $priorityQueue = clone $fireEvents;
            $priorityQueue->top();

            while ($priorityQueue->valid()) {
                $listeners[] = $priorityQueue->current();
                $priorityQueue->next();
            }
        }

        return $listeners;
    }

    /**
     * Returns all the responses returned by every handler executed by the last
     * 'fire' executed
     *
     * @return array<array-key, mixed>
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Check whether certain type of event has listeners
     *
     * @param string $type
     *
     * @return bool
     */
    public function hasListeners(string $type): bool
    {
        return isset($this->events[$type]);
    }

    /**
     * Check if the events manager is collecting all all the responses returned
     * by every registered listener in a single fire
     *
     * @return bool
     */
    public function isCollecting(): bool
    {
        return $this->collect;
    }

    /**
     * @param mixed $handler
     *
     * @return bool
     */
    public function isValidHandler(mixed $handler): bool
    {
        if (!is_object($handler) && !is_callable($handler)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed          $status
     * @param mixed          $handler
     * @param EventInterface $event
     *
     * @return false|mixed
     */
    private function checkFireHandlerClosure(
        mixed $status,
        mixed $handler,
        EventInterface $event
    ): mixed {
        // Check if the event is a closure
        if ($handler instanceof Closure || is_callable($handler)) {
            // Call the function in the PHP userland
            $status = call_user_func_array(
                $handler,
                [
                    $event,
                    $event->getSource(),
                    $event->getData(),
                ]
            );
        }

        return $status;
    }

    /**
     * @param mixed          $status
     * @param mixed          $handler
     * @param EventInterface $event
     *
     * @return mixed
     */
    private function checkFireHandlerMethod(
        mixed $status,
        mixed $handler,
        EventInterface $event
    ): mixed {
        $eventName = $event->getType();

        // Check if the listener has implemented an event with the same name
        if (
            true !== ($handler instanceof Closure || is_callable($handler)) &&
            true === method_exists($handler, $eventName)
        ) {
            $status = $handler->{$eventName}(
                $event,
                $event->getSource(),
                $event->getData()
            );
        }

        return $status;
    }

    /**
     * @param string|null $type
     */
    private function processDetachAllNotNullType(string | null $type): void
    {
        if (null !== $type && isset($this->events[$type])) {
            unset($this->events[$type]);
        }
    }

    /**
     * @param string|null $type
     */
    private function processDetachAllNullType(string | null $type): void
    {
        if (null === $type) {
            $this->events = [];
        }
    }
}
