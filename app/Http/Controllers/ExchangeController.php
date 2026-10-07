<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExchangeController extends Controller
{
    public function processExchange(Request $request, Order $order, OrderRefundService $refunds)
    {
        $request->validate([
            'return_product_id' => 'required|exists:products,id',
            'return_qty' => 'required|integer|min:1',
        ]);

        try {
            $exchange = $refunds->resolveExchange(
                (int) $order->id,
                (int) $request->return_product_id,
                (int) $request->return_qty,
                Auth::user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pos.index', [
            'exchange_order' => $order->id,
            'return_product' => (int) $request->return_product_id,
            'return_qty' => (int) $request->return_qty,
            'credit' => $exchange['credit'],
        ]);
    }
}
