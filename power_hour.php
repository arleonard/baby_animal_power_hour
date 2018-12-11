<?php

require_once(__DIR__ . '/phpmailer/PHPMailerAutoload.php');

// settings
$file_owner       = null;
$email_from_name  = null;
$email_from_email = null;
$smtp_host        = null;
$smtp_port        = null;
foreach (readFileToArray(__DIR__ . '/config.txt') as $setting) {
    $comma = strpos($setting, ',');
    $key = substr($setting, 0, $comma);
    $$key = substr($setting, $comma+1);
}
if (!$file_owner || !$email_from_name || !$email_from_email || !$smtp_host || !$smtp_port) {
    die();
}

// init
$animal_file  = __DIR__ . '/animal.txt';
$top_pics_dir = __DIR__ . '/pics/';

// grab animal and theme
list($animal, $theme) = readFileToArray($animal_file);

// grab email list
$email_list = readFileToArray(__DIR__ . "/emails/$animal.txt");

// grab pic
$source  = $top_pics_dir . $animal . '/' . $theme . '/';
$pics = scandir($source);
$pic  = null;
while (!is_file($pic) && count($pics)>0) {
    $pic = $source . array_pop($pics);
}
$pic_number = round(date('i')/5, 0)+1;

// send pic out
if (is_file($pic)) {
    $shrunk_pic = "{$source}$pic_number.jpg";
    $string = "convert $pic -resize 800x600\> $shrunk_pic";
    exec("convert \"$pic\" -quality 50 -resize 800x600\> $shrunk_pic");
    $destination = $top_pics_dir . $animal . '/.finished/' . $theme . '/';
    mkdir($destination, 0777, true);
    exec("chown $file_owner $destination");
    copy($pic, $destination."$pic_number.jpg");
    exec("chown $file_owner {$destination}{$pic_number}.jpg");
    unlink($pic);
    try {
        $cid = "kitten_$pic_number" . "_" . time();
        $mail = new \PHPMailer();
        $mail->IsSMTP();
        $mail->CharSet   = 'UTF-8';
        $mail->Host      = $smtp_host;
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth  = false;
        $mail->Port      = $smtp_port;
        foreach ($email_list as $to) {
            $to = parseEmailAddress($to);
            $mail->addAddress($to['address'], $to['name']);
        }
        $mail->setFrom($email_from_email, $email_from_name);
        $mail->isHTML(true);
        $mail->Subject = ucfirst($animal) . " Power Hour #$pic_number!";
        $mail->Body = "<p>Here's your $animal number $pic_number of the week!</p><p>This week's theme is: <b>$theme</b>.</p><p><img src='cid:$cid'></p>";
        $mail->AddEmbeddedImage($shrunk_pic, $cid);
        $mail->send();
    } catch (\phpmailerException $e) {

    }
    unlink($shrunk_pic);
}

// if it's the last picture of the power hour...
if ($pic_number==12) {
    //archive old pics
    $destination = $top_pics_dir . $animal . '/.unsorted/' . $theme . '/';
    mkdir($destination, 0777, true);
    exec("chown $file_owner $destination");
    $files = array_diff(scandir($source), array(".",".."));
    $delete = array();
    foreach ($files as $file) {
        if (copy($source.$file, $destination.$file)) {
            exec("chown $file_owner {$destination}{$file}");
            $delete[] = $source.$file;
        }
    }
    foreach ($delete as $file) {
        unlink($file);
    }
    rmdir($source);

    // pick new theme
    $animal = mt_rand(1,10)>7?"Puppy":"Kitten";
    $themes = array_values(array_diff(scandir($top_pics_dir . $animal), array(".","..",".finished",".unsorted")));
    $theme = $themes[mt_rand(0,count($themes)-1)];

    //save new theme
    $handle = fopen($animal_file, "w");
    fwrite($handle, $animal . "\n");
    fwrite($handle, $theme . "\n");
    fclose($handle);
}


function parseEmailAddress($address)
{
    preg_match('/(.*)<(.*)>/', $address, $matches);
    if ($matches) {
        return array('address'=>trim($matches[2]), 'name'=>trim($matches[1]));
    } else {
        return array('address'=>trim($address), 'name' => null);
    }
}

function readFileToArray($file_path)
{
    $lines_array = file($file_path);
    array_walk($lines_array, function(&$item){$item=str_replace("\n","",$item);});
    return $lines_array;
}
