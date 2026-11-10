<?php

namespace App\Support;

use App\OrderDetail;
use App\MegaDealer;
use App\DmsMegaDealer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Reserve supplier stock on fulfilment and credit the buyer on verified receipt. */
class MegaDealerOrderService
{
    private $context;

    public function __construct(OfflinePortalContext $context)
    {
        $this->context = $context;
    }

    public function complete($orderId, $actor, $remarks)
    {
        return $this->transition($orderId, $actor, $remarks, false);
    }

    public function fulfil($orderId, $actor, $remarks)
    {
        return $this->transition($orderId, $actor, $remarks, true);
    }

    private function transition($orderId, $actor, $remarks, $fulfilling)
    {
        // This probe is outside the transaction; the locked record is checked again.
        $probe = OrderDetail::find($orderId);
        if (!$probe) throw new HttpException(404, 'Order not found.');
        $source = $probe->supplier_source;
        if (!in_array($source, ['admin_crms', 'dms_prei'], true)) throw new HttpException(422, 'Invalid Mega Dealer source.');
        $transition = function () use ($orderId, $actor, $remarks, $fulfilling, $source) {
            return $this->lockedTransition($orderId, $actor, $remarks, $fulfilling, $source);
        };
        // DMS supplier/user locks live on their own connection and stay held
        // until the primary order reservation has committed.
        return $source === 'dms_prei' ? DB::connection('dms')->transaction($transition, 3) : $transition();
    }

    private function lockedTransition($orderId, $actor, $remarks, $fulfilling, $source)
    {
        return DB::transaction(function () use ($orderId, $actor, $remarks, $fulfilling, $source) {
            // Locking reads must precede relationship/catalog reads. Under
            // MySQL repeatable-read, an eager relationship read before waiting
            // on the stock-owner lock would retain a stale stock snapshot.
            $order = OrderDetail::lockForUpdate()->find($orderId);
            if (!$order) throw new HttpException(404, 'Order not found.');
            if ($order->supplier_source !== $source) throw new HttpException(409, 'The supplier changed. Refresh the order before continuing.');
            $supplierModel = $source === 'dms_prei' ? DmsMegaDealer::class : MegaDealer::class;
            $supplier = $order->mega_dealer_id ? $supplierModel::lockForUpdate()->find($order->mega_dealer_id) : null;
            $supplierUser = $supplier ? $supplier->user()->lockForUpdate()->first() : null;
            if (!$supplier || !$this->context->isMegaDealerUser($supplierUser) || $order->ad_id) {
                throw new HttpException(422, 'This order does not have a valid Mega Dealer supplier.');
            }
            $supplier->setRelation('user', $supplierUser);
            $order->setRelation($source === 'dms_prei' ? 'dmsMegaDealer' : 'megaDealer', $supplier);
            $actorId = (int) optional($actor)->id;
            $admin = $actor && strtolower(trim((string) $actor->role)) === 'admin';
            $authorizedId = $fulfilling ? (int) $supplier->user_id : (int) $order->dealer_id;
            if (!$actorId || (!$admin && ($actorId !== $authorizedId || ($fulfilling && $source === 'dms_prei')))) {
                throw new HttpException(403, $fulfilling
                    ? 'Only the assigned Mega Dealer can fulfil this order.'
                    : 'Only the ordering Dealer can verify receipt and complete this order.');
            }
            if ($order->status === 'Completed') return [$order, false];
            if ($fulfilling && $order->status === 'For Verification') return [$order, false];
            $expectedStatus = $fulfilling ? 'Pending' : 'For Verification';
            if ($order->status !== $expectedStatus) throw new HttpException(409, $fulfilling
                ? 'Only a pending Mega Dealer order can be fulfilled.'
                : 'The Mega Dealer must fulfil this order before you can verify receipt.');
            if (!is_string($remarks)) {
                throw ValidationException::withMessages(['remarks' => ['Enter remarks as text.']]);
            }
            $remarks = trim($remarks);
            if ($remarks === '' || mb_strlen($remarks) > 255) {
                throw ValidationException::withMessages(['remarks' => ['Enter remarks of up to 255 characters.']]);
            }
            if ((int) $order->qty < 1) {
                throw ValidationException::withMessages(['stock' => ['This order has an invalid quantity.']]);
            }
            if ($fulfilling) {
                $product = $this->context->megaDealerProducts($supplier)->first(function ($product) use ($order) {
                    return $this->context->matchesProduct($order, $product);
                });
                if (!$product) throw ValidationException::withMessages(['stock' => ['This product is no longer available in the supplier sales territory.']]);
                if ((int) $order->qty > $this->context->supplierStock($product, $supplier)) {
                    throw ValidationException::withMessages(['stock' => ['The Mega Dealer does not have enough available stock to fulfil this order.']]);
                }
            }
            // Verification stock is already reserved by the outgoing ledger.
            // Receipt changes only buyer availability, without deducting it twice.
            $order->status = $fulfilling ? 'For Verification' : 'Completed';
            if (Schema::hasColumn('order_details', 'completed_at')) $order->completed_at = $fulfilling ? null : date('Y-m-d H:i:s');
            if (Schema::hasColumn('order_details', 'remarks')) $order->remarks = $remarks;
            $order->save();
            return [$order, true];
        }, 3);
    }
}
