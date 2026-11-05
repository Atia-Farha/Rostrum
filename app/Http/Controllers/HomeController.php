<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;

class HomeController extends Controller
{
    public function index()
    {
        // The landing page is a marketing surface, not a working page. Its
        // nav carries the logo and one button back into the product, but
        // not the four dashboard links — a signed-in visitor already
        // reaches Overview, Account Settings and History through the rail
        // on every working page, so listing them in the nav would put the
        // same destinations in front of them twice.
        View::share('hideDashboardLinks', true);

        return view('home');
    }
}
