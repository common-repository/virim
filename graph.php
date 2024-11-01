<?
error_reporting(E_ERROR);

$debugging = false;


$imgWidth=800;
$imgHeight=150;


$x=0;

if($_GET['type']=='over_time') {

	include_once('../../../wp-config.php');
	include_once('../../../wp-load.php');
	include_once('../../../wp-includes/wp-db.php');

	$table_name = $wpdb->prefix . "virim_post_stats";
	
	$myrows = $wpdb->get_results("select * from $table_name order by dt");
	foreach($myrows as $line) {

		$post_count++;
		
		$over_time[$post_count]['fb_shares'] = $line->fb_shares;
		$over_time[$post_count]['comments'] = $line->comments;
		$over_time[$post_count]['tweets'] = $line->tweets;	
		
		$totals['fb_shares'][] = $line->fb_shares;
		$totals['comments'][] = $line->comments;
		$totals['tweets'][] = $line->tweets;	
					
	}
	
	foreach($totals as $metric=>$vals) {
		$avgs[$metric] = array_sum($vals)/count($vals);	
	}
	
	foreach($over_time as $id=>$vals) {
		foreach($vals as $n=>$v) {
			$data[$n][] = round( ( ($v-$avgs[$n]) /$avgs[$n]) *100);
		}
	}
	$line_values  = $data['fb_shares'];	
	$t_line_values = $data['tweets'];	
	$c_line_values = $data['comments'];	
}
else {

	$line_values = unserialize($_GET['s_values']);
	$t_line_values = unserialize($_GET['t_values']);
	$c_line_values = unserialize($_GET['c_values']);
}



if($debugging) {
	echo "<pre> here";
	print_r($line_values);
	print_r($t_line_values);
	print_r($c_line_values);
}

//print_r($line_values);
$values = array();

if($_GET['type']=='days') {
	$line_start = 0;
	$line_end = 6;
} 
elseif($_GET['type']=='hours') {
	$line_start = 0;
    $line_end = 23;	
}
elseif($_GET['type']=='over_time') {
	$line_start = 1;
    $line_end = count($line_values);	
}

$max = max($line_values);
$min = min($line_values);

if($max<max($t_line_values)) { $max = max($t_line_values); }
if($min>min($t_line_values)) { $min = min($t_line_values); }

if($max<max($c_line_values)) { $max = max($c_line_values); }
if($min>min($c_line_values)) { $min = min($c_line_values); }

$max = $max+10;


if($min<0) {
    $v_distance = $max+abs($min);
}
elseif($min>0) {
    $v_distance = $max-$min;
}

for($x=$line_start; $x<=$line_end; $x++) {
    if($line_values[$x]) { $values[$x] = $line_values[$x] + abs($min);    }
    else { $values[$x] = abs($min);  }
    
    if($t_line_values[$x]) { $t_values[$x] = $t_line_values[$x] + abs($min); }
    else { $t_values[$x] = abs($min); }      


    if($c_line_values[$x]) { $c_values[$x] = $c_line_values[$x] + abs($min); }
    else { $c_values[$x] = abs($min); }      

	
}

if($debugging) {
	echo "max: $max min: $min <br/>";
	print_r($t_values);
	print_r($values);
}


$line_values = $values;
$t_line_values = $t_values;
$c_line_values = $c_values;


$count = count($values)-1;

//print_r($values);
$h_step = $imgWidth/$count;

$v_step = $imgHeight/($v_distance+5);



$image=imagecreatetruecolor($imgWidth+30, $imgHeight+30);
imageantialias($image, true); 




$colorWhite=imagecolorallocate($image, 255, 255, 255);

$colorGrey=imagecolorallocate($image, 92, 92, 92);

$colorBlue = imagecolorallocate($image, 53, 204, 255);
$colorRed = imagecolorallocate($image, 59, 89, 152);
$colorGreen = imagecolorallocate($image, 216, 48, 25);

imagefill ( $image, 0, 0, $colorWhite );


$y_zero = ($imgHeight-($v_step*abs($min)));


$x=0;

$day_labels[0] = 'Sun';
$day_labels[1] = 'Mon';
$day_labels[2] = 'Tue';
$day_labels[3] = 'Wed';
$day_labels[4] = 'Thu';
$day_labels[5] = 'Fri';
$day_labels[6] = 'Sat';


