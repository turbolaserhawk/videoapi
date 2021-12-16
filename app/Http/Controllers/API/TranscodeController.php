<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TranscodeController extends Controller
{
    public function index(Request $request){
        set_time_limit(0);
        foreach ($request->data as $detail){
            $input = public_path($detail['video']);
            if ($detail['allow_cut'] == "yes"){
                $cut_from = $detail['cut']['from'];
                $cut_to = $detail['cut']['to'];
                $cut = "-ss $cut_from -to $cut_to";
            }else{ $cut = ""; }
            $width = $detail['output']['size']['width'];
            $height = $detail['output']['size']['height'];
            $scale = ",scale=$width:$height";
            if ($detail['allow_crop'] == "yes"){
                $left_right = $detail['crop']['left_right'];
                $top_bottom = $detail['crop']['top_bottom'];
                $crop_scale = "[0]crop=in_w-2*$left_right:in_h-2*$top_bottom$scale [c];";
            }else{ $crop_scale = ""; }
            /*if ($detail['rotate'] == 90){
                $filter .= "transpose=1,";
            }elseif ($detail['rotate'] == 180){
                $filter .= "transpose=2,";
            }elseif ($detail['rotate'] == 270){
                $filter .= "transpose=3,";
            }*/
            //watermarks
            $watermark_path = '';
            $watermark_overlay = '';
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
                    if ($detail['allow_crop'] == "yes"){
                        $in = "c";
                    }else{
                        $in = 0;
                    }
                    $out = 'out'.$water;
                }else{
                    $in = 'out'.$key;
                    $out = 'out'.($key+1);
                }
                if ($key + 1 == $total_watermarks){
                    $terminator = '';
                }else{
                    $terminator = "[$out];";
                }
                $watermark_overlay .= "[$water][$in]scale2ref=w='iw*$w_w/100':h='ih*$w_h/100'[$wm][$vid];[$vid][$wm]overlay=$position$between $terminator";
            }
            $text_overlay = "";
            if ($detail['text_overlay'] == "yes"){
                foreach ($detail['to'] as $key => $overlay){
                    $font_file =  public_path($overlay['font_file']);
                    $font_size = $overlay['font_size'];
                    $font_color = $overlay['font_color'];
                    $draw_text = $overlay['draw_text'];
                    $overlay_position = $overlay['position'];
                    $w_x = $overlay['offset']['x'];
                    $w_y = $overlay['offset']['y'];
                    if ($overlay_position == "top_left"){
                        $position = $this->text_position_top_left($w_x,$w_y);
                    }elseif ($overlay_position == "top_right"){
                        $position = $this->text_position_top_right($w_x,$w_y);
                    }elseif ($overlay_position == "bottom_left"){
                        $position = $this->text_position_bottom_left($w_x,$w_y);
                    }elseif ($overlay_position == "bottom_right"){
                        $position = $this->text_position_bottom_right($w_x,$w_y);
                    }elseif ($overlay_position == "center"){
                        $position = $this->text_position_center($w_x,$w_y);
                    }else{
                        $position = "$w_x:$w_y";
                    }
                    if ($overlay['between'] == "yes"){
                        $b_from = $overlay['between_from'];
                        $b_to = $overlay['between_to'];
                        $between = ":enable='between(t,$b_from,$b_to)'";
                    }else{
                        $between = "";
                    }
                    $text_overlay .= ",drawtext=fontsize=$font_size:fontcolor=$font_color:fontfile=$font_file:text=$draw_text:$position $between";
                }
            }
            $format = $detail['output']['format'];
            $output = public_path("output/".time().".".$format);
            $bitrate = $detail['bitrate']."k";
            $fps = $detail['output']['fps'];
            $cmd = "ffmpeg -y -r $fps -i $input $cut $watermark_path -filter_complex \"$crop_scale$watermark_overlay$text_overlay\" -b $bitrate -crf 23 $output 2> final.txt";
            exec($cmd);
        }
        return response()->json(['output'=>'Transcoded successfully']);
        //exec("ffmpeg -y -i $pre -i logo.jpg -filter_complex \"[1][0]scale2ref=w='iw*100/100':h='ih*100/100'[wm][vid];[vid][wm]overlay=0:0:enable='between(t,2,4)'\" full.mp4");
        //$size = array('width'=>$width,'height'=>$height,'fps'=>$fps);
        //$res = array('format'=>$format,'size'=>$size);

    }
}
