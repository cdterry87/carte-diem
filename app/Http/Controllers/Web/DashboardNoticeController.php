<?php

namespace App\Http\Controllers\Web;

use App\Enums\DashboardNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardNoticeController extends WebController
{
    public function dismiss(Request $request, DashboardNotice $notice): RedirectResponse
    {
        $this->actor($request)->dismiss($notice);

        return back();
    }
}
