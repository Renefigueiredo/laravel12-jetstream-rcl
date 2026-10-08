<?php

namespace App\Services\Import\Layouts;

use App\Enums\ImportSlot;
use App\Enums\SpreadsheetLayoutType;

class PaymentsLayout extends AbstractLayout
{
    public function type(): SpreadsheetLayoutType
    {
        return SpreadsheetLayoutType::Payments;
    }

    public function headers(): array
    {
        return [
            'TIPO', 'COD_OPERACAO', 'NM_OPERACAO', 'SUM_VL_OBRIGACAO_POR_COD_OPERACAO', 'CD_COMANDO',
            'DT_LIQUIDACAO', 'CEDENTE', 'ESPECIE', 'OBRIGACAO', 'CD_TIPO_TRANSACAO', 'DOC_GERADOR',
            'DT_EMISSAO', 'DT_VENCIMENTO', 'PREV_LIQUIIDACAO', 'VL_SALDO_OBRIGACAO', 'VL_RECEBIDO',
            'VL_OBRIGACAO', 'VL_DESCONTO_CONCEDIDO', 'VL_JUROS_MORA', 'SITUACAO', 'CD_MOVIMENTO_CONTA',
            'CD_CONTA_CORRENTE', 'NM_CONTA_CORRENTE', 'DS_AUDIT',
        ];
    }

    public function amountColumn(): string
    {
        return 'VL_RECEBIDO';
    }

    public function periodDateColumn(): string
    {
        return 'DT_LIQUIDACAO';
    }

    public function exampleRow(): array
    {
        return [
            'TIPO' => 'PAGAMENTO',
            'COD_OPERACAO' => '20150652',
            'NM_OPERACAO' => 'MATERIAL DE CONSUMO',
            'SUM_VL_OBRIGACAO_POR_COD_OPERACAO' => '1250.4',
            'CD_COMANDO' => '1',
            'DT_LIQUIDACAO' => '15-MAY-26',
            'CEDENTE' => 'FORNECEDOR EXEMPLO LTDA',
            'ESPECIE' => 'NOTA FISCAL 000123',
            'OBRIGACAO' => '900001',
            'CD_TIPO_TRANSACAO' => 'PIX',
            'DOC_GERADOR' => '000123',
            'DT_EMISSAO' => '02-MAY-26',
            'DT_VENCIMENTO' => '15-MAY-26',
            'PREV_LIQUIIDACAO' => '15-MAY-26',
            'VL_SALDO_OBRIGACAO' => '0',
            'VL_RECEBIDO' => '1250.4',
            'VL_OBRIGACAO' => '1250.4',
            'VL_DESCONTO_CONCEDIDO' => '0',
            'VL_JUROS_MORA' => '0',
            'SITUACAO' => 'LIQUIDADO',
            'CD_MOVIMENTO_CONTA' => '700001',
            'CD_CONTA_CORRENTE' => '0001',
            'NM_CONTA_CORRENTE' => 'CONTA EXEMPLO',
            'DS_AUDIT' => 'EXEMPLO',
        ];
    }

    protected function columns(): array
    {
        return [
            'COD_OPERACAO' => ['attribute' => 'operation_code', 'type' => self::TEXT, 'required' => true],
            'DT_LIQUIDACAO' => ['attribute' => 'paid_on', 'type' => self::DATE, 'required' => true],
            'CEDENTE' => ['attribute' => 'supplier_name', 'type' => self::TEXT, 'required' => true],
            'OBRIGACAO' => ['attribute' => 'obligation_number', 'type' => self::TEXT, 'required' => true],
            'VL_RECEBIDO' => ['attribute' => 'amount_cents', 'type' => self::MONEY, 'required' => true],
            'VL_OBRIGACAO' => ['attribute' => 'obligation_amount_cents', 'type' => self::MONEY, 'required' => true],
            'NM_OPERACAO' => ['attribute' => 'operation_name', 'type' => self::TEXT, 'required' => false],
            'ESPECIE' => ['attribute' => 'species', 'type' => self::TEXT, 'required' => false],
            'CD_TIPO_TRANSACAO' => ['attribute' => 'transaction_type', 'type' => self::TEXT, 'required' => false],
            'DOC_GERADOR' => ['attribute' => 'source_document', 'type' => self::TEXT, 'required' => false],
            'SITUACAO' => ['attribute' => 'settlement_status', 'type' => self::TEXT, 'required' => false],
            'CD_MOVIMENTO_CONTA' => ['attribute' => 'account_movement', 'type' => self::TEXT, 'required' => false],
        ];
    }

    protected function slotAttributes(ImportSlot $slot): array
    {
        return ['unit' => $slot->unit()?->value];
    }

    protected function identityKey(array $attributes, ImportSlot $slot): string
    {
        return implode('|', [
            $this->normalizeForIdentity($attributes['obligation_number']),
            $this->normalizeForIdentity($attributes['operation_code']),
            $this->normalizeForIdentity($attributes['account_movement']),
        ]);
    }
}
