<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function show(Request $request): View
    {
        return view('tenant.status', [
            'user' => $request->user(),
        ]);
    }
}
