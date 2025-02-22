# Betting System

This repository contains a betting system implementation with factories, managers, and status processors for different betting platforms.

### Core Components

- **BetFactory.php** - A factory class for creating bet-related objects.
- **BetManager.php** - Manages bet operations such as placing, updating, and validating bets.
- **BetRequestFactory.php** - Generates standardized bet request objects.

### Bet Status Processing (BetStatus Folder)

- **BetStatusProcessor.php** - Handles the processing of bet statuses.
- **StatusChangeAction.php** - Defines actions that occur during status changes.
- **StatusChanger.php** - Manages state transitions for bets.
- **StatusTransitionHandler.php** - Ensures smooth status transitions.

### Bet Status Handlers (BetStatus/Handlers Folder)

- **AbstractStatusHandler.php** - Base class for status handlers.
- **StatusHandlerAccepted.php** - Handles accepted bet status.
- **StatusHandlerCancelled.php** - Handles cancelled bet status.
- **StatusHandlerFailed.php** - Handles failed bet status.
- **StatusHandlerInterface.php** - Defines a common interface for status handlers.
- **StatusHandlerRefund.php** - Handles refund bet status.

### Status Processing

- **StatusProcessorInterface.php** - Defines a common interface for bet status processing.
- **StatusProcessorISN.php** - Implements status processing for ISN (International Sports Network).
- **StatusProcessorPinnacle.php** - Implements status processing for Pinnacle.
