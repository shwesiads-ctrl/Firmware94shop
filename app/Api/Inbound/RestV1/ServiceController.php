<?php

namespace App\Api\Inbound\RestV1;

use App\Models\Service;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;

class ServiceController
{
    public function index(Request $request, PricingService $pricing)
    {
        $services = Service::where('is_active', true)->with('category', 'fields')->paginate(25);

        $services->getCollection()->transform(function (Service $service) use ($pricing, $request) {
            return $this->present($service, $pricing, $request);
        });

        return response()->json($services);
    }

    public function show(Request $request, Service $service, PricingService $pricing)
    {
        if (! $service->is_active) {
            abort(404);
        }

        return response()->json(['data' => $this->present($service, $pricing, $request)]);
    }

    private function present(Service $service, PricingService $pricing, Request $request): array
    {
        return [
            'id' => $service->id,
            'category' => $service->category->name,
            'name' => $service->name,
            'description' => $service->description,
            'price' => $pricing->priceFor($service, $request->user()),
            'currency' => 'USD',
            'estimated_time_minutes' => $service->estimated_time_minutes,
            'fields' => $service->fields->map(fn ($f) => [
                'key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'required' => $f->required,
            ]),
        ];
    }
}
