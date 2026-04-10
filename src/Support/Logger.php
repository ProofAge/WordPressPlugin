<?php

namespace ProofAge\WordPress\Support;

final class Logger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $message, array $context = []): void
    {
        $options = Options::all();

        if (! ($options['logging_enabled'] ?? false) && ! ($options['debug_mode'] ?? false)) {
            return;
        }

        $payload = [
            'message' => $message,
            'context' => $this->maskContext($context),
        ];

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info(wp_json_encode($payload), ['source' => 'proofage-age-verification']);

            return;
        }

        error_log('[proofage-age-verification] ' . wp_json_encode($payload));
    }

    public function error(string $message, array $context = []): void
    {
        $this->log($message, array_merge(['level' => 'error'], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @return array<string, mixed>
     */
    private function maskContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->maskContext($value);
                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $normalizedKey = strtolower((string) $key);

            if (str_contains($normalizedKey, 'secret') || str_contains($normalizedKey, 'signature')) {
                $context[$key] = $this->maskString((string) $value);
            }

            if (str_contains($normalizedKey, 'api_key')) {
                $context[$key] = $this->maskString((string) $value);
            }
        }

        return $context;
    }

    private function maskString(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }
}
