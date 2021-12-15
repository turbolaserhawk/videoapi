<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function position_top_left($w_x,$w_y){
        return "$w_x:$w_y";
    }
    public function position_top_right($w_x,$w_y){
        return "W-w-$w_x:$w_y";
    }
    public function position_bottom_left($w_x,$w_y){
        return "$w_x:H-h-$w_y";
    }
    public function position_bottom_right($w_x,$w_y){
        return "W-w-$w_x:H-h-$w_y";
    }
    public function position_center($w_x,$w_y){
        return "W/2-w/2-$w_x/2:H/2-h/2-$w_y/2";
    }
}
