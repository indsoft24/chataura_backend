<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WithdrawalRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = WithdrawalRequest::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('status') && in_array($request->status, [WithdrawalRequest::STATUS_PENDING, WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_REJECTED], true)) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(20)->withQueryString();
        return view('admin.withdrawals.index', compact('requests'));
    }

    public function showApproveForm(WithdrawalRequest $withdrawal): View|RedirectResponse
    {
        if ($withdrawal->status !== WithdrawalRequest::STATUS_PENDING) {
            return redirect()->route('admin.withdrawals.index')->with('error', 'Request is not pending.');
        }
        return view('admin.withdrawals.approve', compact('withdrawal'));
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== WithdrawalRequest::STATUS_PENDING) {
            return redirect()->route('admin.withdrawals.index')->with('error', 'Request is not pending.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $withdrawal->status = WithdrawalRequest::STATUS_APPROVED;
        $withdrawal->admin_note = $validated['admin_note'] ?? null;
        $withdrawal->save();

        return redirect()->route('admin.withdrawals.index')->with('success', 'Withdrawal approved.');
    }

    public function showRejectForm(WithdrawalRequest $withdrawal): View|RedirectResponse
    {
        if ($withdrawal->status !== WithdrawalRequest::STATUS_PENDING) {
            return redirect()->route('admin.withdrawals.index')->with('error', 'Request is not pending.');
        }
        return view('admin.withdrawals.reject', compact('withdrawal'));
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== WithdrawalRequest::STATUS_PENDING) {
            return redirect()->route('admin.withdrawals.index')->with('error', 'Request is not pending.');
        }

        $validated = $request->validate([
            'admin_note' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($withdrawal, $validated) {
            $withdrawal->status = WithdrawalRequest::STATUS_REJECTED;
            $withdrawal->admin_note = $validated['admin_note'];
            $withdrawal->save();

            $user = User::where('id', $withdrawal->user_id)->lockForUpdate()->first();
            if ($user) {
                $user->increment('gems', $withdrawal->gems_amount);
            }
        });

        return redirect()->route('admin.withdrawals.index')->with('success', 'Withdrawal rejected and gems refunded.');
    }
}
