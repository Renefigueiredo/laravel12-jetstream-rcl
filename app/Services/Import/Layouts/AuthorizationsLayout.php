<?php

namespace App\Services\Import\Layouts;

use App\Enums\ImportSlot;
use App\Enums\SpreadsheetLayoutType;

class AuthorizationsLayout extends AbstractLayout
{
    public function type(): SpreadsheetLayoutType
    {
        return SpreadsheetLayoutType::Authorizations;
    }

    public function headers(): array
    {
        return [
            'MAP_SOLICITACAO', 'MAP_SETOR_SOLICITANTE', 'MAP_FUNCIONARIO_SOLICITANTE', 'MAP_DATA_AUTORIZACAO',
            'MAP_APROVADOR', 'MAP_FORNECEDOR', 'VALOR', 'MAP_FORMA_DE_PAGAMENTO', 'MAP_AFRAFEP_CARTAO',
            'MAP_CONDICAO_DE_PAGAMENTO', 'MAP_VALOR',
        ];
    }

    public function amountColumn(): string
    {
        return 'VALOR';
    }

    public function periodDateColumn(): string
    {
        return 'MAP_DATA_AUTORIZACAO';
    }

    public function exampleRow(): array
    {
        return [
            'MAP_SOLICITACAO' => 'MATERIAL DE ESCRITORIO - 00001',
            'MAP_SETOR_SOLICITANTE' => 'Secretaria',
            'MAP_FUNCIONARIO_SOLICITANTE' => 'FUNCIONARIO EXEMPLO',
            'MAP_DATA_AUTORIZACAO' => '15/05/2026',
            'MAP_APROVADOR' => 'Aprovador Exemplo',
            'MAP_FORNECEDOR' => 'FORNECEDOR EXEMPLO LTDA',
            'VALOR' => 'R$ 1.250,40',
            'MAP_FORMA_DE_PAGAMENTO' => 'PIX',
            'MAP_AFRAFEP_CARTAO' => '',
            'MAP_CONDICAO_DE_PAGAMENTO' => 'A vista',
            'MAP_VALOR' => '1250,40',
        ];
    }

    protected function columns(): array
    {
        return [
            'MAP_SOLICITACAO' => ['attribute' => 'request', 'type' => self::TEXT, 'required' => true],
            'MAP_FORNECEDOR' => ['attribute' => 'supplier_name', 'type' => self::TEXT, 'required' => true],
            'VALOR' => ['attribute' => 'amount_cents', 'type' => self::MONEY, 'required' => true],
            'MAP_DATA_AUTORIZACAO' => ['attribute' => 'authorized_on', 'type' => self::DATE, 'required' => true],
            'MAP_FORMA_DE_PAGAMENTO' => ['attribute' => 'payment_method', 'type' => self::TEXT, 'required' => false],
            'MAP_AFRAFEP_CARTAO' => ['attribute' => 'card', 'type' => self::TEXT, 'required' => false],
            'MAP_CONDICAO_DE_PAGAMENTO' => ['attribute' => 'payment_condition', 'type' => self::TEXT, 'required' => false],
        ];
    }

    protected function identityKey(array $attributes, ImportSlot $slot): string
    {
        return hash('sha256', implode('|', [
            $this->normalizeForIdentity($attributes['request']),
            $this->normalizeForIdentity($attributes['supplier_name']),
            $attributes['amount_cents'],
            $attributes['authorized_on']->format('Y-m-d'),
        ]));
    }
}
