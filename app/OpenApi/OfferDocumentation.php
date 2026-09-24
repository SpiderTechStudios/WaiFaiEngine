<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class OfferDocumentation
{
    #[OA\Get(
        path: '/offers',
        operationId: 'listOffers',
        tags: ['Offers'],
        summary: 'List offers for the current company',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offers', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedOffersResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/offers',
        operationId: 'createOffer',
        tags: ['Offers'],
        summary: 'Create a free WiFi offer',
        description: 'Define a time-limited free-access promotion. Provide a duration (standalone) or an internet_plan_id. router_ids empty = company-wide.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreOfferRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Offer created', content: new OA\JsonContent(ref: '#/components/schemas/OfferResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/offers/{offer}',
        operationId: 'showOffer',
        tags: ['Offers'],
        summary: 'View an offer',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'offer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Offer', content: new OA\JsonContent(ref: '#/components/schemas/OfferResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/offers/{offer}',
        operationId: 'updateOffer',
        tags: ['Offers'],
        summary: 'Update an offer',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'offer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreOfferRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Offer updated', content: new OA\JsonContent(ref: '#/components/schemas/OfferResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/offers/{offer}',
        operationId: 'deleteOffer',
        tags: ['Offers'],
        summary: 'Delete an offer',
        description: 'Deletes the offer, or deactivates it when it already has claims.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'offer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Offer deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function destroy(): void {}

    #[OA\Post(
        path: '/offers/{offer}/activate',
        operationId: 'activateOffer',
        tags: ['Offers'],
        summary: 'Activate an offer',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'offer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Offer activated', content: new OA\JsonContent(ref: '#/components/schemas/OfferResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function activate(): void {}

    #[OA\Post(
        path: '/offers/{offer}/deactivate',
        operationId: 'deactivateOffer',
        tags: ['Offers'],
        summary: 'Deactivate an offer',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'offer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Offer deactivated', content: new OA\JsonContent(ref: '#/components/schemas/OfferResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function deactivate(): void {}

    #[OA\Post(
        path: '/portal/{subdomain}/offers/claim',
        operationId: 'portalClaimOffer',
        tags: ['Portal'],
        summary: 'Claim a free WiFi offer',
        description: 'Grants free access for the offer duration starting now. One claim per phone + device MAC. Free access never records revenue. When a captive_session is supplied the device is authorized and gateway_auth_url is returned.',
        parameters: [new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PortalClaimOfferRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Offer claimed', content: new OA\JsonContent(ref: '#/components/schemas/PortalClaimOfferResponse')),
            new OA\Response(response: 404, description: 'Portal or offer not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Offer unavailable, already claimed, or invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function portalClaim(): void {}
}

#[OA\Schema(
    schema: 'Offer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: '3 Hours Free'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'duration', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'duration_unit', type: 'string', nullable: true, enum: ['HOURS', 'DAYS', 'WEEKS', 'MONTHS'], example: 'HOURS'),
        new OA\Property(property: 'max_claims', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'claims_count', type: 'integer', example: 0),
        new OA\Property(property: 'remaining_claims', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'internet_plan_id', type: 'integer', nullable: true),
        new OA\Property(property: 'package', type: 'object', nullable: true),
        new OA\Property(property: 'routers', type: 'array', items: new OA\Items(type: 'object'), description: 'Empty = company-wide'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'StoreOfferRequest',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: '3 Hours Free'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'duration', type: 'integer', nullable: true, example: 3, description: 'Required on create when no internet_plan_id is given'),
        new OA\Property(property: 'duration_unit', type: 'string', nullable: true, enum: ['HOURS', 'DAYS', 'WEEKS', 'MONTHS'], example: 'HOURS'),
        new OA\Property(property: 'max_claims', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'internet_plan_id', type: 'integer', nullable: true, description: 'Optional existing package; otherwise a hidden zero-price plan is used'),
        new OA\Property(property: 'router_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Empty/omitted = company-wide'),
    ]
)]
#[OA\Schema(
    schema: 'OfferResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Offer'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedOffersResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Offers retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Offer')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PortalClaimOfferRequest',
    required: ['offer_id', 'customer_phone'],
    properties: [
        new OA\Property(property: 'offer_id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_phone', type: 'string', example: '0711987654'),
        new OA\Property(property: 'customer_name', type: 'string', nullable: true),
        new OA\Property(property: 'captive_session', type: 'string', nullable: true, description: '32-char captive session token'),
        new OA\Property(property: 'mac_address', type: 'string', nullable: true, example: 'AA:BB:CC:DD:EE:FF'),
    ]
)]
#[OA\Schema(
    schema: 'PortalClaimOfferResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Offer claimed'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'customer', type: 'object'),
            new OA\Property(property: 'access_grant', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'status', type: 'string', example: 'active'),
                new OA\Property(property: 'source', type: 'string', example: 'offer'),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
            ]),
            new OA\Property(property: 'package', type: 'object', nullable: true),
            new OA\Property(property: 'offer', type: 'object'),
            new OA\Property(property: 'captive', type: 'object', nullable: true, description: 'Present when captive_session is supplied'),
        ], type: 'object'),
    ]
)]
class OfferSchemas {}
