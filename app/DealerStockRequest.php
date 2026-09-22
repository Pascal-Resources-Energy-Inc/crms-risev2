<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DealerStockRequest extends Model
{
    protected $fillable = ['dealer_id', 'product_id', 'quantity', 'attachments', 'status', 'remarks', 'reviewed_by', 'reviewed_at', 'approved_order_id'];
    protected $dates = ['reviewed_at'];
    protected $casts = ['attachments' => 'array'];

    public function dealer() { return $this->belongsTo(User::class, 'dealer_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
