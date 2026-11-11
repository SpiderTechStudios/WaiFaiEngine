<?php

namespace App\Services\Omada;

use RuntimeException;

class OmadaException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?int $httpStatus = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function userMessage(): string
    {
        return match (true) {
            $this->errorCode === -44106 => 'The Omada Client ID or Client Secret is invalid.',
            $this->errorCode === -44116, $this->httpStatus === 401 => 'The Omada access token is invalid or expired.',
            $this->errorCode === -7131 => 'The Omada Controller ID is invalid.',
            $this->errorCode === -1005 => 'The Omada API account does not have permission for this action.',
            $this->errorCode === -1001 => 'The Omada API request parameters were rejected.',
            $this->errorCode === -1600, $this->httpStatus === 404 => 'The requested Omada API endpoint is not available on this controller version.',
            $this->httpStatus === 403 => 'The Omada API account is not allowed to perform this action.',
            default => $this->getMessage(),
        };
    }
}
