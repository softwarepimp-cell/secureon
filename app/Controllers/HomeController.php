<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Plan;

class HomeController extends Controller
{
    public function home()
    {
        $plans = Plan::allActive();
        $popularPlanId = null;
        if (!empty($plans)) {
            $popularIndex = count($plans) > 1 ? 1 : 0;
            $popularPlanId = (int)$plans[$popularIndex]['id'];
        }
        $this->view('marketing/home', [
            'plans' => $plans,
            'popular_plan_id' => $popularPlanId,
        ]);
    }

    public function pricing()
    {
        $plans = Plan::allActive();
        $popularPlanId = null;
        if (!empty($plans)) {
            $popularIndex = count($plans) > 1 ? 1 : 0;
            $popularPlanId = (int)$plans[$popularIndex]['id'];
        }
        $this->view('marketing/pricing', [
            'plans' => $plans,
            'popular_plan_id' => $popularPlanId,
        ]);
    }
}

