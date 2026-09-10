<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class PaymentProviderDocumentation
{
    #[OA\Get(
        path: '/superadmin/payment-providers',
        operationId: 'listPaymentProviders',
        tags: ['Payment Providers'],
        summary: 'List payment providers',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Providers', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderListResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/superadmin/payment-providers',
        operationId: 'createPaymentProvider',
        tags: ['Payment Providers'],
        summary: 'Create a payment provider',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StorePaymentProviderRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/superadmin/payment-providers/{paymentProvider}',
        operationId: 'showPaymentProvider',
        tags: ['Payment Providers'],
        summary: 'Show payment provider (secrets never returned)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'flutterwave')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Provider', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/superadmin/payment-providers/{paymentProvider}',
        operationId: 'updatePaymentProvider',
        tags: ['Payment Providers'],
        summary: 'Update payment provider configuration',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePaymentProviderRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse')),
        ]
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/superadmin/payment-providers/{paymentProvider}',
        operationId: 'deletePaymentProvider',
        tags: ['Payment Providers'],
        summary: 'Delete provider or deactivate when history exists',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted/deactivated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ]
    )]
    public function destroy(): void {}

    #[OA\Post(
        path: '/superadmin/payment-providers/{paymentProvider}/enable',
        operationId: 'enablePaymentProvider',
        tags: ['Payment Providers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Enabled', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse'))]
    )]
    public function enable(): void {}

    #[OA\Post(
        path: '/superadmin/payment-providers/{paymentProvider}/disable',
        operationId: 'disablePaymentProvider',
        tags: ['Payment Providers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Disabled', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse'))]
    )]
    public function disable(): void {}

    #[OA\Post(
        path: '/superadmin/payment-providers/{paymentProvider}/default-payments',
        operationId: 'setDefaultPaymentProvider',
        tags: ['Payment Providers'],
        summary: 'Set default collections provider (unsets previous default)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Default updated', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse'))]
    )]
    public function setDefaultPayments(): void {}

    #[OA\Post(
        path: '/superadmin/payment-providers/{paymentProvider}/default-payouts',
        operationId: 'setDefaultPayoutProvider',
        tags: ['Payment Providers'],
        summary: 'Set default payouts provider (unsets previous default)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentProvider', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Default updated', content: new OA\JsonContent(ref: '#/components/schemas/PaymentProviderResponse'))]
    )]
    public function setDefaultPayouts(): void {}

    #[OA\Post(
        path: '/webhooks/payments/{provider}',
        operationId: 'paymentProviderCollectionWebhook',
        tags: ['Payment Webhooks'],
        summary: 'Public provider collection webhook (signature-verified)',
        description: 'No Sanctum auth. Identify provider from the path. Flutterwave: send verif-hash header matching the provider webhook_secret, body.event=charge.completed, data.tx_ref = payment intent reference. PalmPay: body.orderId = payment intent reference, orderStatus=2 for success, signed with RSA-SHA1 in body.sign; response is plain text "success". Stub: X-Platform-Payment-Secret. Backend looks up the payment intent by reference and routes by purpose (platform_subscription, subscription_renewal, installation_request, device_purchase, …). Idempotent.',
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'palmpay', enum: ['flutterwave', 'palmpay', 'stub'])),
            new OA\Parameter(name: 'verif-hash', in: 'header', required: false, description: 'Flutterwave webhook secret hash', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'X-Platform-Payment-Secret', in: 'header', required: false, description: 'Stub provider webhook secret', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FlutterwavePaymentWebhookRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Processed. PalmPay returns plain text "success"; other providers return PlatformPayment JSON.', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 401, description: 'Invalid signature', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Unknown payment reference', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Amount/currency/provider mismatch', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function paymentWebhook(): void {}

    #[OA\Post(
        path: '/webhooks/payouts/{provider}',
        operationId: 'paymentProviderPayoutWebhook',
        tags: ['Payment Webhooks'],
        summary: 'Public provider payout webhook (signature-verified)',
        description: 'No Sanctum auth. Same signature rules as collection webhooks. Does not mix with collection payment intents.',
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'palmpay', enum: ['flutterwave', 'palmpay', 'stub'])),
            new OA\Parameter(name: 'verif-hash', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FlutterwavePaymentWebhookRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Accepted (PalmPay: plain text success)', content: new OA\JsonContent(ref: '#/components/schemas/PayoutWebhookAcceptedResponse')),
            new OA\Response(response: 401, description: 'Invalid signature', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function payoutWebhook(): void {}
}
