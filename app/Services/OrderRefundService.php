<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Full refunds and exchange eligibility shared by the Sales Ledger and the POS terminal.
 * Business-rule failures throw InvalidArgumentException with a cashier-friendly message.
 */
class OrderRefundService
{
    public const WINDOW_DAYS = 7;

    public function __construct(
        protected AccountService $accounts,
        protected StockService $stock,
        protected OnlineOrderTrackingService $tracking,
        protected BakiService $baki,
    ) {}

    /** Same shop, own counter for floor staff, online orders admin-only. */
    public function authorize(Order $order, User $user, string $action = 'refund'): void
    {
        if ((int) $order->shop_id !== (int) $user->shop_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($order->isOnlineOrder() && ! $user->isAdminUser()) {
            abort(403, "Only admins can {$action} online orders.");
        }

        if (! $order->isOnlineOrder() && ! $user->isAdminUser() && $user->counter_id
            && (int) $order->counter_id !== (int) $user->counter_id) {
            abort(403, "You can only {$action} sales from your counter.");
        }
    }

    public function isVoided(Order $order): bool
    {
        return in_array((string) $order->status, ['refunded', 'cancelled', 'returned'], true);
    }

    public function wasExchanged(Order $order): bool
    {
        return Order::where('shop_id', $order->shop_id)->where('exchange_for_order_id', $order->id)->exists();
    }

    /** Null when refundable, otherwise the reason it is blocked. */
    public function refundBlockReason(Order $order): ?string
    {
        if ($this->isVoided($order)) {
            return 'This order has already been voided or refunded.';
        }
        if ($order->is_exchange_receipt) {
            return 'Exchange receipts cannot be refunded. Adjust from the original sale if needed.';
        }
        if ($this->wasExchanged($order)) {
            return 'This order was already exchanged. Refunding it would double-restock and cash-out incorrectly.';
        }
        if ($order->created_at < now()->subDays(self::WINDOW_DAYS)) {
            return 'The '.self::WINDOW_DAYS.'-day refund window has expired for this order.';
        }
        if ($order->isOnlineOrder()) {
            if ($order->status !== 'completed') {
                return 'Refund is only available after the order is delivered. Use Returned if it is not delivered yet.';
            }
            if (! $this->moneyWasCollected($order)) {
                return 'No payment was collected yet. Use Returned if the package came back unpaid (COD).';
            }
        }

        return null;
    }

    /** Cash the customer actually paid for this invoice (baki part excluded). */
    public function refundableCash(Order $order): float
    {
        return max(0, round($order->netPayable() - max(0, (float) ($order->credit_amount ?? 0)), 2));
    }

    public function refund(Order $order, User $user): void
    {
        $this->authorize($order, $user, 'refund');

        if ($reason = $this->refundBlockReason($order)) {
            throw new InvalidArgumentException($reason);
        }

        DB::transaction(function () use ($order, $user) {
            $order->update([
                'status' => 'refunded',
                'paid_amount' => 0,
            ]);

            foreach ($order->items as $item) {
                if ($item->product) {
                    $this->stock->restockForDocument(
                        $item->product,
                        $item->quantity,
                        'Refund - '.$order->invoice_no,
                        'order_refund',
                        $order->id,
                        'order_refund',
                        $user->id,
                    );
                }
            }

            $order->load('items.product', 'counter');
            $this->baki->reverseSaleCredit($order, $user->id);
            $this->accounts->postOrderRefund($order);

            if ($order->isOnlineOrder()) {
                $this->tracking->upsertLatestLog(
                    $order,
                    'refunded',
                    'Order refunded after payment collection.',
                    $order->shipping_courier,
                    $order->shipping_tracking_no,
                    $user->id,
                );
            }
        });
    }

    /** Null when the order can be exchanged, otherwise the reason it is blocked. */
    public function exchangeBlockReason(Order $order): ?string
    {
        if ($this->isVoided($order)) {
            return 'This order was refunded or cancelled and cannot be exchanged.';
        }
        if ($order->isOnlineOrder() && $order->status !== 'completed') {
            return 'Online orders can be exchanged only after delivery.';
        }

        return null;
    }

    /** Quantity of this product already taken back through earlier exchanges of the order. */
    public function returnedQty(Order $order, int $productId): int
    {
        return (int) Order::where('shop_id', $order->shop_id)
            ->where('exchange_for_order_id', $order->id)
            ->where('return_product_id', $productId)
            ->whereNotIn('status', ['refunded', 'cancelled', 'returned'])
            ->sum('return_qty');
    }

    /**
     * Credit for returning $qty of an item: what the customer actually paid per unit
     * (line price after the order-level discount share), never the current shelf price.
     */
    public function exchangeCredit(Order $order, OrderItem $item, int $qty): float
    {
        $merchGross = max(0, (float) $order->total_amount - (float) ($order->delivery_charge ?? 0));
        $merchNet = max(0, $merchGross - (float) ($order->discount_amount ?? 0));
        $ratio = $merchGross > 0 ? min(1, $merchNet / $merchGross) : 1;

        return round((float) $item->unit_price * $qty * $ratio, 2);
    }

    /**
     * Validate an exchange request and return the server-side credit.
     *
     * @return array{order: Order, item: OrderItem, credit: float}
     */
    public function resolveExchange(int $orderId, int $productId, int $qty, User $user): array
    {
        $order = Order::with('items')->where('shop_id', $user->shop_id)->find($orderId);
        if (! $order) {
            throw new InvalidArgumentException('Original invoice for this exchange was not found.');
        }

        $this->authorize($order, $user, 'exchange');

        if ($reason = $this->exchangeBlockReason($order)) {
            throw new InvalidArgumentException($reason);
        }

        $item = $order->items->firstWhere('product_id', $productId);
        if (! $item) {
            throw new InvalidArgumentException('The returned product is not on invoice '.$order->invoice_no.'.');
        }

        $available = (int) $item->quantity - $this->returnedQty($order, $productId);
        if ($qty < 1 || $qty > $available) {
            throw new InvalidArgumentException($available > 0
                ? "Only {$available} unit(s) of this product can still be returned from {$order->invoice_no}."
                : "All units of this product were already returned from {$order->invoice_no}.");
        }

        return ['order' => $order, 'item' => $item, 'credit' => $this->exchangeCredit($order, $item, $qty)];
    }

    public function moneyWasCollected(Order $order): bool
    {
        $method = strtolower((string) $order->payment_method);
        $isCod = in_array($method, ['cash_on_delivery', 'cod', 'cash on delivery'], true);

        if (! $isCod) {
            return (float) $order->paid_amount > 0 || $order->status === 'completed';
        }

        return $order->status === 'completed' || (float) $order->paid_amount > 0;
    }
}
