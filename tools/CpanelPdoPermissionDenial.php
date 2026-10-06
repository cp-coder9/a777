<?php
declare(strict_types=1);
/** Recognize driver denials, including the exact ConnectionFactory wrapper. */
final class CpanelPdoPermissionDenial
{
    public static function isDenied(Throwable $error, array $allowedCodes): bool
    {
        $driver = $error;
        if (!$driver instanceof PDOException) {
            if (get_class($error) !== RuntimeException::class
                || $error->getMessage() !== 'Database connection failed.'
                || $error->getCode() !== 0
                || !$error->getPrevious() instanceof PDOException) return false;
            $driver = $error->getPrevious();
        }
        $code = $driver->errorInfo[1] ?? null;
        if (!is_int($code) || !in_array($code, [1044,1045,1142,1143], true)) return false;
        return in_array($code, $allowedCodes, true);
    }
}
