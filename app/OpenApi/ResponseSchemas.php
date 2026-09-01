<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DashboardResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Dashboard retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/DashboardData'),
    ]
)]
#[OA\Schema(
    schema: 'IncomeResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Income retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/IncomeData'),
    ]
)]
#[OA\Schema(
    schema: 'RouterResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Router retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Router'),
    ]
)]
#[OA\Schema(
    schema: 'RouterCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Router created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Router'),
    ]
)]
#[OA\Schema(
    schema: 'RouterListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Routers retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Router')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouterResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Router retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AdminRouter'),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouterCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Router created for client'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AdminRouter'),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouterListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Routers retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminRouter')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouterDetailResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Router and client retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AdminRouterDetailData'),
    ]
)]
#[OA\Schema(
    schema: 'PackageResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Package retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Package'),
    ]
)]
#[OA\Schema(
    schema: 'PackageCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Package created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Package'),
    ]
)]
#[OA\Schema(
    schema: 'PackageListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Packages retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Package')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'VoucherResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Voucher retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Voucher'),
    ]
)]
#[OA\Schema(
    schema: 'VoucherListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Vouchers retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Voucher')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'VouchersCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Vouchers created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/VouchersCreatedData'),
    ]
)]
#[OA\Schema(
    schema: 'VoucherConsumeResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Voucher consumed'),
        new OA\Property(property: 'data', ref: '#/components/schemas/VoucherConsumeData'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payment retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Payment recorded'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payments retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Withdrawal retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Withdrawal'),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Withdrawal requested'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Withdrawal'),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Withdrawals retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'wallet_balance', type: 'number', example: 600),
            new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Withdrawal')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalStatsResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Withdrawal stats retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/WithdrawalStatsData'),
    ]
)]
#[OA\Schema(
    schema: 'BranchResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Branch retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Branch'),
    ]
)]
#[OA\Schema(
    schema: 'BranchCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Branch created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Branch'),
    ]
)]
#[OA\Schema(
    schema: 'BranchListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Branches retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Branch')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'CustomerResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Customer retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
    ]
)]
#[OA\Schema(
    schema: 'CustomerListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Customers retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'SessionResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Session retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/HotspotSession'),
    ]
)]
#[OA\Schema(
    schema: 'SessionCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Session created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/HotspotSession'),
    ]
)]
#[OA\Schema(
    schema: 'SessionListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Sessions retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/HotspotSession')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'SettingsResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Settings retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/SettingsData'),
    ]
)]
#[OA\Schema(
    schema: 'MembershipListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Staff retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Membership')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'MembershipCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Staff added'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Membership'),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 401),
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'ForbiddenResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 403),
        new OA\Property(property: 'message', type: 'string', example: 'You are not authorized to perform this action.'),
    ]
)]
#[OA\Schema(
    schema: 'NotFoundResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 404),
        new OA\Property(property: 'message', type: 'string', example: 'Resource not found.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 422),
        new OA\Property(property: 'message', type: 'string', example: 'Validation Error'),
        new OA\Property(
            property: 'data',
            type: 'object',
            description: 'Field-keyed validation messages',
            example: ['email' => ['The email field is required.'], 'password' => ['The password must be at least 8 characters.']],
        ),
    ]
)]
#[OA\Schema(
    schema: 'PortalBootstrapResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Portal bootstrap'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'company', type: 'object'),
            new OA\Property(property: 'packages', type: 'array', items: new OA\Items(ref: '#/components/schemas/Package')),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PortalPayment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reference', type: 'string', example: 'PAY-ABC123'),
        new OA\Property(property: 'amount', type: 'number', example: 1000),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'payment_method', type: 'string', example: 'mobile_money'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid', 'failed', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'package', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'PortalPaymentResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payment retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/PortalPayment'),
    ]
)]
#[OA\Schema(
    schema: 'PortalPaymentCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Payment initiated'),
        new OA\Property(property: 'data', ref: '#/components/schemas/PortalPayment'),
    ]
)]
#[OA\Schema(
    schema: 'PortalVoucherRedeemResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Voucher redeemed'),
        new OA\Property(property: 'data', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PortalRestoreResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Access restored'),
        new OA\Property(property: 'data', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'EnrollmentStatusResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Registration submitted. Please complete the payment request on your phone.'),
        new OA\Property(property: 'data', ref: '#/components/schemas/EnrollmentStatus'),
    ]
)]
#[OA\Schema(
    schema: 'EnrollmentExpiredResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 410),
        new OA\Property(property: 'message', type: 'string', example: 'The registration payment session has expired.'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'enrollment_reference', type: 'string', example: 'ENR-ABC123XYZ'),
            new OA\Property(property: 'enrollment_status', type: 'string', example: 'expired'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PlatformPaymentResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payment webhook processed'),
        new OA\Property(property: 'data', ref: '#/components/schemas/PlatformPayment'),
    ]
)]
#[OA\Schema(
    schema: 'PlatformPaymentCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Payment initiated successfully.'),
        new OA\Property(property: 'data', ref: '#/components/schemas/PlatformPayment'),
    ]
)]
#[OA\Schema(
    schema: 'PayoutWebhookAcceptedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payout webhook accepted'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'provider', type: 'string', example: 'flutterwave'),
            new OA\Property(property: 'accepted', type: 'boolean', example: true),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'FlutterwavePaymentWebhookRequest',
    properties: [
        new OA\Property(property: 'event', type: 'string', example: 'charge.completed'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 123456),
            new OA\Property(property: 'tx_ref', type: 'string', example: 'PAY-ABC123XYZ0', description: 'Internal payment intent reference'),
            new OA\Property(property: 'flw_ref', type: 'string', example: 'FLW-REF-xxxx'),
            new OA\Property(property: 'status', type: 'string', example: 'successful'),
            new OA\Property(property: 'amount', type: 'number', example: 10000),
            new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'StubPaymentWebhookRequest',
    required: ['reference', 'status'],
    properties: [
        new OA\Property(property: 'reference', type: 'string', example: 'PAY-ABC123XYZ0'),
        new OA\Property(property: 'transaction_reference', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'paid', description: 'paid|successful|failed'),
        new OA\Property(property: 'amount', type: 'number', example: 10000),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
    ]
)]
#[OA\Schema(
    schema: 'SubscriptionResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Subscription retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionSummary'),
    ]
)]
#[OA\Schema(
    schema: 'InstallationRequestResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Installation request retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/InstallationRequest'),
    ]
)]
#[OA\Schema(
    schema: 'InstallationRequestCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Installation request created'),
        new OA\Property(property: 'data', ref: '#/components/schemas/InstallationRequest'),
    ]
)]
#[OA\Schema(
    schema: 'InstallationRequestListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Installation requests retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/InstallationRequest')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'InstallationUpdateCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Update added'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'visibility', type: 'string', enum: ['customer', 'internal']),
            new OA\Property(property: 'body', type: 'string'),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentProviderResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payment provider retrieved'),
        new OA\Property(property: 'data', ref: '#/components/schemas/PaymentProvider'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentProviderListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Payment providers retrieved'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentProvider')),
    ]
)]
class ResponseSchemas {}
