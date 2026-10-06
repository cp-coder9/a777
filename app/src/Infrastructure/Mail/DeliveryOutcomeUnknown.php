<?php
declare(strict_types=1);
namespace Architex\Infrastructure\Mail;

/** Sending DATA began, but no conclusive acceptance was received. Requires review. */
final class DeliveryOutcomeUnknown extends \RuntimeException {}
