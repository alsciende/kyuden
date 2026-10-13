# Web Client

## Goal

Build an engine to play Legends of the Five Rings LCG as published by Fantasy Flight Games.

## MVP features

- the engine will act as a board on which to play the game
- enforce the game workflow of turns, phases and steps
- do not enforce any game rules regarding cards

## Architecture

### Entities

`App\Entity\LogicalCard` is what we list in a pack or a decklist. It holds the game information about a card: title, cost, text, etc.

`App\Entity\LogicalCard` are grouped in `App\Entity\Pack` by `App\Entity\Printing`.

`App\Entity\PhysicalCard` is an occurrence of a card that can be in multiples in a deck. This is the card that is drawn, played, discarded, etc.

`App\Entity\PhysicalCard` are associated to a `App\Entity\Player` in a `App\Entity\Game`.

`App\Entity\PhysicalCard` is a mapped superclass on the actual card types in `src/Entity/CardTypes`.

`App\Entity\PhysicalCard` in play are organized in `App\Entity\Stack`: ordered collection of cards.

`App\Entity\Deck` is a collection of card codes that should refer to known `App\Entity\LogicalCard`. When a game starts, the deck is used to generate the `App\Entity\PhysicalCard` set.



### User Events and Action Messages

- Events are received by the `App\Controller\EventController`, converted to messages, and dispatched to the bus (TODO)
- Messages are instances of `App\Message\ActionInterface` and use the attribute `App\Attribute\AppAction`.
- This attribute is used to add a resource tag to the message and make them discoverable by the `App\Service\ActionMessageRegistry` service.
- Messages are routed to the middlewares in the order they are listed in messenger.yaml, but each middleware starts by forwarding the message down the chain:
  - doctrine_transaction (`\Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware`)
  - `App\Middleware\WorkflowTransitionMiddleware`
  - `App\Middleware\EventRecordingMiddleware`
- Messages are routed to their main handler in src/MessageHandler, which changes the game state accordingly
- `App\Middleware\EventRecordingMiddleware` saves an event to the database to record the handling of that message
- `App\Middleware\WorkflowTransitionMiddleware` checks if any workflow transition can happen automatically
- doctrine_transaction middleware commits everything to the database
- `App\Controller\EventController` sends a response (TODO)

### Workflow

There are 6 workflows, defined in `config/packages/workflows.php`, one for `App\Entity\Game` and one for every subclass of `App\Entity\PhysicalCard`. They are state_machine, meaning one state at a time.

The place of a card is not stored: it is computed from the type of its `App\Entity\Stack` and its `App\Enum\Orientation` (see `App\Workflow\CardMarkingStore`). Card transitions describe the move or turn they make in their metadata (`stack`, `orientation`), applied by `App\Workflow\CardTransitionListener`. A card is created with the initial orientation of its type; a transition that moves a card always gives its orientation.

The places of the `game` workflow where players can trigger action abilities carry the `action_window` metadata. The `trigger_action` transition of a card workflow (a transition from a place to itself) lists where the abilities of a card can be used.

Engine will listen to changes in the workflow with EventListeners located in `src/Workflow`. Some will try to block a transition (GuardListener), some will react to a transition (TransitionListener), etc.

Audit trails are enabled, generating logs in the `workflow` channel.

### State

`App\Entity\Game`, `App\Entity\Player` and `App\Entity\PhysicalCard` entities have a `state` field which is a JSON representation of their state. They implement the `App\State\Model\StatefulInterface` interface.

Services `App\State\Manager\GameStateManager`, `App\State\Manager\PlayerStateManager` and `App\State\Manager\CardStateManager` decode this field to a `App\State\Model\GameState`, `App\State\Model\PlayerState` and `App\State\Model\CardState` object respectively.

The main way to interact with the state is via their `withState` methods, which decode the state to a closure callback and encode it afterward.

### Notifications

When users need to be notified of changes, a `App\Notification\NotificationInterface` object is issued to a `App\Notifier\NotifierInterface` service.

The main `App\Notifier\NotifierInterface` service is `App\Notifier\MercureNotifier` which sends notifications as server-sent events via a Mercure hub.
