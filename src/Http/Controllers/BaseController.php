<?php

namespace Ometra\Caronte\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class BaseController extends Controller
{
    protected function toView(string $viewPath, array $data = []): View|InertiaResponse
    {
        if ((bool) config('caronte.ui.use_inertia', false)) {
            return inertia(str_replace('.', '/', $viewPath), $data);
        }

        return view('caronte::' . $viewPath, $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function branding(): array
    {
        return (array) config('caronte.ui.branding', []);
    }
}
