<?php

namespace App\Actions\Conciliation;

use RuntimeException;

/**
 * A business rule refused the action; the message is shown to the user as is.
 */
class ActionRefusedException extends RuntimeException {}
