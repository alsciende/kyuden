<?php

declare(strict_types=1);

namespace App\Task;

/**
 * A task of the worker: a message sent to the transport async (config/packages/messenger.yaml) on task.bus, then
 * handled by the worker (messenger:consume async), out of the requests. Its handler sits beside it in this namespace,
 * registered on task.bus only (#[AsMessageHandler(bus: 'task.bus')]).
 *
 * A task holds ids, not entities, since it is serialized; its handler reads what it needs again, the state having
 * possibly changed since it was sent. A task may be handled more than once (a worker killed while handling it, a retry):
 * its handler does nothing when there is nothing left to do.
 */
interface TaskInterface
{
}
