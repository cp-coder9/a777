<?php
declare(strict_types=1);
namespace Architex\Platform\Environment;
use RuntimeException;
final class RegulatoryCaseException extends RuntimeException{public function __construct(public readonly string$reason){parent::__construct($reason);}}
