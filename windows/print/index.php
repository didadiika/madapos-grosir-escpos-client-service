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
                    for($i = 0; $i < $job->autoprint_quantity; $i++){ #Foreach Autoprint Quantity
                    #----------------------------------RECEIPT-------------------------------------#
                    if($job->job == 'Receipt' && $data->waiting->receipt == true){
                        
                        if($data->waiting->push_drawer == true && ($printer->printer_cash_drawer == 1 || $printer->printer_cash_drawer == true)){
                            $print->pulse(0, 100, 100);
                        }
                        

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
                        $print -> text("NOTA PENJUALAN\n");
                        $print -> setTextSize(1, 1);
                        $print -> text(str_repeat('=',$max_width)."\n");
                        $print->setEmphasis(true);//berguna mempertebal huruf
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $customer_name = ($data->receipt->customer->is_default == true) ? $data->receipt->customer_alias : $data->receipt->customer->name;
                        $print->text("UID      : ".substr($data->receipt->sale_uid,0,$max_width - 11)."\n");
                        $print->text("Pelanggan: ".substr($customer_name,0,$max_width - 11)."\n");
                        $print->text("Tanggal  : ".substr($data->receipt->date,0,$max_width - 11)."\n");
                        $print->text("Kasir    : ".substr($data->receipt->cashier->name,0,$max_width - 11)."\n");

                        $max_qty = 4;
                        $space_between_qty_unit = 1;
                        $space_between_unit_item = 1;
                        $max_unit = 5;
                        $max_item = $max_width - $max_qty - $max_unit - $space_between_qty_unit - $space_between_unit_item;
                        $max_price = 10;
                        $max_sub_total = 10;
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        
                        $print -> text(str_repeat('-', $max_width)."\n");
                        if($printer->printer_paper_size == "80mm"){
                            $print -> text(" Qty".str_repeat(' ',$space_between_qty_unit)."Unit".str_repeat(' ',$space_between_unit_item)."Item".str_repeat(' ',8)."Price".str_repeat(' ',11)."Sub Total\n");
                        } else if($printer->printer_paper_size == "58mm"){
                            $print -> text(" Qty".str_repeat(' ',$space_between_qty_unit)."Unit".str_repeat(' ',$space_between_unit_item)."Item".str_repeat(' ',1)."Price".str_repeat(' ',2)."Sub Total\n");
                        }
                        $print -> text(str_repeat('-', $max_width)."\n");

                        #CARTS
                        $no = 0;
                        foreach($data->carts as $cart){
                            $no++;
                                $item = substr(ucwords(strtolower($cart->product->name)),0,$max_width - $max_qty - $space_between_qty_unit);#12
                                $qty = $cart->qty;#1
                                $unit = ($cart->product->unit) ? substr($cart->product->unit,0,4): '';#1
                                $note = $cart->note;
                                $price = uang((int) $cart->price_final);#6
                                $sub_total = uang($cart->qty * (int)$cart->price_final);#6
                                $space_before_note= 9;
                                $space_before_price = 19;
                                $space_between_price_sub_total = 9;
                                if($printer->printer_paper_size == "58mm") {  $space_before_price = 5; $space_between_price_sub_total = 1;}
                                
                                $print -> setJustification(Printer::JUSTIFY_LEFT);
                                $print -> text(str_repeat(' ',$max_qty - strlen($qty)).$qty.str_repeat(' ',$max_unit - strlen($unit)).$unit.str_repeat(' ',$space_between_unit_item).$item."\n");
                                if($note){
                                    $print -> text(str_repeat(' ',$space_before_note)."*".$note."\n");
                                }
                                $print -> text(str_repeat(' ',$space_before_price).str_repeat(' ',$max_price - strlen($price)).$price.str_repeat(' ',$space_between_price_sub_total).str_repeat(' ',$max_sub_total - strlen($sub_total)).$sub_total."\n");
                        }
                        $total = $data->receipt->total_before_disc;
                        $total_length = strlen(uang($total));
                        if($data->receipt->disc_number > 0){
                            $disc = "-".uang((int)$data->receipt->disc_number);
                            $grand_total = $total - (int)$data->receipt->disc_number;
                        } else if($data->receipt->disc_percent > 0){
                            $disc = "-".$data->receipt->disc_percent."%";
                            $grand_total = $total - ($total * (int)$data->receipt->disc_percent/100);
                        } else {
                            $disc = "-";
                            $grand_total = $total;
                        }
                        $discb_length = strlen($disc);
                        $grand_length = strlen(uang($grand_total));
                        $paid = (int) $data->paid;
                        $paid = ($paid > 0) ? '-'.uang($paid) :'-';
                        $paid_length = strlen($paid);
                        $receivable_before = (int)$data->receivable_before;
                        $receivable_before_length = strlen(uang($receivable_before));
                        $receivable_final = (int) ($grand_total - (int) $data->paid + $receivable_before);
                        $receivable_final_length = strlen(uang($receivable_final));

                        $print -> text(str_repeat('-', $max_width)."\n");
                        $print -> setJustification(Printer::JUSTIFY_LEFT);

                        $space_before = 28;
                        if($printer->printer_paper_size == "58mm") {  $space_before = 12; }
                        
                        $print -> text("DISC".str_repeat(' ',15).':'.str_repeat(' ',$space_before - strlen($disc)).$disc."\n");
                        $print -> text("TOTAL".str_repeat(' ',14).':'.str_repeat(' ',$space_before - strlen(uang($grand_total))).uang($grand_total)."\n");
                        if($receivable_before > 0){
                        $print -> text("PIUTANG SEBELUMNYA".str_repeat(' ',1).':'.str_repeat(' ',$space_before - strlen(uang($receivable_before))).uang($receivable_before)."\n");
                        }
                        $print -> text("BAYAR".str_repeat(' ',14).':'.str_repeat(' ',$space_before  - strlen($paid)).$paid."\n");
                        if($receivable_final > 0){
                        $print -> text("PIUTANG KESELURUHAN:".str_repeat(' ',$space_before - strlen(uang($receivable_final))).uang($receivable_final)."\n");
                        }
                        
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $print -> text(str_repeat('=', $max_width)."\n");
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $print->text($data->print_setting->printer_cashier_footer_info."\n");
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

                    #----------------------------------DELIVERY NOTE-------------------------------------#
                    else if($job->job == 'Delivery Note' && $data->waiting->delivery_note == true){
                        
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
                        $print -> text("SURAT JALAN\n");
                        $print -> setTextSize(1, 1);
                        $print -> text(str_repeat('=',$max_width)."\n");
                        $print->setEmphasis(true);//berguna mempertebal huruf
                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                        $customer_name = ($data->receipt->customer->is_default == true) ? $data->receipt->customer_alias : $data->receipt->customer->name;
                        $print->text("UID      : ".substr($data->receipt->sale_uid,0,$max_width - 11)."\n");
                        $print->text("Pelanggan: ".substr($customer_name,0,$max_width - 11)."\n");
                        $print->text("Tanggal  : ".substr($data->receipt->date,0,$max_width - 11)."\n");
                        $print->text("Kasir    : ".substr($data->receipt->cashier->name,0,$max_width - 11)."\n");

                        $max_qty = 4;
                        $max_unit = 5;
                        $space_between_qty_unit = 1;
                        $space_between_unit_item = 1;
                        $space_before_note = 9;
                        $max_item = $max_width - $max_qty - $max_unit - $space_between_qty_item;
                        $print -> setJustification(Printer::JUSTIFY_LEFT);

                        $print -> text(str_repeat('-', $max_width)."\n");
                        $print -> text(" Qty".str_repeat(' ',$space_between_qty_unit)."Unit".str_repeat(' ',$space_between_unit_item)."Item\n");
                        $print -> text(str_repeat('-', $max_width)."\n");

                        #CARTS
                        $q = array();
                        $no = 0;
                        foreach($data->carts as $cart){
                            $no++;
                                $item = substr(ucwords(strtolower($cart->product->name)),0,$max_width - $max_qty - $space_between_qty_item);#12
                                $qty = $cart->qty;#1
                                $unit = ($cart->product->unit) ? substr($cart->product->unit,0,4): '';#1
                                $note = $cart->note;
                                
                                $print -> setJustification(Printer::JUSTIFY_LEFT);
                                $print -> text(str_repeat(' ',$max_qty - strlen($qty)).$qty.str_repeat(' ',$max_unit - strlen($unit)).$unit.str_repeat(' ',$space_between_unit_item).$item."\n");
                                if($note){
                                    $print -> text(str_repeat(' ',$space_before_note)."*".$note."\n");
                                }

                                
                        }
                        
                        $print -> text(str_repeat('-', $max_width)."\n");
                        $print -> text($no." ITEM(S)\n");
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
                    #----------------------------------END DELVERY NOTE-------------------------------------#

                    #----------------------------------ORDER-------------------------------------#
                    else if($job->job == 'Order' && $data->waiting->order == true){
                        
                        if(count($printer->order_per_category) > 0){#If Count Categories
                            foreach($printer->order_per_category as $category){#Foreach Category
                                if(count($category->orders) > 0){ #If Count Order
                                    $print -> getPrintConnector() -> write(PRINTER::ESC . "B" . chr(4) . chr(1));
                                    
                                    $print->selectPrintMode(Printer::MODE_FONT_A);
                                    $print -> setJustification(Printer::JUSTIFY_LEFT);
                                    $print -> text("#".$category->category_name."\n");

                                    if($center == 'On')
                                    {
                                    $print -> setJustification(Printer::JUSTIFY_CENTER);
                                    }
                                    
                                    $print->text($data->store->header_bill."\n");
                                    $print->setEmphasis(true);
                                    if($center == 'On')
                                    {
                                    $print -> setJustification(Printer::JUSTIFY_CENTER);
                                    }
                                    $print -> text(str_repeat('=',$max_width)."\n");
                                    $print -> setJustification(Printer::JUSTIFY_LEFT);
                                    $print->text("Tanggal : ".$data->receipt->date."\n");
                                    $print -> setJustification(Printer::JUSTIFY_CENTER);
                                    $print -> setTextSize(2, 1);
                                    $customer_name = ($data->receipt->customer->is_default == true) ? $data->receipt->customer_alias : $data->receipt->customer->name;
                                    $print->text(strtoupper($customer_name)."\n");
                                    $print -> setTextSize(1, 1);
                                    #Judul
                                    $print -> text(str_repeat('-', $max_width)."\n");

                                    $no = 0;
                                    $spasi_max_qty = 4;
                                    $spasi_between_qty_items = 1;
                                    $print -> setJustification(Printer::JUSTIFY_LEFT);
                                    $print -> text(" Qty".str_repeat(' ',$spasi_between_qty_items)."Item"."\n");
                                    $print -> text(str_repeat('-', $max_width)."\n");
                                    
                                    foreach($category->orders as $order){
                                    $no++;
                                        $product_name = ucwords(strtolower($order->product_name));#12
                                        $qty = $order->qty;#1
                                        $note = $order->note;#6
                                        
                                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                                        $print -> text(str_repeat(' ',$spasi_max_qty - strlen($qty)).$qty.str_repeat(' ',$spasi_between_qty_items).$product_name."\n");
                                        $print -> setJustification(Printer::JUSTIFY_LEFT);
                                        if($note){
                                            $print -> text("      *".$note."\n");
                                        }
                                    }
                                    $print -> text(str_repeat('-', $max_width)."\n");
                                    $print -> setJustification(Printer::JUSTIFY_LEFT);
                                    $print -> text($no." ITEM(S)\n");
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
                                    

                                }#End If Count Order
                            
                            }#End Foreach Category
                        }#End If Count Categories
                    }
                    #----------------------------------END ORDER-------------------------------------#
                    }#EndForeach Autoprint Quantity
                }#End Foreach Jobs

            }#End Count Jobs
            $print->close();#Close Koneksi Printer
        }#End If Connector
    }#End Foreach Printers

}#End Count Printers
?>