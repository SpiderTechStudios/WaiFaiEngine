<?php

namespace App\Services\Omada;

use Illuminate\Validation\ValidationException;

class OmadaEndpointValidator
{
    /**
     * Validate a controller URL and return the normalized origin, e.g.
     * "https://controller.example.com:8043".
     *
     * @throws ValidationException
     */
    public function validate(string $url, ?int $port = null, ?bool $verifyTls = null, string $connectionMethod = 'public'): string
    {
        $url = trim($url);

        if ($url === '') {
            throw $this->fail('controller_url', 'The Omada controller URL is required.');
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw $this->fail('controller_url', 'The Omada controller URL is not valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower(trim((string) $parts['host'], '[]'));
        $port = $port ?? (isset($parts['port']) ? (int) $parts['port'] : null);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw $this->fail('controller_url', 'The Omada controller URL must use HTTP or HTTPS.');
        }

        if ($scheme === 'http' && ! (bool) config('omada.allow_insecure_http', false)) {
            throw $this->fail('controller_url', 'The Omada controller URL must use HTTPS. Enable OMADA_ALLOW_INSECURE_HTTP only for trusted private networks.');
        }

        if ($port === null) {
            $port = $scheme === 'https' ? (int) config('omada.default_port', 8043) : 80;
        }

        if ($port < 1 || $port > 65535) {
            throw $this->fail('controller_port', 'The Omada controller port must be between 1 and 65535.');
        }

        if ($verifyTls === false && ! (bool) config('omada.allow_self_signed', false)) {
            throw $this->fail('verify_tls', 'Disabling TLS verification is not allowed by the platform. Upload a trusted certificate to the controller or enable OMADA_ALLOW_SELF_SIGNED.');
        }

        $this->assertSafeHost($host, $connectionMethod);

        return $scheme.'://'.$host.':'.$port;
    }

    /**
     * @throws ValidationException
     */
    private function assertSafeHost(string $host, string $connectionMethod): void
    {
        if ($host === '' || $host === '0.0.0.0') {
            throw $this->fail('controller_url', 'The Omada controller host is not valid.');
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost')) {
            throw $this->fail('controller_url', 'The Omada controller must not point at localhost.');
        }

        if (in_array($host, ['169.254.169.254', 'metadata.google.internal', 'metadata.google.com'], true)) {
            throw $this->fail('controller_url', 'The Omada controller must not point at a cloud metadata endpoint.');
        }

        $allowPrivate = (bool) config('omada.allow_private_hosts', false)
            && in_array($connectionMethod, ['vpn', 'cloud'], true);

        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $addresses = $resolved;
            }
        }

        if ($allowPrivate) {
            return;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw $this->fail('controller_url', 'The Omada controller resolves to a private or reserved address. Use a public endpoint, VPN, or enable OMADA_ALLOW_PRIVATE_HOSTS for a private-network integration.');
            }
        }
    }

    private function fail(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => [$message]]);
    }
}
