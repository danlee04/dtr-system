<?php

namespace App\Services\Attendance;

use RuntimeException;

/**
 * A file the IT Unit's DTR layout cannot be read from. The message says why,
 * in words HR can act on, because it is shown to them as it is.
 */
class UnreadableDtr extends RuntimeException
{
    //
}
