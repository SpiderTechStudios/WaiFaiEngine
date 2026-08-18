<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Location;
use App\Models\NetworkStation;

class BranchService
{
    public function defaultStation(Company $company, ?int $locationId = null): NetworkStation
    {
        if ($locationId) {
            $location = Location::query()
                ->where('company_id', $company->id)
                ->findOrFail($locationId);

            $station = $location->networkStations()->first();
            if ($station) {
                return $station;
            }

            return NetworkStation::query()->create([
                'company_id' => $company->id,
                'location_id' => $location->id,
                'name' => $location->name.' hotspot',
                'code' => $location->code.'-hotspot',
                'status' => 'active',
            ]);
        }

        $station = NetworkStation::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        if ($station) {
            return $station;
        }

        $location = Location::query()->create([
            'company_id' => $company->id,
            'name' => $company->name,
            'code' => 'main',
            'status' => 'active',
            'timezone' => $company->timezone,
        ]);

        return NetworkStation::query()->create([
            'company_id' => $company->id,
            'location_id' => $location->id,
            'name' => $company->name.' hotspot',
            'code' => 'main-hotspot',
            'status' => 'active',
        ]);
    }
}
