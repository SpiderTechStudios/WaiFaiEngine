<?php

namespace Tests\Unit;

use App\Support\NetworkDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NetworkDetectorTest extends TestCase
{
    #[DataProvider('phoneNetworkProvider')]
    public function test_detects_network_from_prefix(string $phone, ?string $expected): void
    {
        $this->assertSame($expected, NetworkDetector::detect($phone));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function phoneNetworkProvider(): array
    {
        return [
            'vodacom local' => ['0754123456', NetworkDetector::MPESA],
            'vodacom intl' => ['255754123456', NetworkDetector::MPESA],
            'vodacom 079' => ['0791234567', NetworkDetector::MPESA],
            'airtel' => ['0789123456', NetworkDetector::AIRTEL_MONEY],
            'airtel 068' => ['0681234567', NetworkDetector::AIRTEL_MONEY],
            'halotel' => ['0621234567', NetworkDetector::HALOPESA],
            'yas / tigo' => ['0714257454', NetworkDetector::MIXX_BY_YAS],
            'yas 065' => ['0651234567', NetworkDetector::MIXX_BY_YAS],
            'ttcl' => ['0731234567', NetworkDetector::TTCL],
            'unknown prefix' => ['0700555666', null],
            'too short' => ['07142', null],
        ];
    }

    public function test_detect_or_falls_back(): void
    {
        $this->assertSame('mobile_money', NetworkDetector::detectOr('mobile_money', '0700555666'));
        $this->assertSame(NetworkDetector::MIXX_BY_YAS, NetworkDetector::detectOr('mobile_money', '0714257454'));
    }
}
