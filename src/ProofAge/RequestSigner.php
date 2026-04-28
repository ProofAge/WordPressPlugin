<?php

namespace ProofAge\WordPress\ProofAge;

if (! defined('ABSPATH')) {
    exit;
}

final class RequestSigner
{
    public function __construct(private readonly string $secretKey)
    {
    }

    public function sign(string $method, string $path, string $body = ''): string
    {
        $canonical = strtoupper($method) . $path . $body;

        return hash_hmac('sha256', $canonical, $this->secretKey);
    }

    /**
     * @param  array<string, mixed>  $formFields
     * @param  array<int, string>  $fileHashes
     */
    public function signMultipart(string $method, string $path, array $formFields, array $fileHashes): string
    {
        $fields = $this->canonicalizeArray($formFields);
        $serializedFields = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);

        sort($fileHashes);

        $canonical = strtoupper($method) . $path . "\n" . $serializedFields . "\n" . implode(',', $fileHashes);

        return hash_hmac('sha256', $canonical, $this->secretKey);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @return array<string, mixed>
     */
    private function canonicalizeArray(array $input): array
    {
        ksort($input);

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->canonicalizeArray($value);
            }
        }

        return $input;
    }
}
