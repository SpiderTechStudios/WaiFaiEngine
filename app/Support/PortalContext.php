<?php

namespace App\Support;

use App\Models\Company;

class PortalContext
{
    public function __construct(public ?Company $company = null) {}
}
