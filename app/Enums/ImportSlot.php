<?php

namespace App\Enums;

enum ImportSlot: string
{
    case Authorizations = 'authorizations';
    case PaymentsSocial = 'payments_social';
    case PaymentsSaude = 'payments_saude';

    public function label(): string
    {
        return __('conciliation.slots.'.$this->value);
    }

    public function layout(): SpreadsheetLayoutType
    {
        return match ($this) {
            self::Authorizations => SpreadsheetLayoutType::Authorizations,
            self::PaymentsSocial, self::PaymentsSaude => SpreadsheetLayoutType::Payments,
        };
    }

    public function unit(): ?OperatingUnit
    {
        return match ($this) {
            self::Authorizations => null,
            self::PaymentsSocial => OperatingUnit::Social,
            self::PaymentsSaude => OperatingUnit::Saude,
        };
    }

    /**
     * The other payment slot of the same session, when this is a payment slot.
     */
    public function siblingPaymentSlot(): ?self
    {
        return match ($this) {
            self::Authorizations => null,
            self::PaymentsSocial => self::PaymentsSaude,
            self::PaymentsSaude => self::PaymentsSocial,
        };
    }
}
