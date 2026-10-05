<?php

namespace App\Http\Controllers;

use App\Dealer;
use App\CenterChief;
use App\Client;
use App\Transaction;
use App\RedeemedHistory;
use App\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = auth()->user();
        $profile = null;
        $transactions_details = collect();
        $availablePoints = 0;

        if ($user->usesSrpPricing()) {
            $profile = CenterChief::where('user_id', $user->id)->first();
            $transactions_details = TransactionDetail::where('dealer_id', $user->id)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->orderBy('id', 'desc')
                ->get();

            $totalEarnedPoints = $transactions_details->sum('points_dealer');
            $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
            $availablePoints = $totalEarnedPoints - $redeemedPoints;
        } elseif ($user->isDealerLike()) {
            $profile = Dealer::with('sales')->where('user_id', $user->id)->first();
            $transactions_details = TransactionDetail::where('dealer_id', $user->id)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->orderBy('id', 'desc')
                ->get();

            if ($profile) {
                $totalEarnedPoints = $transactions_details->sum('points_dealer');
                $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
                $availablePoints = $totalEarnedPoints - $redeemedPoints;
            }
        } else {
            $profile = Client::with('transactions')->where('user_id', $user->id)->first();

            if ($profile) {
                $transactions_details = TransactionDetail::where('client_id', $profile->id)
                    ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                        $query->where('status', 'Completed');
                    })
                    ->orderBy('id', 'desc')
                    ->get();

                $totalEarnedPoints = $transactions_details->sum('points_client');
                $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
                $availablePoints = $totalEarnedPoints - $redeemedPoints;
            }
        }

        $total_sales = $transactions_details->sum(function ($transaction) {
            return $transaction->price * $transaction->qty;
        });
        $total_transactions = $transactions_details->count();

        return view('account', [
            'profile' => $profile,
            'available_points' => $availablePoints,
            'transactions_details' => $transactions_details,
            'total_sales' => $total_sales,
            'total_transactions' => $total_transactions,
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_type' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        if (!empty($validated['password']) && !Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($user->usesSrpPricing()) {
            $profile = CenterChief::where('user_id', $user->id)->firstOrFail();
            $profile->name = $validated['name'];
            $profile->email = $validated['email'];
            $profile->number = $validated['number'] ?? $profile->number;
            $profile->save();

            // The Dealer record remains an access bridge for dealer features,
            // but Center Chief profile details are owned by center_chiefs.
            Dealer::where('user_id', $user->id)->update([
                'name' => $profile->name,
                'email_address' => $profile->email,
                'number' => $profile->number,
                'updated_at' => now(),
            ]);
        } elseif ($user->isDealerLike()) {
            $profile = Dealer::firstOrNew(['user_id' => $user->id]);
            $profile->name = $validated['name'];
            $profile->email_address = $validated['email'];
            $profile->number = $validated['number'] ?? $profile->number;
            $profile->address = $validated['address'] ?? $profile->address;
            $profile->facebook = $validated['facebook'] ?? $profile->facebook;
            $profile->store_name = $validated['store_name'] ?? $profile->store_name;
            $profile->store_type = $validated['store_type'] ?? $profile->store_type;
            $profile->save();
        } else {
            $profile = Client::firstOrNew(['user_id' => $user->id]);
            $profile->name = $validated['name'];
            $profile->email_address = $validated['email'];
            $profile->number = $validated['number'] ?? $profile->number;
            $profile->address = $validated['address'] ?? $profile->address;
            $profile->save();
        }

        return back()->with('success', 'Account updated successfully.');
    }
}
