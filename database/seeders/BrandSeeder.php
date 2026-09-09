<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Ruijie',
                'description' => 'Networking and Wi-Fi equipment.',
                'logo' => 'https://images.seeklogo.com/logo-png/51/1/ruijie-logo-png_seeklogo-515759.png'
            ],
            [
                'name' => 'Reyee',
                'description' => 'Networking and Wi-Fi solutions by Ruijie.',
                'logo' => 'https://wirmax.net/img/cms/Ruijie-Reyee.png'
            ],
            [
                'name' => 'MikroTik',
                'description' => 'Network routers, switches, wireless systems and networking software.',
                'logo' => 'https://1000logos.net/wp-content/uploads/2020/10/MikroTik-Logo-768x432.png'
            ],
            [
                'name' => 'TP-Link',
                'description' => 'Networking products including routers, switches and access points.',
                'logo' => 'https://images.seeklogo.com/logo-png/29/1/tp-link-nuevo-logo-png_seeklogo-291223.png'
            ],
            [
                'name' => 'Ubiquiti',
                'description' => 'Enterprise and professional networking equipment.',
                'logo' => 'https://logos-world.net/wp-content/uploads/2025/01/Ubiquiti-Logo-2013.png'
            ],
            [
                'name' => 'Cisco',
                'description' => 'Enterprise networking and security equipment.',
                'logo' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/64/Cisco_logo.svg/3840px-Cisco_logo.svg.png?utm_source=commons.wikimedia.org&utm_campaign=index&utm_content=thumbnail'
            ],
            [
                'name' => 'Huawei',
                'description' => 'Networking, telecommunications and enterprise equipment.',
            ],
            [
                'name' => 'Tenda',
                'description' => 'Networking products including routers, switches and wireless devices.',
            ],
            [
                'name' => 'D-Link',
                'description' => 'Networking and connectivity products.',
            ],
            [
                'name' => 'Hikvision',
                'description' => 'Security, surveillance and networking products.',
            ],
            [
                'name' => 'ZTE',
                'description' => 'Telecommunications and networking equipment.',
            ],
            [
                'name' => 'Netgear',
                'description' => 'Networking products for home and business environments.',
            ],
            [
                'name' => 'TotoLink',
                'description' => 'Networking and wireless connectivity products.',
            ],
            [
                'name' => 'Mercusys',
                'description' => 'Affordable networking and wireless products.',
            ],
            [
                'name' => 'Cambium Networks',
                'description' => 'Wireless broadband and enterprise networking solutions.',
            ],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'description' => $brand['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}