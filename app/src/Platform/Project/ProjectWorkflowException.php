<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

use RuntimeException;

final class ProjectWorkflowException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message === '' ? $reason : $message);
    }
}
