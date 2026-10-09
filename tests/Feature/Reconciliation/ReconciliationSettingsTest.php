<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\GrantUserPermission;
use App\Actions\Conciliation\UpdateReconciliationSettings;
use App\Enums\AuditAction;
use App\Enums\UserPermission;
use App\Livewire\Reconciliation\Settings;
use App\Models\AuditLog;
use App\Models\ReconciliationSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class ReconciliationSettingsTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    protected function administrator(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_only_who_may_configure_the_tolerance_reaches_the_screen(): void
    {
        $operator = $this->operator();
        $administrator = $this->administrator();
        $trusted = $this->operator();
        app(GrantUserPermission::class)->handle($administrator, $trusted, UserPermission::ConfigureTolerance);

        $this->get(route('reconciliation.settings'))->assertRedirect(route('login'));
        $this->actingAs($operator)->get(route('reconciliation.settings'))->assertForbidden();
        $this->actingAs($operator)->get(route('sessions.index'))->assertDontSee(__('conciliation.settings.nav'));
        $this->actingAs($trusted)->get(route('reconciliation.settings'))->assertOk();
        $this->actingAs($administrator)->get(route('reconciliation.settings'))->assertOk()->assertSee(__('conciliation.settings.title'));
        $this->actingAs($administrator)->get(route('sessions.index'))->assertSee(__('conciliation.settings.nav'));

        Livewire::actingAs($operator)->test(Settings::class)->assertForbidden();

        $this->expectException(AuthorizationException::class);

        app(UpdateReconciliationSettings::class)->handle($operator, 100, null, null, 1000);
    }

    public function test_screen_shows_the_values_in_effect_and_saves_new_ones(): void
    {
        $this->travelTo('2026-08-10 12:00:00');
        $this->useTolerance(50, 100, 20000);
        $administrator = $this->administrator();

        Livewire::actingAs($administrator)
            ->test(Settings::class)
            ->assertSet('form.toleranceAmount', '0,50')
            ->assertSet('form.tolerancePercent', '1')
            ->assertSet('form.toleranceCap', '200,00')
            ->assertSet('form.surchargeCapPercent', '10')
            ->assertSee(__('conciliation.settings.never_changed'))
            ->set('form.toleranceAmount', 'R$ 1,00')
            ->set('form.tolerancePercent', '1,5 %')
            ->set('form.toleranceCap', '1.250,00')
            ->set('form.surchargeCapPercent', '12,25')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.settings.saved'))
            ->assertSet('form.toleranceAmount', '1,00')
            ->assertSet('form.tolerancePercent', '1,5')
            ->assertSee($administrator->name);

        $settings = ReconciliationSettings::current();

        $this->assertSame(100, $settings->tolerance_cents);
        $this->assertSame(150, $settings->tolerance_basis_points);
        $this->assertSame(125000, $settings->tolerance_cap_cents);
        $this->assertSame(1225, $settings->surcharge_cap_basis_points);
        $this->assertSame($administrator->id, $settings->updated_by);

        $log = AuditLog::query()->where('action', AuditAction::ReconciliationSettingsChanged)->sole();

        $this->assertTrue($log->user->is($administrator));
        $this->assertSame(['tolerance_cents' => 50, 'tolerance_basis_points' => 100, 'tolerance_cap_cents' => 20000, 'surcharge_cap_basis_points' => 1000], $log->before);
        $this->assertSame(['tolerance_cents' => 100, 'tolerance_basis_points' => 150, 'tolerance_cap_cents' => 125000, 'surcharge_cap_basis_points' => 1225], $log->after);
        $this->assertSame('2026-08-10 12:00:00', $log->created_at->format('Y-m-d H:i:s'));

        Livewire::actingAs($administrator)->test(Settings::class)->call('save')->assertHasNoErrors();

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::ReconciliationSettingsChanged)->count());
    }

    public function test_tolerance_may_be_only_an_amount_or_only_a_percentage(): void
    {
        $administrator = $this->administrator();

        Livewire::actingAs($administrator)
            ->test(Settings::class)
            ->set('form.toleranceAmount', '2,00')
            ->set('form.tolerancePercent', '')
            ->set('form.toleranceCap', '200,00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form.toleranceCap', '');

        $this->assertSame([200, null, null], array_values(ReconciliationSettings::current()->only(['tolerance_cents', 'tolerance_basis_points', 'tolerance_cap_cents'])));

        Livewire::actingAs($administrator)
            ->test(Settings::class)
            ->set('form.toleranceAmount', '0')
            ->set('form.tolerancePercent', '2')
            ->set('form.toleranceCap', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([0, 200, null], array_values(ReconciliationSettings::current()->only(['tolerance_cents', 'tolerance_basis_points', 'tolerance_cap_cents'])));
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_refused_with_the_reason(string $field, string $value, string $error): void
    {
        $this->useTolerance(50, 100, 20000);

        $screen = Livewire::actingAs($this->administrator())
            ->test(Settings::class)
            ->set('form.'.$field, $value)
            ->call('save');

        $this->assertStringContainsString(__('conciliation.settings.errors.'.$error), $screen->html());
        $this->assertSame(50, ReconciliationSettings::current()->tolerance_cents);
        $this->assertSame(1000, ReconciliationSettings::current()->surcharge_cap_basis_points);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::ReconciliationSettingsChanged)->count());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidValues(): array
    {
        return [
            'tolerância negativa' => ['toleranceAmount', '-1,00', 'tolerance_amount'],
            'tolerância vazia' => ['toleranceAmount', '', 'tolerance_amount'],
            'tolerância em texto' => ['toleranceAmount', 'um real', 'tolerance_amount'],
            'tolerância acima do limite' => ['toleranceAmount', '1.000,01', 'tolerance_amount'],
            'percentual acima de 100' => ['tolerancePercent', '100,01', 'tolerance_percent'],
            'percentual negativo' => ['tolerancePercent', '-1', 'tolerance_percent'],
            'percentual com três casas' => ['tolerancePercent', '1,234', 'tolerance_percent'],
            'teto negativo' => ['toleranceCap', '-200,00', 'tolerance_cap'],
            'acréscimo acima de 100' => ['surchargeCapPercent', '101', 'surcharge_cap'],
            'acréscimo vazio' => ['surchargeCapPercent', '', 'surcharge_cap'],
        ];
    }

    public function test_action_refuses_values_out_of_range(): void
    {
        $administrator = $this->administrator();

        foreach ([
            [[-1, null, null, 1000], 'tolerance_amount'],
            [[100001, null, null, 1000], 'tolerance_amount'],
            [[50, 10001, null, 1000], 'tolerance_percent'],
            [[50, 100, -1, 1000], 'tolerance_cap'],
            [[50, 100, 20000, 10001], 'surcharge_cap'],
        ] as [$arguments, $error]) {
            try {
                app(UpdateReconciliationSettings::class)->handle($administrator, ...$arguments);
                $this->fail('Accepted: '.$error);
            } catch (ActionRefusedException $exception) {
                $this->assertSame(__('conciliation.settings.errors.'.$error), $exception->getMessage());
            }
        }
    }

    public function test_new_tolerance_applies_only_to_the_next_runs(): void
    {
        $administrator = $this->administrator();
        $july = $this->sessionWithFiles('2026-07-01', state: 'open');
        $this->authorization($july, 'PADARIA PERNAMBUCANA', 100000);
        $near = $this->payment($july, 'PADARIA PERNAMBUCANA', 100080);
        $julyRun = $this->reconcile($july);

        $this->assertNull($near->link);
        $this->assertSame(50, $julyRun->tolerance_cents);

        app(UpdateReconciliationSettings::class)->handle($administrator, 100, null, null, 1000);

        $this->assertSame(50, $julyRun->refresh()->tolerance_cents);
        $this->assertNull($near->refresh()->link);

        $august = $this->sessionWithFiles('2026-08-01', state: 'open');
        $this->authorization($august, 'GRAFICA SUL', 100000);
        $nearToo = $this->payment($august, 'GRAFICA SUL', 100080);
        $augustRun = $this->reconcile($august);

        $this->assertSame(100, $augustRun->tolerance_cents);
        $this->assertNotNull($nearToo->link);

        app(UpdateReconciliationSettings::class)->handle($administrator, 50, 100, 20000, 1000);

        $september = $this->sessionWithFiles('2026-09-01', state: 'open');
        $this->authorization($september, 'POSTO ALFA', 100000);
        $byPercent = $this->payment($september, 'POSTO ALFA', 100800);
        $this->reconcile($september);

        $this->assertNotNull($byPercent->link);
    }
}
