<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

use RuntimeException;

final class UpdateAlreadyRunningException extends RuntimeException {}
