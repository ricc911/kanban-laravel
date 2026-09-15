<?php

namespace App\Http\Controllers;

use App\Services\Plans\PublicPlanCatalog;
use Illuminate\Contracts\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(PublicPlanCatalog $plans): View
    {
        return view('public.home', ['plans' => $plans->commercial()]);
    }
}
