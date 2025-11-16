<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
require __DIR__ . '/../../vendor/autoload.php';
use Mike42\Escpos\ImagickEscposImage;#Butuh Ekstensi Imagick
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\RawbtPrintConnector;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

date_default_timezone_set("Asia/Jakarta");
require __DIR__ . '/../../helper/Tanggal_helper.php';
require __DIR__ . '/../../helper/Uang_helper.php';
require __DIR__ . '/../../config.php';


$json = $_POST['json'];
$data = json_decode($json);

#----------------------------------IMAGE SETTING FIRST-------------------------------------#
$logo_image = 'default.png';
$urlArray = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $urlArray);
$numSegments = count($segments); 
$environment = $segments[$numSegments - 3];
if($environment == 'windows')
{
    $image_directory = $data->print_setting->windows_images_directory;
} else if($environment == 'android'){
    $image_directory = $data->print_setting->android_images_directory;
}
#----------------------------------IMAGE SETTING FIRST-------------------------------------#



if(count($data->printers) > 0){

    foreach($data->printers as $printer){
    #----------Setting Paper-----------#
    if($printer->printer_type == '58' || ($printer->printer_paper_size == '80mm' && $printer->printer_type == '80'))
    {
        $max_width = 48; 
        if($printer->printer_paper_size == '58mm'){
            $max_width = 32;
        } 
        $center = 'On';
        $right = 'On';
    }
    else{
        $max_width = 32;
        $center = 'Off';
        $right = 'Off';
    }
    #----------Setting Paper-----------#

        $connector = ($printer->printer_conn == 'USB') ? new WindowsPrintConnector($printer->printer_address) : new NetworkPrintConnector($printer->printer_address) ;
        if($connector){ #If Connector
            $print = new Printer($connector);#Open Koneksi Printer
            if(count($printer->jobs) > 0){

                foreach($printer->jobs as $job){
                    
                    #----------------------------------RECEIPT-------------------------------------#
                    if($job->job == 'Receipt'){
                        

                        

                        if($center == 'On')
                        {
                            $print -> setJustification(Printer::JUSTIFY_CENTER);
                        }
                        $logo = EscposImage::load($image_directory.'/default.png');
                        $print->bitImage($logo);
                        $print->selectPrintMode(Printer::MODE_FONT_A);
                        $print->setEmphasis(true);
                        $print->text($data->store->header_bill."\n");
                        $print->text($data->store->address."\n");
                        $print->text($data->store->city."\n");
                        $print->text($data->store->phone."\n");

                        $print->selectPrintMode(Printer::MODE_FONT_A | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                        $print->feed(1);
                        $print -> setTextSize(2, 2);
                        $print -> text("NOTA BAYAR PIUTANG\n");
                        $print -> setTextSize(1, 1);
                        $print -> text(str_repeat('=',$max_width)."\n");
                        $print->setEmphasis(true);//berguna mempertebal huruf
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $customer_name = ($data->receipt->customer->is_default == true) ? $data->receipt->customer_alias : $data->receipt->customer->name;
                        $print->text("UID      : ".substr($data->receipt->payment_uid,0,$max_width - 11)."\n");
                        $print->text("Pelanggan: ".substr($customer_name,0,$max_width - 11)."\n");
                        $print->text("Tanggal  : ".substr($data->receipt->date,0,$max_width - 11)."\n");
                        $print->text("Kasir    : ".substr($data->receipt->cashier->name,0,$max_width - 11)."\n");
                        $print->text("Sales    : ".substr($data->receipt->sales_name,0,$max_width - 11)."\n");

                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        
                        
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $print -> text(str_repeat('=', $max_width)."\n");

                        $paid = (int) $data->receipt->paid;
                        $paid = ($paid > 0) ? '-'.uang($paid) :'-';
                        $paid_length = strlen($paid);
                        $receivable_before = (int)$data->receivable_before;
                        $receivable_before_length = strlen(uang($receivable_before));
                        $receivable_final = (int) ($receivable_before - (int) $data->receipt->paid);
                        $receivable_final_length = strlen(uang($receivable_final));

                        $space_before = 28;
                        if($printer->printer_paper_size == "58mm") {  $space_before = 12; }

                        if($receivable_before > 0){
                            $print -> text("PIUTANG SEBELUMNYA".str_repeat(' ',1).':'.str_repeat(' ',$space_before - strlen(uang($receivable_before))).uang($receivable_before)."\n");
                            }
                            $print -> text("BAYAR".str_repeat(' ',14).':'.str_repeat(' ',$space_before  - strlen($paid)).$paid."\n");
                            if($receivable_final > 0){
                            $print -> text("PIUTANG KESELURUHAN:".str_repeat(' ',$space_before - strlen(uang($receivable_final))).uang($receivable_final)."\n");
                            }
                        
                        $print -> text(str_repeat('=', $max_width)."\n");

                        if($center == 'On')
                        {
                            $print -> setJustification(Printer::JUSTIFY_CENTER);
                        }
                        $print -> text("TERIMA KASIH \n");
                        $mada_footer = EscposImage::load($image_directory.'/'.$data->app_logo);
                        $print->bitImage($mada_footer);
                        if($printer->printer_footer_space > 0){$print -> feed($printer->printer_footer_space); }
                        $print->cut();#Memotong kertas
                        
                        
                    }
                    #----------------------------------END RECEIPT-------------------------------------#

                    
                }#End Foreach Jobs

            }#End Count Jobs
            $print->close();#Close Koneksi Printer
        }#End If Connector
    }#End Foreach Printers

}#End Count Printers
?>