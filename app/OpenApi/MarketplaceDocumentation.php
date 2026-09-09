<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class MarketplaceDocumentation
{
    #[OA\Get(
        path: '/marketplace/devices',
        operationId: 'listMarketplaceDevices',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'List devices available for purchase',
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'brand_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'device_category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Available devices', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedMarketplaceDevicesResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
        ]
    )]
    public function listDevices(): void {}

    #[OA\Get(
        path: '/marketplace/devices/{device}',
        operationId: 'showMarketplaceDevice',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Show one marketplace device',
        parameters: [new OA\Parameter(name: 'device', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Device', content: new OA\JsonContent(ref: '#/components/schemas/MarketplaceDeviceResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showDevice(): void {}

    #[OA\Get(
        path: '/cart',
        operationId: 'showCart',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Get the current company cart',
        responses: [
            new OA\Response(response: 200, description: 'Cart', content: new OA\JsonContent(ref: '#/components/schemas/CartResponse')),
        ]
    )]
    public function showCart(): void {}

    #[OA\Post(
        path: '/cart/items',
        operationId: 'addCartItem',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Add a device to the cart',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['device_id'], properties: [
            new OA\Property(property: 'device_id', type: 'integer', example: 1),
            new OA\Property(property: 'quantity', type: 'integer', example: 1),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Item added', content: new OA\JsonContent(ref: '#/components/schemas/CartResponse')),
            new OA\Response(response: 422, description: 'Unavailable or insufficient stock', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function addCartItem(): void {}

    #[OA\Patch(
        path: '/cart/items/{cartItem}',
        operationId: 'updateCartItem',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Change a cart item quantity',
        parameters: [new OA\Parameter(name: 'cartItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['quantity'], properties: [
            new OA\Property(property: 'quantity', type: 'integer', example: 2),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Quantity updated', content: new OA\JsonContent(ref: '#/components/schemas/CartResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function updateCartItem(): void {}

    #[OA\Delete(
        path: '/cart/items/{cartItem}',
        operationId: 'removeCartItem',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Remove an item from the cart',
        parameters: [new OA\Parameter(name: 'cartItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Item removed', content: new OA\JsonContent(ref: '#/components/schemas/CartResponse')),
        ]
    )]
    public function removeCartItem(): void {}

    #[OA\Delete(
        path: '/cart',
        operationId: 'clearCart',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Clear the current cart',
        responses: [
            new OA\Response(response: 200, description: 'Cart cleared', content: new OA\JsonContent(ref: '#/components/schemas/CartResponse')),
        ]
    )]
    public function clearCart(): void {}

    #[OA\Post(
        path: '/cart/checkout',
        operationId: 'checkoutCart',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Pay for the cart and create a pending device purchase order',
        description: 'Creates an order with status pending and a platform payment whose purpose is device_purchase. Payment success moves the order to processing.',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'fulfillment_method', type: 'string', enum: ['delivery', 'pickup'], example: 'delivery'),
            new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0712345678'),
            new OA\Property(property: 'notes', type: 'string', nullable: true),
            new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'mobile_money'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Order and payment created', content: new OA\JsonContent(ref: '#/components/schemas/CartCheckoutResponse')),
            new OA\Response(response: 422, description: 'Empty cart or stock error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function checkout(): void {}

    #[OA\Get(
        path: '/orders',
        operationId: 'listCompanyOrders',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'List the current company device orders',
        responses: [
            new OA\Response(response: 200, description: 'Orders', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedOrdersResponse')),
        ]
    )]
    public function listOrders(): void {}

    #[OA\Get(
        path: '/orders/{order}',
        operationId: 'showCompanyOrder',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Show a company device order',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showOrder(): void {}

    #[OA\Post(
        path: '/orders/{order}/confirm-delivery',
        operationId: 'confirmOrderDelivery',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Confirm the order was delivered or collected',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Delivered', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
            new OA\Response(response: 422, description: 'Order is not ready to confirm', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function confirmDelivery(): void {}

    #[OA\Get(
        path: '/superadmin/orders',
        operationId: 'listAdminOrders',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'List device purchase orders',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'in-transit', 'delivered', 'cancelled'])),
            new OA\Parameter(name: 'company_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orders', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedOrdersResponse')),
        ]
    )]
    public function adminListOrders(): void {}

    #[OA\Get(
        path: '/superadmin/orders/{order}',
        operationId: 'showAdminOrder',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Show a device purchase order',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        ]
    )]
    public function adminShowOrder(): void {}

    #[OA\Post(
        path: '/superadmin/orders/{order}/in-transit',
        operationId: 'markOrderInTransit',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Mark a paid order as sent',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'In transit', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        ]
    )]
    public function inTransit(): void {}

    #[OA\Post(
        path: '/superadmin/orders/{order}/delivered',
        operationId: 'markOrderDelivered',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Mark an order delivered',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Delivered', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        ]
    )]
    public function delivered(): void {}

    #[OA\Post(
        path: '/superadmin/orders/{order}/cancel',
        operationId: 'cancelOrder',
        tags: ['Marketplace'],
        security: [['sanctum' => []]],
        summary: 'Cancel an order',
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Cancelled', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        ]
    )]
    public function cancel(): void {}
}

#[OA\Schema(
    schema: 'CartItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'device_id', type: 'integer', example: 4),
        new OA\Property(property: 'quantity', type: 'integer', example: 1),
        new OA\Property(property: 'unit_price', type: 'string', example: '100000.00'),
        new OA\Property(property: 'line_total', type: 'string', example: '100000.00'),
        new OA\Property(property: 'device', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Cart',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', example: 'open'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartItem')),
        new OA\Property(property: 'total_amount', type: 'string', example: '100000.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
    ]
)]
#[OA\Schema(
    schema: 'CartResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Cart retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Cart'),
    ]
)]
#[OA\Schema(
    schema: 'OrderItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'device_id', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'name', type: 'string', example: 'Office Router'),
        new OA\Property(property: 'sku', type: 'string', example: 'SKU-1'),
        new OA\Property(property: 'quantity', type: 'integer', example: 1),
        new OA\Property(property: 'unit_price', type: 'string', example: '100000.00'),
        new OA\Property(property: 'line_total', type: 'string', example: '100000.00'),
    ]
)]
#[OA\Schema(
    schema: 'Order',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reference', type: 'string', example: 'ORD-ABC123XYZ0'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'in-transit', 'delivered', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'payment_status', type: 'string', enum: ['pending', 'paid', 'failed', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'fulfillment_method', type: 'string', enum: ['delivery', 'pickup'], example: 'delivery'),
        new OA\Property(property: 'total_amount', type: 'string', example: '100000.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'shipped_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'payment', ref: '#/components/schemas/PlatformPayment', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'OrderResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Order retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
    ]
)]
#[OA\Schema(
    schema: 'CartCheckoutResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Order created and payment initiated'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'order', ref: '#/components/schemas/Order'),
            new OA\Property(property: 'payment', ref: '#/components/schemas/PlatformPayment'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedOrdersResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Orders retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'MarketplaceDeviceResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Marketplace device retrieved'),
        new OA\Property(property: 'data', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedMarketplaceDevicesResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Marketplace devices retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
class MarketplaceSchemas {}
