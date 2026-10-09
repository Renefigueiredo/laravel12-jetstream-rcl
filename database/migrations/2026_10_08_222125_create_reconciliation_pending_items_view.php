<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The view joins, in standard SQL, everything a session with a completed run still has to decide:
     * the best pending suggestions, authorizations without payment, authorizations with an
     * open balance and payments without authorization. An authorization with an open balance is
     * always listed as such, even while it still has suggestions, so that it can be closed.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW reconciliation_pending_items AS
            SELECT
                's-' || s.id AS id,
                r.reconciliation_session_id AS reconciliation_session_id,
                'suggestion' AS kind,
                s.classification AS classification,
                s.id AS suggestion_id,
                s.authorization_entry_id AS authorization_entry_id,
                s.payment_entry_id AS payment_entry_id,
                a.supplier_name AS authorization_supplier,
                p.supplier_name AS payment_supplier,
                s.score AS score,
                s.difference_cents AS difference_cents,
                s.paid_before_authorization AS paid_before_authorization,
                s.card_mismatch AS card_mismatch,
                a.card AS authorization_card,
                p.card AS payment_card
            FROM reconciliation_suggestions s
            JOIN reconciliation_runs r ON r.id = s.reconciliation_run_id AND r.status = 'completed'
            JOIN authorization_entries a ON a.id = s.authorization_entry_id
            JOIN payment_entries p ON p.id = s.payment_entry_id
            WHERE s.status = 'pending'
              AND s.position = (
                  SELECT MIN(s2.position)
                  FROM reconciliation_suggestions s2
                  WHERE s2.authorization_entry_id = s.authorization_entry_id
                    AND s2.reconciliation_run_id = s.reconciliation_run_id
                    AND s2.status = 'pending'
              )

            UNION ALL

            SELECT
                'a-' || a.id,
                a.reconciliation_session_id,
                CASE WHEN st.authorization_entry_id IS NULL THEN 'unmatched_authorization' ELSE 'open_balance' END,
                CASE WHEN st.authorization_entry_id IS NULL THEN 'unmatched_authorization' ELSE 'open_balance' END,
                NULL,
                a.id,
                NULL,
                a.supplier_name,
                NULL,
                NULL,
                NULL,
                FALSE,
                FALSE,
                a.card,
                NULL
            FROM authorization_entries a
            LEFT JOIN import_files f ON f.id = a.import_file_id
            LEFT JOIN authorization_states st ON st.authorization_entry_id = a.id
            WHERE (a.import_file_id IS NULL OR f.status = 'active')
              AND EXISTS (
                  SELECT 1 FROM reconciliation_runs r
                  WHERE r.reconciliation_session_id = a.reconciliation_session_id AND r.status = 'completed'
              )
              AND (
                  st.status = 'partial'
                  OR (
                      st.authorization_entry_id IS NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM reconciliation_suggestions s
                          WHERE s.authorization_entry_id = a.id AND s.status = 'pending'
                      )
                  )
              )
              AND NOT EXISTS (
                  SELECT 1 FROM reconciliation_skips k WHERE k.authorization_entry_id = a.id
              )

            UNION ALL

            SELECT
                'p-' || p.id,
                p.reconciliation_session_id,
                'unmatched_payment',
                'unmatched_payment',
                NULL,
                NULL,
                p.id,
                NULL,
                p.supplier_name,
                NULL,
                NULL,
                FALSE,
                FALSE,
                NULL,
                p.card
            FROM payment_entries p
            JOIN import_files f ON f.id = p.import_file_id AND f.status = 'active'
            WHERE EXISTS (
                  SELECT 1 FROM reconciliation_runs r
                  WHERE r.reconciliation_session_id = p.reconciliation_session_id AND r.status = 'completed'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM reconciliation_links l WHERE l.payment_entry_id = p.id
              )
              AND NOT EXISTS (
                  SELECT 1 FROM reconciliation_suggestions s
                  WHERE s.payment_entry_id = p.id AND s.status = 'pending'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM reconciliation_skips k WHERE k.payment_entry_id = p.id
              )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS reconciliation_pending_items');
    }
};