foreach($line_values as $label=>$value) {
    if($_GET['type']=='days') {
		
        imagestring($image, 2, ($x*$h_step), $imgHeight+10, $day_labels[$label], $colorGrey);
    }
    elseif($_GET['type']=='hours') {
        if($label==0) {
            $label = "12AM";
        }
        elseif($label<12) {
            $label = $label."AM";
        }        
        elseif($label==12) {
            $label = "12PM";
        }
        elseif($label>=12) {
            $label = ($label-12)."PM";
        }
        
        imagestring($image, 2, ($x*$h_step), $imgHeight+10, $label, $colorGrey);
    }    
    //imagestring($image, 2, ($x*$h_step), $imgHeight+10, $label, $colorGrey);
    $x++;
}





$i=0;
for ($i=0; $i<$count; $i++){
    $c_seg_color = $colorGreen;
    
    if( ($c_values[$i]!=abs($min)) or ($c_values[$i+1]!=abs($min)) ) {
        
        imageline($image, $i*$h_step, ($imgHeight-($v_step*$c_values[$i])), ($i+1)*$h_step, ($imgHeight-($v_step*$c_values[$i+1])), $c_seg_color);
        imageline($image, ($i*$h_step)-1, (($imgHeight-($v_step*$c_values[$i]))), (($i+1)*$h_step)-1, (($imgHeight-($v_step*$c_values[$i+1]))), $c_seg_color);
        imageline($image, ($i*$h_step)+1, (($imgHeight-($v_step*$c_values[$i]))), (($i+1)*$h_step)+1, (($imgHeight-($v_step*$c_values[$i+1]))), $c_seg_color);    
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$c_values[$i])))-1, (($i+1)*$h_step), (($imgHeight-($v_step*$c_values[$i+1])))-1, $c_seg_color);
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$c_values[$i])))+1, (($i+1)*$h_step), (($imgHeight-($v_step*$c_values[$i+1])))+1, $c_seg_color);
    }
}


$i=0;
for ($i=0; $i<$count; $i++){
//imageline($image, $i*$h_step, ($imgHeight-($v_step*$downValues[$i])), ($i+1)*$h_step, ($imgHeight-($v_step*$downValues[$i+1])), $colorRed);

    $seg_color = $colorRed;
    
    if( ($values[$i]!=abs($min)) or ($values[$i+1]!=abs($min)) ) {
        imageline($image, $i*$h_step, ($imgHeight-($v_step*$values[$i])), ($i+1)*$h_step, ($imgHeight-($v_step*$values[$i+1])), $seg_color);
        imageline($image, ($i*$h_step)-1, (($imgHeight-($v_step*$values[$i]))), (($i+1)*$h_step)-1, (($imgHeight-($v_step*$values[$i+1]))), $seg_color);
        imageline($image, ($i*$h_step)+1, (($imgHeight-($v_step*$values[$i]))), (($i+1)*$h_step)+1, (($imgHeight-($v_step*$values[$i+1]))), $seg_color);    
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$values[$i])))-1, (($i+1)*$h_step), (($imgHeight-($v_step*$values[$i+1])))-1, $seg_color);
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$values[$i])))+1, (($i+1)*$h_step), (($imgHeight-($v_step*$values[$i+1])))+1, $seg_color);    
    }
    
}
$i=0;
for ($i=0; $i<$count; $i++){
    $t_seg_color = $colorBlue;
    
    if( ($t_values[$i]!=abs($min)) or ($t_values[$i+1]!=abs($min)) ) {
        
        imageline($image, $i*$h_step, ($imgHeight-($v_step*$t_values[$i])), ($i+1)*$h_step, ($imgHeight-($v_step*$t_values[$i+1])), $t_seg_color);
        imageline($image, ($i*$h_step)-1, (($imgHeight-($v_step*$t_values[$i]))), (($i+1)*$h_step)-1, (($imgHeight-($v_step*$t_values[$i+1]))), $t_seg_color);
        imageline($image, ($i*$h_step)+1, (($imgHeight-($v_step*$t_values[$i]))), (($i+1)*$h_step)+1, (($imgHeight-($v_step*$t_values[$i+1]))), $t_seg_color);    
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$t_values[$i])))-1, (($i+1)*$h_step), (($imgHeight-($v_step*$t_values[$i+1])))-1, $t_seg_color);
        imageline($image, ($i*$h_step), (($imgHeight-($v_step*$t_values[$i])))+1, (($i+1)*$h_step), (($imgHeight-($v_step*$t_values[$i+1])))+1, $t_seg_color);
    }
}

imageline($image, 0, $y_zero , $imgWidth, $y_zero, $colorGrey);

imagestring($image, 2,  3 , $y_zero-15, 'Average', $colorGrey);


if(!$debugging) {
	header("Content-type: image/png");
	imagepng($image);
	imagedestroy($image);
}
//*/

 
?>