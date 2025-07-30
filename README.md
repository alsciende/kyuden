# Web Client

## Goal

Build an engine to play Legends of the Five Rings LCG as published by Fantasy Flight Games.

## MVP features

- the engine will act as a board on which to play the game
- enforce the game workflow of turns, phases and steps
- do not enforce any game rules regarding cards

## Architecture

### User Events and Action Messages

- Events are received by the `App\Controller\EventController`, converted to messages, and dispatched to the bus (TODO)
- Messages are instances of `App\Message\ActionInterface`
- Messages are routed to the middlewares in the order they are listed in messenger.yaml, but each middleware starts by forwarding the message down the chain:
  - doctrine_transaction
  - `App\Middleware\WorkflowTransitionMiddleware`
  - `App\Middleware\EventRecordingMiddleware`
- Messages are routed to their main handler in src/MessageHandler, which changes the game state accordingly
- `App\Middleware\EventRecordingMiddleware` saves an event to the database to record the handling of that message
- `App\Middleware\WorkflowTransitionMiddleware` checks if any workflow transition can happen automatically
- doctrine_transaction middleware commits everything to the database
- `App\Controller\EventController` sends a response (TODO)

### Workflow

There are 2 workflow, one for `App\Entity\Game` and one for `App\Entity\PhysicalCard`. Both are state_machine, meaning one state at a time.

Engine will listen to changes in the workflow with EventListeners located in `src/Workflow`. Some will try to block a transition (GuardListener), some will react to a transition (TransitionListener), etc.

Audit trails are enabled, generating logs in the `workflow` channel.

### State

`App\Entity\Game` and `App\Entity\Player` entities have a `state` field which is a JSON representation of their state.

Services `App\Service\GameStateManager` and `App\Service\PlayerStateManager` decode this field to a `App\State\GameState` and `App\State\PlayerState` object respectively.

The main way to interact with the state is via their `withState` methods, which decode the state to a closure callback and encode it afterward.

### Notifications

When users need to be notified of changes, a `App\Notification\NotificationInterface` object is issued to a `App\Notifier\NotifierInterface` service.

The main `App\Notifier\NotifierInterface` service is `App\Notifier\MercureNotifier` which sends notifications as server-sent events via a Mercure hub.

### Framework Events

Framework events are instances of `\App\Event\FrameworkEventInterface`.

They describe an event in the game framework.

I can't remember what they're for at the moment. They seem redundant with the game workflow.
