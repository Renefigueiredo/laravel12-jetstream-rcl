<?php

namespace App\Services\Import;

use App\Contracts\SpreadsheetLayout;
use App\Enums\SpreadsheetLayoutType;
use App\Services\Import\Layouts\AuthorizationsLayout;
use App\Services\Import\Layouts\PaymentsLayout;

class LayoutRegistry
{
    public function for(SpreadsheetLayoutType $type): SpreadsheetLayout
    {
        return match ($type) {
            SpreadsheetLayoutType::Authorizations => app(AuthorizationsLayout::class),
            SpreadsheetLayoutType::Payments => app(PaymentsLayout::class),
        };
    }
}
