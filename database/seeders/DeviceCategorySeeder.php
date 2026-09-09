<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeviceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Repeater',
                'description' => 'Amplifies and regenerates signals over long distances to prevent data loss.'
            ],
            [
                'name' => 'Hub',
                'description' => 'Connects multiple devices in a network segment by broadcasting incoming signals to every port.'
            ],
            [
                'name' => 'Bridge',
                'description' => 'Connects multiple network segments together and filters traffic to reduce data collisions.'
            ],
            [
                'name' => 'Switch',
                'description' => 'Connects devices within a local network and intelligently forwards data only to the specific intended recipient using MAC addresses.'
            ],
            [
                'name' => 'Router',
                'description' => 'Connects different networks together and directs data packets using IP addresses.'
            ],
            [
                'name' => 'Gateway',
                'description' => 'Acts as an entry point or translator between networks that use completely different communication protocols.'
            ],
            [
                'name' => 'Modem',
                'description' => 'Converts digital data into analog signals (and vice versa) for transmission over telephone or television lines.'
            ],
            [
                'name' => 'Access Point (WAP)',
                'description' => 'Connects wireless devices to a wired local area network using Wi-Fi.'
            ],
            [
                'name' => 'Firewall',
                'description' => 'Monitors and controls incoming and outgoing network traffic based on security rules to block unauthorized access.'
            ]
        ];

        foreach ($categories as $category) {
            \App\Models\DeviceCategory::create($category);
        }
    }
}
