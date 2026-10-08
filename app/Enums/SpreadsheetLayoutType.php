<?php

namespace App\Enums;

enum SpreadsheetLayoutType: string
{
    case Authorizations = 'authorizations';
    case Payments = 'payments';

    public function label(): string
    {
        return __('conciliation.layouts.'.$this->value);
    }

    /**
     * The slug used in the template download route.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Authorizations => 'autorizacoes',
            self::Payments => 'pagamentos',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $layout) {
            if ($layout->slug() === $slug) {
                return $layout;
            }
        }

        return null;
    }
}
