<?php
/**
 * Application logging and error handlers
 */

function app_log(string $level, string $message): void
{
    $dir = STORAGE_PATH . '/logs';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function register_app_error_handlers(): void
{
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        app_log('PHP', "{$message} in {$file}:{$line}");
        return false;
    });

    set_exception_handler(static function (Throwable $e): void {
        app_log('EXCEPTION', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "Application error: " . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        echo 'An unexpected error occurred. Please try again later.';
        exit;
    });
}
