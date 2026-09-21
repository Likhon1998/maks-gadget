<x-supply-layout title="Purchase Returns" subtitle="Return stock to suppliers and deduct from inventory." :action-url="route('supply.purchase-returns.create')" action-label="+ New Return">
    <div class="bg-white rounded-2xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900 text-white text-[11px] uppercase tracking-widest">
                <tr><th class="p-4 text-left">Return #</th><th class="p-4">Supplier</th><th class="p-4">Status</th><th class="p-4">Total</th><th class="p-4">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($returns as $return)
                    <tr>
                        <td class="p-4 font-bold">{{ $return->return_number }}</td>
                        <td class="p-4">{{ $return->supplier->name }}</td>
                        <td class="p-4 uppercase text-xs font-bold">{{ $return->status }}</td>
                        <td class="p-4">{{ format_taka($return->total_amount) }}</td>
                        <td class="p-4">{{ $return->created_at->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center text-gray-400">No purchase returns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $returns->links() }}</div>
    </div>
</x-supply-layout>
