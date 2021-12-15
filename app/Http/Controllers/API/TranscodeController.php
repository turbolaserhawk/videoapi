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
            $width = $detail['output']['size']['width'];
            $height = $detail['output']['size']['height'];
            $filter .= "scale=$width:$height";
            $filter .= '"';
            $pre_vid = public_path("pre/".$out_name);
            exec("$ffmpeg -y -i $input $cut $filter $pre_vid 2> log.txt");
            //watermarks
            $watermark_path = '';
            $overlay = '';
            $total_watermarks = count($detail['iw']);
            foreach ($detail['iw'] as $key => $watermark){
                $watermark_path .=  " -i ".public_path($watermark['path']);
                $vid = 'vid'.$key;
                $wm = 'wm'.$key;
                $w_h = $watermark['height'];
                $w_w = $watermark['width'];
                $w_x = $watermark['offset']['x'];
                $w_y = $watermark['offset']['y'];
                if ($watermark['position'] == "top_left"){
                    $position = $this->position_top_left($w_x,$w_y);
                }elseif ($watermark['position'] == "top_right"){
                    $position = $this->position_top_right($w_x,$w_y);
                }elseif ($watermark['position'] == "bottom_left"){
                    $position = $this->position_bottom_left($w_x,$w_y);
                }elseif ($watermark['position'] == "bottom_right"){
                    $position = $this->position_bottom_right($w_x,$w_y);
                }elseif ($watermark['position'] == "center"){
                    $position = $this->position_center($w_x,$w_y);
                }else{
                    $position = "$w_x:$w_y";
                }
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
                $overlay .= "[$water][$in]scale2ref=w='iw*$w_w/100':h='ih*$w_h/100'[$wm][$vid];[$vid][$wm]overlay=$position$between $terminator";
            }
            $format = $detail['output']['format'];
            $output = public_path("output/".time().".".$format);
            $bitrate = $detail['bitrate']."k";
            $fps = $detail['output']['fps'];
            $cmd = "ffmpeg -y -r $fps -i $pre_vid $watermark_path -filter_complex \"$overlay\" -b $bitrate -bufsize 4000k $output 2> final.txt";
            exec($cmd);
        }
        return response()->json(['output'=>'Transcoded successfully']);
        //exec("ffmpeg -y -i $pre -i logo.jpg -filter_complex \"[1][0]scale2ref=w='iw*100/100':h='ih*100/100'[wm][vid];[vid][wm]overlay=0:0:enable='between(t,2,4)'\" full.mp4");
        //$size = array('width'=>$width,'height'=>$height,'fps'=>$fps);
        //$res = array('format'=>$format,'size'=>$size);

    }
}
