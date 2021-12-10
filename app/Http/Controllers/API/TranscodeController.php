<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use FFMpeg;

class TranscodeController extends Controller
{
    public function index(Request $request){
        set_time_limit(0);
        foreach ($request->data as $detail){
            $ffmpeg = "ffmpeg";
            $filter = '-vf "';
            $out = time().".mp4";
            $input = public_path($detail['video']);
            if ($detail['allow_cut'] == "yes"){
                $cut_from = $detail['cut']['from'];
                $cut_to = $detail['cut']['to'];
                $cut = "-ss $cut_from -to $cut_to";
            }else{
                $cut = "";
            }
            $fps = $detail['fps'];
            if ($detail['allow_crop'] == "yes"){
                $left_right = $detail['crop']['left_right'];
                $top_bottom = $detail['crop']['top_bottom'];
                $filter .= "crop=in_w-2*$left_right:in_h-2*$top_bottom,";
            }
            /*if ($detail['rotate'] == 90){
                $filter .= "transpose=1,";
            }elseif ($detail['rotate'] == 180){
                $filter .= "transpose=2,";
            }elseif ($detail['rotate'] == 270){
                $filter .= "transpose=3,";
            }*/
            if ($detail['allow_resize'] == "yes"){
                $width = $detail['resize']['width'];
                $height = $detail['resize']['height'];
                $filter .= "scale=$width:$height";
            }
            $filter .= '"';
            $pre_vid = public_path("pre/".$out);
            exec("$ffmpeg -y -r $fps -i $input $cut $filter $pre_vid 2> log.txt");
            //watermarks
            if ($detail['primary_watermark'] == "yes"){
                $pw_vid = public_path("pw/".$out);
                $pw_path = public_path($detail['pw']['path']);
                $pw_top = $detail['pw']['top'];
                $pw_left = $detail['pw']['left'];
                exec("$ffmpeg -y -i $pre_vid -i $pw_path -filter_complex \"[1][0]scale2ref=w='iw*5/100':h='ih*5/100'[wm][vid];[vid][wm]overlay=$pw_left:$pw_top'\" $pw_vid 2> pw.txt");
            }else{
                $pw_vid = $pre_vid;
            }
            if ($detail['random_watermark'] == "yes"){
                $rw_vid = public_path("rw/".$out);
                $rw_path = public_path($detail['rw']['path']);
                $x = "if(eq(mod(n\,30)\,0)\,sin(random(1))*W\,x)";
                $y = "if(eq(mod(n\,30)\,0)\,sin(random(1))*H\,y)";
                $rw_from = $detail['rw']['from'];
                $rw_to = $detail['rw']['to'];
                $rw_between = "enable='between(t,$rw_from,$rw_to)'";
                exec("ffmpeg -y -i $pw_vid -i $rw_path -filter_complex \"[1][0]scale2ref=w='iw*5/100':h='ih*5/100'[wm][vid];[vid][wm]overlay=$x:$y:$rw_between'\" $rw_vid 2> rw.txt");
            }else{
                $rw_vid = $pw_vid;
            }
            if ($detail['full_watermark'] == "yes"){
                $fw_vid = public_path("fw/".$out);
                $fw_path = public_path($detail['fw']['path']);
                $fw_from = $detail['fw']['from'];
                $fw_to = $detail['fw']['to'];
                $fw_between = "enable='between(t,$fw_from,$fw_to)'";
                exec("ffmpeg -y -i $rw_vid -i $fw_path -filter_complex \"[1][0]scale2ref=w='iw*100/100':h='ih*100/100'[wm][vid];[vid][wm]overlay=0:0:$fw_between\" $fw_vid 2> fw.txt");

            }else{
                $fw_vid = $rw_vid;
            }
            return response()->json($fw_vid);
        }
        //exec("ffmpeg -y -i $pre -i logo.jpg -filter_complex \"[1][0]scale2ref=w='iw*100/100':h='ih*100/100'[wm][vid];[vid][wm]overlay=0:0:enable='between(t,2,4)'\" full.mp4");

    }
}
