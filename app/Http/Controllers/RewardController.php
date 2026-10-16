<?php

namespace App\Http\Controllers;

use App\Reward;
use App\Dealer;
use App\Client;
use App\RedeemedHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RewardController extends Controller
{
    /**
     * Rewards and redemptions always belong to the signed-in user.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
        {
            // Get all rewards
            $rewards = Reward::orderBy('created_at', 'desc')->get();
            
            // ADD THIS: Calculate claims count and limit reached status for each reward
            foreach ($rewards as $reward) {
                $reward->claims_count = RedeemedHistory::where('reward_id', $reward->id)->count();
                
                $reward->is_limit_reached = false;
                if ($reward->redemption_limit !== null && $reward->redemption_limit > 0) {
                    $reward->is_limit_reached = $reward->claims_count >= $reward->redemption_limit;
                }
            }

            $customer = null;
            $dealer = null;
            $totalPoints = 0;
            $redeemedPoints = 0;
            $availablePoints = 0;

            if (auth()->user()->role == "Client") {
                $customer = Client::with('transactions')
                    ->where('user_id', auth()->user()->id)
                    ->first();
                
                if ($customer) {
                    $totalPoints = $customer->transactions->sum('points_client');
                }
            }

            if (auth()->user()->isDealerLike()) {
                $dealer = Dealer::with('sales')
                    ->where('user_id', auth()->user()->id)
                    ->first();
                
                if ($dealer) {
                    $totalPoints = $dealer->sales->sum('points_dealer');
                }
            }

            $redeemedPoints = abs(RedeemedHistory::where('user_id', auth()->user()->id)
                ->sum('points_amount'));
            
            $availablePoints = $totalPoints - $redeemedPoints;

            return view('rewards', compact(
                'rewards', 
                'customer', 
                'dealer', 
                'availablePoints', 
                'totalPoints', 
                'redeemedPoints'
            ));
        }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'price_reward' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
            'points_required' => 'required|integer|min:1',
            'redemption_limit' => 'nullable|integer|min:1',
            'expiry_date' => 'nullable|date|after:today',
            'image' => 'nullable|mimes:jpeg,jpg,png,gif,JPG,JPEG|max:5120',
            'is_active' => 'nullable|boolean'
        ]);

        $reward = new Reward();
        $reward->price_reward = $validated['price_reward'];
        $reward->description = $validated['description'];
        $reward->points_required = $validated['points_required'];
        $reward->redemption_limit = $validated['redemption_limit'] ?? null;
        $reward->expiry_date = $validated['expiry_date'] ?? null;
        $reward->is_active = $request->has('is_active') ? 1 : 0;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('images/rewards'), $imageName);
            $reward->image = 'images/rewards/' . $imageName;
        }

        $reward->save();

        return redirect()->route('rewards')
            ->with('success', 'Reward added successfully!');
    }

    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        
        if ($reward->image && file_exists(public_path($reward->image))) {
            unlink(public_path($reward->image));
        }
        
        $reward->delete();

        return redirect()->route('rewards')
            ->with('success', 'Reward deleted successfully!');
    }
}
