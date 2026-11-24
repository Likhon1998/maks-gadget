<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShopScoped;
use App\Models\ServiceTechnician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceTechnicianController extends Controller
{
    use ShopScoped;

    public function index(): View
    {
        $technicians = ServiceTechnician::where('shop_id', $this->shopId())
            ->withCount(['jobs as completed_jobs_count' => fn ($q) => $q->completed()])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (ServiceTechnician $tech) {
                $tech->earned = $tech->earnedCommission();
                $tech->paid = $tech->paidCommission();
                $tech->outstanding = $tech->outstandingCommission();
                $tech->advance = $tech->advanceBalance();

                return $tech;
            });

        return view('services.technicians.index', compact('technicians'));
    }

    public function create(): View
    {
        return view('services.technicians.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['shop_id'] = $this->shopId();
        $data['is_active'] = $request->boolean('is_active', true);

        ServiceTechnician::create($data);

        return redirect()
            ->route('services.technicians.index')
            ->with('success', 'Service technician added.');
    }

    public function storeFromPos(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user?->canAccessPos()) {
            return response()->json(['success' => false, 'message' => 'POS access required.'], 403);
        }

        $data = $this->validated($request);
        $data['shop_id'] = $this->shopId();
        $data['is_active'] = true;

        $technician = ServiceTechnician::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Technician added.',
            'technician' => [
                'id' => $technician->id,
                'name' => $technician->name,
                'phone' => $technician->phone,
                'commission_percent' => (float) $technician->commission_percent,
                'advance_balance' => 0.0,
                'outstanding_commission' => 0.0,
            ],
        ]);
    }

    public function edit(ServiceTechnician $technician): View
    {
        $this->authorizeShop($technician);

        return view('services.technicians.edit', compact('technician'));
    }

    public function update(Request $request, ServiceTechnician $technician): RedirectResponse
    {
        $this->authorizeShop($technician);
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $technician->update($data);

        return redirect()
            ->route('services.technicians.index')
            ->with('success', 'Technician updated.');
    }

    public function destroy(ServiceTechnician $technician): RedirectResponse
    {
        $this->authorizeShop($technician);

        if ($technician->jobs()->exists()) {
            $technician->update(['is_active' => false]);

            return redirect()
                ->route('services.technicians.index')
                ->with('success', 'Technician has service bills, so they were deactivated instead of deleted.');
        }

        $technician->delete();

        return redirect()
            ->route('services.technicians.index')
            ->with('success', 'Technician removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
