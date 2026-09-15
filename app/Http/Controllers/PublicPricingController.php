<?php

namespace App\Http\Controllers;

use App\Services\Plans\PublicPlanCatalog;
use Illuminate\Contracts\View\View;

class PublicPricingController extends Controller
{
    public function __invoke(PublicPlanCatalog $plans): View
    {
        return view('public.pricing', ['plans' => $plans->commercial()]);
    }
}
