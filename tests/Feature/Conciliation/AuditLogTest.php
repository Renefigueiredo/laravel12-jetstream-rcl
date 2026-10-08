<?php

namespace Tests\Feature\Conciliation;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use DatabaseMigrations;

    public function test_recorder_stores_actor_action_entity_and_snapshots(): void
    {
        $user = User::factory()->create();
        $session = ReconciliationSession::factory()->create();

        $this->travelTo('2026-05-10 12:30:15');

        $log = DB::transaction(fn (): AuditLog => app(AuditRecorder::class)->record(
            $user,
            AuditAction::SessionCreated,
            $session,
            'Sessão 1 - 05/2026',
            null,
            ['status' => 'open'],
        ));

        $log = $log->fresh();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(AuditAction::SessionCreated, $log->action);
        $this->assertSame('reconciliation_session', $log->auditable_type);
        $this->assertSame($session->id, $log->auditable_id);
        $this->assertSame('Sessão 1 - 05/2026', $log->label);
        $this->assertNull($log->before);
        $this->assertSame(['status' => 'open'], $log->after);
        $this->assertSame('2026-05-10 12:30:15', $log->created_at->toDateTimeString());
    }

    public function test_recorder_refuses_to_run_outside_a_transaction(): void
    {
        $user = User::factory()->create();
        $session = ReconciliationSession::factory()->create();

        $this->expectException(LogicException::class);

        app(AuditRecorder::class)->record($user, AuditAction::SessionCreated, $session, 'Sessão', null, null);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(LogicException::class);

        $log->update(['label' => 'alterado']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_audit_log_survives_the_deletion_of_its_entity(): void
    {
        $user = User::factory()->create();
        $session = ReconciliationSession::factory()->create();

        DB::transaction(function () use ($user, $session): void {
            app(AuditRecorder::class)->record($user, AuditAction::SessionDeleted, $session, 'Sessão', ['status' => 'open'], null);
            $session->delete();
        });

        $this->assertSame(1, AuditLog::query()->where('auditable_id', $session->id)->count());
    }
}
