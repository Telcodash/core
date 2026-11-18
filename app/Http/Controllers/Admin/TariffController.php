<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTariffRequest;
use App\Services\Subscriptions\Data\Tariff;
use App\Services\Subscriptions\SubscriptionDirectory;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Support\Section;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tariff catalogue (administrators only), held by the Subscription Daemon.
 */
class TariffController extends Controller
{
    public function index(SubscriptionDirectory $subscriptions): Response
    {
        [$tariffs, $error] = Section::load(
            fn () => array_map(fn (Tariff $tariff) => $tariff->toArray(), $subscriptions->tariffs()),
            'The tariff catalogue is unavailable right now. Please try again shortly.',
            'Tariff catalogue unavailable',
        );

        return Inertia::render('admin/tariffs/index', [
            'tariffs' => $tariffs ?? [],
            'error' => $error,
        ]);
    }

    public function store(SaveTariffRequest $request, SubscriptionDirectory $subscriptions): RedirectResponse
    {
        /** @var array{name: string, description: ?string, monthly_price_pence: int, minutes: ?int, texts: ?int, data_mb: ?int, eu_roaming: bool, international_roaming: bool, five_g_plus: bool, available: bool} $fields */
        $fields = $request->tariffFields();

        return $this->attempt(fn () => $subscriptions->createTariff($fields), 'Tariff created.');
    }

    public function update(SaveTariffRequest $request, string $tariff, SubscriptionDirectory $subscriptions): RedirectResponse
    {
        return $this->attempt(fn () => $subscriptions->updateTariff($tariff, $request->tariffFields()), 'Tariff updated.');
    }

    /**
     * Run a catalogue write; the daemon's rule violations (e.g. a frozen price) go back to the form.
     *
     * @param  callable(): Tariff  $write
     */
    private function attempt(callable $write, string $success): RedirectResponse
    {
        try {
            $write();
        } catch (TaskFailedException $e) {
            return back()->withErrors(['tariff' => preg_replace('/^Invalid payload: /', '', $e->reason)]);
        } catch (QueueServiceException) {
            return back()->withErrors(['tariff' => 'The tariff catalogue is unavailable right now. Please try again shortly.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($success)]);

        return to_route('tariffs.index');
    }
}
