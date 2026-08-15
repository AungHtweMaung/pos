<?php

namespace App\Http\Controllers\Shifts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\CloseShiftRequest;
use App\Http\Requests\Shifts\OpenShiftRequest;
use App\Models\Sale;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * The cashier's own End-of-Shift page (spec §8.4). Shows the currently
     * open shift with live expected cash + a close form, or an open-shift
     * form when none is running. Also lists the user's recent shifts.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $open = Shift::openFor($user->id)->first();

        $current = null;
        if ($open) {
            $expected = $open->expectedCash();
            $current = [
                'id' => $open->id,
                'opened_at' => $open->opened_at,
                'opening_float' => (float) $open->opening_float,
                'expected_cash' => $expected,
                'cash_sales' => round($expected - (float) $open->opening_float, 2),
            ];
        }

        $recent = Shift::where('cashier_id', $user->id)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => $this->formatShift($s));

        return Inertia::render('Shifts/Index', [
            'current' => $current,
            'recent' => $recent,
        ]);
    }

    public function open(OpenShiftRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Spec §11: one active shift per cashier at a time.
        if (Shift::openFor($user->id)->exists()) {
            return back()->with('error', 'You already have an open shift.');
        }

        Shift::create([
            'cashier_id' => $user->id,
            'opened_at' => now(),
            'opening_float' => $request->input('opening_float'),
        ]);

        return back()->with('success', 'Shift opened.');
    }

    public function close(CloseShiftRequest $request, Shift $shift): RedirectResponse
    {
        // A cashier can only close their own shift; admin can close any.
        $user = $request->user();
        abort_if(!$user->isAdmin() && $shift->cashier_id !== $user->id, 403);

        if (!$shift->isOpen()) {
            return back()->with('error', 'This shift is already closed.');
        }

        $counted = round((float) $request->input('counted_cash'), 2);
        $expected = $shift->expectedCash();

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'difference' => round($counted - $expected, 2),
        ]);

        return redirect()
            ->route('shift.index')
            ->with('success', 'Shift closed and reconciled.');
    }

    /**
     * Shift history across all cashiers — admin only (spec §8.4, gated by
     * view-all-shifts in web.php).
     */
    public function history(Request $request): Response
    {
        $shifts = Shift::with('cashier:id,name,username')
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->paginate(20)
            ->through(fn ($s) => $this->formatShift($s, withCashier: true));

        return Inertia::render('Shifts/History', [
            'shifts' => $shifts,
        ]);
    }

    private function formatShift(Shift $s, bool $withCashier = false): array
    {
        $data = [
            'id' => $s->id,
            'opened_at' => $s->opened_at,
            'closed_at' => $s->closed_at,
            'opening_float' => (float) $s->opening_float,
            'expected_cash' => $s->expected_cash === null ? null : (float) $s->expected_cash,
            'counted_cash' => $s->counted_cash === null ? null : (float) $s->counted_cash,
            'difference' => $s->difference === null ? null : (float) $s->difference,
        ];

        if ($withCashier) {
            $data['cashier'] = [
                'id' => $s->cashier->id,
                'name' => $s->cashier->name,
                'username' => $s->cashier->username,
            ];
        }

        return $data;
    }
}
