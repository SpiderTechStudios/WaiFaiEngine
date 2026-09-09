<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class DeviceCatalogDocumentation
{
    #[OA\Get(
        path: '/superadmin/brands',
        operationId: 'listBrands',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'List brands',
        responses: [new OA\Response(response: 200, description: 'Brands')]
    )]
    public function listBrands(): void {}

    #[OA\Post(
        path: '/superadmin/brands',
        operationId: 'createBrand',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Create a brand, optionally with a logo file',
        requestBody: new OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'slug', type: 'string', nullable: true),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'logo', type: 'string', format: 'binary', nullable: true),
            new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
        ]))),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createBrand(): void {}

    #[OA\Patch(
        path: '/superadmin/brands/{brand}',
        operationId: 'updateBrand',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Update a brand or replace its logo',
        parameters: [new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateBrand(): void {}

    #[OA\Delete(
        path: '/superadmin/brands/{brand}',
        operationId: 'deleteBrand',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Delete a brand with no devices',
        parameters: [new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function deleteBrand(): void {}

    #[OA\Get(
        path: '/superadmin/device-categories',
        operationId: 'listDeviceCategories',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'List device categories',
        responses: [new OA\Response(response: 200, description: 'Categories')]
    )]
    public function listCategories(): void {}

    #[OA\Post(
        path: '/superadmin/device-categories',
        operationId: 'createDeviceCategory',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Create a device category',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createCategory(): void {}

    #[OA\Patch(
        path: '/superadmin/device-categories/{deviceCategory}',
        operationId: 'updateDeviceCategory',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Update a device category',
        parameters: [new OA\Parameter(name: 'deviceCategory', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateCategory(): void {}

    #[OA\Delete(
        path: '/superadmin/device-categories/{deviceCategory}',
        operationId: 'deleteDeviceCategory',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Delete a device category with no devices',
        parameters: [new OA\Parameter(name: 'deviceCategory', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function deleteCategory(): void {}

    #[OA\Get(
        path: '/superadmin/devices',
        operationId: 'listCatalogDevices',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'List catalog devices',
        responses: [new OA\Response(response: 200, description: 'Devices')]
    )]
    public function listDevices(): void {}

    #[OA\Post(
        path: '/superadmin/devices',
        operationId: 'createCatalogDevice',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Create a catalog device',
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createDevice(): void {}

    #[OA\Get(
        path: '/superadmin/devices/{device}',
        operationId: 'showCatalogDevice',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Show a catalog device',
        parameters: [new OA\Parameter(name: 'device', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Device')]
    )]
    public function showDevice(): void {}

    #[OA\Patch(
        path: '/superadmin/devices/{device}',
        operationId: 'updateCatalogDevice',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Update a catalog device',
        parameters: [new OA\Parameter(name: 'device', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateDevice(): void {}

    #[OA\Delete(
        path: '/superadmin/devices/{device}',
        operationId: 'deleteCatalogDevice',
        tags: ['Device Catalog'],
        security: [['sanctum' => []]],
        summary: 'Delete a catalog device',
        parameters: [new OA\Parameter(name: 'device', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function deleteDevice(): void {}
}
