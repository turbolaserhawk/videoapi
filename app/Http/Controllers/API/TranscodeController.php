<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TranscodeController extends Controller
{
    public function index(Request $request){
        set_time_limit(0);
        foreach ($request->data as $detail){
            $input = public_path($detail['video']);
            //setting up cutting position
            if ($detail['allow_cut'] == "yes"){
                $cut_from = $detail['cut']['from'];
                $cut_to = $detail['cut']['to'];
                $cut = "-ss $cut_from -to $cut_to";
            }else{
                $cut = "";
            }
            //setting aspect ratio
            $aspect = $detail['output']['aspectRatio'];
            //setting resolution
            $width = $detail['output']['size']['width'];
            $height = $detail['output']['size']['height'];
            $scale = ",setdar=$aspect,scale=$width:$height";
            //crop setting
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
            //image watermarks/overlays setting
            $watermark_path = '';
            $watermark_overlay = '';
            $total_watermarks = count($detail['watermark']);
            foreach ($detail['watermark'] as $key => $watermark){
                $water = $key+1;
                if ($watermark['transition'] == "yes"){
                    $loop = " -loop 1 ";
                    $watermark_out = "watermark_out".$water;
                    //trim should be greater than fadeout time
                    $fade_in_start = $watermark['transition_fadeIn_start'];
                    $fade_in_duration =$watermark['transition_fadeIn_duration'];
                    $fade_out_start = $watermark['transition_fadeOut_start'];
                    $fade_out_duration = $watermark['transition_fadeOut_duration'];
                    $trim_start = $fade_in_start -2;
                    $trim_end = $fade_out_start + $fade_out_duration + 2;
                    $transition = "[$water]trim=$trim_start:$trim_end,fade=in:st=$fade_in_start:d=$fade_in_duration:alpha=1,fade=out:st=$fade_out_start:d=$fade_out_duration:alpha=1,setpts=PTS+0/TB[$watermark_out];";
                }else{
                    $loop = "";
                    $watermark_out = $water;
                    $transition = "";
                }
                $watermark_path .=  "$loop -i ".public_path($watermark['path']);
                $vid = 'vid'.$key;
                $wm = 'wm'.$key;
                $w_h = $watermark['height'];
                $w_w = $watermark['width'];
                $w_x = $watermark['offset']['x'];
                $w_y = $watermark['offset']['y'];
                //position setting
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
                //enable/between setting
                if ($watermark['between'] == "yes"){
                    $b_from = $watermark['between_from'];
                    $b_to = $watermark['between_to'];
                    $between = ":enable='between(t,$b_from,$b_to)'";
                }else{
                    $between = '';
                }
                //process setting
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
                //preparing command
                $watermark_overlay .= "$transition [$watermark_out][$in]scale2ref=w='iw*$w_w/100':h='ih*$w_h/100'[$wm][$vid];[$vid][$wm]overlay=$position$between $terminator";
            }
            //text overlay setting
            $text_overlay = "";
            if ($detail['text_overlay'] == "yes"){
                foreach ($detail['text'] as $key => $overlay){
                    //basic setting
                    $font_file =  public_path($overlay['font_file']);
                    $font_size = $overlay['font_size'];
                    $font_color = $overlay['font_color'];
                    $draw_text = $overlay['draw_text'];
                    $overlay_position = $overlay['position'];
                    $w_x = $overlay['offset']['x'];
                    $w_y = $overlay['offset']['y'];
                    //position setting
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
                    //enable/between setting
                    if ($overlay['between'] == "yes"){
                        $b_from = $overlay['between_from'];
                        $b_to = $overlay['between_to'];
                        $between = ":enable='between(t,$b_from,$b_to)'";
                    }else{
                        $between = "";
                    }
                    //preparing command
                    $text_overlay .= ",drawtext=fontsize=$font_size:fontcolor=$font_color:fontfile=$font_file:text=$draw_text:$position $between";
                }
            }
            //output setting
            $format = $detail['output']['format'];
            $output = public_path("output/".time().".".$format);
            $bitrate = $detail['bitrate']."k";
            $fps = $detail['output']['fps'];
            //final command to execute
            $cmd = "ffmpeg -y -r $fps -i $input $cut $watermark_path -filter_complex \"$crop_scale$watermark_overlay$text_overlay\" -b $bitrate -preset ultrafast -crf 23 $output 2> final.txt";
            exec($cmd);
            $data = array('user_id'=>Auth::id(),'width'=>$width,'height'=>$height,'fps'=>$fps,'format'=>$format,'path'=>$output);
            Clip::create($data);

        }
        return response()->json(['output'=>'Transcoded successfully']);

    }
}
