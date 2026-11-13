<?php

namespace App\Http\Controllers\Web;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends WebController
{
    public function privacy(): Response
    {
        return $this->page('legal/privacy');
    }

    private function page(string $component): Response
    {
        return Inertia::render($component, ['legal' => config('legal')]);
    }
}
