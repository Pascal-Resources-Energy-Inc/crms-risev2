<?php

namespace App\Support;

use App\OrderDetail;

/** Number AD orders from the database's allocated ID, including concurrent saves. */
class OrderReference
{
    public static function save(OrderDetail $order)
    {
        return $order->getConnection()->transaction(function () use ($order) {
            // The required reference remains private until this transaction commits.
            // Never predict MAX(id) + 1: another account may insert in between.
            $order->transaction_id = 'PRORD-PENDING-' . bin2hex(random_bytes(16));
            $order->save();
            $order->transaction_id = 'PRORD-' . str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
            $order->save();
            return $order;
        });
    }
}
