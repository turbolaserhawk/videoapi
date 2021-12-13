<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TranscodeController extends Controller
{
    public function index(Request $request){
        set_time_limit(0);
        foreach ($request->data as $detail){
            $ffmpeg = "ffmpeg";
            $filter = '-vf "';
            $out_name = time().".mp4";
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
            $pre_vid = public_path("pre/".$out_name);
            exec("$ffmpeg -y -r $fps -i $input $cut $filter $pre_vid 2> log.txt");
            //watermarks
            $watermark_path = '';
            $overlay = '';
            $total_watermarks = count($detail['iw']);
            foreach ($detail['iw'] as $key => $watermark){
                $watermark_path .=  " -i ".public_path($watermark['path']);
                $vid = 'vid'.$key;
                $wm = 'wm'.$key;
                $w_h = $watermark['h'];
                $w_w = $watermark['w'];
                $w_x = $watermark['x'];
                $w_y = $watermark['y'];
                $water = $key+1;
                if ($watermark['between'] == "yes"){
                    $b_from = $watermark['between_from'];
                    $b_to = $watermark['between_to'];
                    $between = ":enable='between(t,$b_from,$b_to)'";
                }else{
                    $between = '';
                }
                if ($key == 0){
                    $in = 0;
                    $out = 'out'.$water;
                }else{
                    $in = 'out'.$key;
                    $out = 'out'.($water+1);
                }
                if ($key + 1 == $total_watermarks){
                    $terminator = '';
                }else{
                    $terminator = "[$out];";
                }
                $overlay .= "[$water][$in]scale2ref=w='iw*$w_w/100':h='ih*$w_h/100'[$wm][$vid];[$vid][$wm]overlay=$w_x:$w_y$between $terminator";
            }
            $output = public_path("output/".$out_name);
            $cmd = "ffmpeg -y -i $pre_vid $watermark_path -filter_complex \"$overlay\" $output 2> final.txt";
            exec($cmd);
            $size = array('width'=>$detail['resize']['width'],'height'=>$detail['resize']['height'],'fps'=>$detail['fps']);
            $res = array('format'=>'mp4','size'=>$size);
            return response()->json(['output'=>$res]);
        }
        //exec("ffmpeg -y -i $pre -i logo.jpg -filter_complex \"[1][0]scale2ref=w='iw*100/100':h='ih*100/100'[wm][vid];[vid][wm]overlay=0:0:enable='between(t,2,4)'\" full.mp4");

    }
}
