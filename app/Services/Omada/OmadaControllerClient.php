<?php

namespace App\Services\Omada;

use App\Models\OmadaIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OmadaControllerClient
{
    private ?string $resolvedControllerId = null;

    private ?string $resolvedVersion = null;

    public function __construct(
        private readonly OmadaIntegration $integration,
        private readonly ?string $accessTokenOverride = null,
    ) {}

    public function controllerId(): string
    {
        if ($this->resolvedControllerId !== null) {
            return $this->resolvedControllerId;
        }

        if (filled($this->integration->controller_id)) {
            return $this->resolvedControllerId = (string) $this->integration->controller_id;
        }

        $info = $this->info();
        $id = (string) ($info['omadacId'] ?? '');

        if ($id === '') {
            throw new OmadaException('The Omada controller did not return an Omada ID.', context: ['endpoint' => '/api/info']);
        }

        return $this->resolvedControllerId = $id;
    }

    public function controllerVersion(): ?string
    {
        if ($this->resolvedVersion !== null) {
            return $this->resolvedVersion;
        }

        if (filled($this->integration->controller_version)) {
            return $this->resolvedVersion = (string) $this->integration->controller_version;
        }

        return $this->resolvedVersion = ($this->info()['controllerVer'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $result = $this->request('GET', '/api/info', authenticated: false);

        return is_array($result) ? $result : [];
    }

    public function accessToken(): string
    {
        if ($this->accessTokenOverride !== null && $this->accessTokenOverride !== '') {
            return $this->accessTokenOverride;
        }

        $clientId = (string) $this->integration->credential('client_id', '');
        $clientSecret = (string) $this->integration->credential('client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            throw new OmadaException('The Omada Client ID and Client Secret are required. Create an Open API application on the controller.', errorCode: -44106);
        }

        $controllerId = $this->controllerId();
        $cacheKey = 'omada:token:'.$this->integration->id.':'.sha1($controllerId.'|'.$clientId);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = [
            'grant_type' => 'client_credentials',
            'omadacId' => $controllerId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        $result = $this->request(
            'POST',
            '/openapi/authorize/token',
            query: ['grant_type' => 'client_credentials'],
            body: $body,
            authenticated: false,
        );

        $token = (string) ($result['accessToken'] ?? '');

        if ($token === '') {
            throw new OmadaException('The Omada controller did not return an access token.', context: ['endpoint' => '/openapi/authorize/token']);
        }

        $expiresIn = (int) ($result['expiresIn'] ?? 7200);
        $ttl = max(60, min((int) config('omada.token_cache_ttl', 300), $expiresIn - 60));

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return mixed
     */
    public function get(string $path, array $query = [])
    {
        return $this->request('GET', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return mixed
     */
    public function post(string $path, array $body = [])
    {
        return $this->request('POST', $path, body: $body);
    }

    /**
     * @return mixed
     */
    public function delete(string $path)
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function paginated(string $path, array $query = []): array
    {
        $query = array_merge([
            'page' => 1,
            'pageSize' => (int) config('omada.page_size', 1000),
        ], $query);

        $result = $this->get($path, $query);

        if (! is_array($result)) {
            return [];
        }

        $rows = $result['data'] ?? $result;

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return mixed
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        bool $authenticated = true,
    ) {
        $url = $this->baseUrl().$path;

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->timeout(max(2, (int) config('omada.timeout', 15)))
                ->connectTimeout(max(1, (int) config('omada.connect_timeout', 8)))
                ->withOptions(['verify' => $this->verifyTls()])
                ->retry(
                    max(1, (int) config('omada.retries', 2)),
                    200,
                    fn ($exception) => $exception instanceof ConnectionException,
                    throw: false,
                );

            if ($authenticated) {
                $request = $request->withHeaders([
                    'Authorization' => 'AccessToken='.$this->accessToken(),
                ]);
            }

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url.(empty($query) ? '' : '?'.http_build_query($query)), $body),
                'DELETE' => $request->delete($url, $query),
                default => throw new OmadaException('Unsupported Omada HTTP method.'),
            };
        } catch (ConnectionException $exception) {
            throw new OmadaException(
                'Unable to reach the Omada controller.',
                context: ['endpoint' => $path, 'transport' => $exception->getMessage()],
            );
        }

        return $this->parse($response, $path);
    }

    /**
     * @return mixed
     */
    private function parse(Response $response, string $path)
    {
        $json = $response->json();
        $errorCode = is_array($json) ? ($json['errorCode'] ?? null) : null;

        if ($response->failed()) {
            throw new OmadaException(
                (string) ($json['msg'] ?? 'The Omada controller rejected the request.'),
                errorCode: is_numeric($errorCode) ? (int) $errorCode : null,
                httpStatus: $response->status(),
                context: ['endpoint' => $path],
            );
        }

        if (is_numeric($errorCode) && (int) $errorCode !== 0) {
            throw new OmadaException(
                (string) ($json['msg'] ?? 'The Omada controller returned an error.'),
                errorCode: (int) $errorCode,
                httpStatus: $response->status(),
                context: ['endpoint' => $path],
            );
        }

        return is_array($json) ? ($json['result'] ?? []) : [];
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->integration->controller_url, '/');
    }

    private function verifyTls(): bool
    {
        return (bool) $this->integration->verify_tls && (bool) config('omada.verify_tls', true);
    }
}
