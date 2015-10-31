<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dubai');


/*
function test_update_quickbooks($GatewayName='paytabs',$OrderNumber='')
{
    //CONFIG FILE FOR QUICKBOOKS API AND DATABASE
    include dirname(__FILE__).'/php-qb/quickbooks-php-master/docs/partner_platform/example_app_ipp_v3/config.php';

    
$order = Mage::getModel('sales/order')->load($OrderNumber, 'increment_id');

print_r($order);
}
*/


function update_quickbooks($GatewayName='paytabs',$OrderNumber='',$PaymentTotal=0,$Currency='', $PaymentOnly=true)
{
 include dirname(__FILE__).'/php-qb/quickbooks-php-master/docs/partner_platform/example_app_ipp_v3/config.php';
/* include '../php-qb/quickbooks-php-master/docs/partner_platform/example_app_ipp_v3/config.php';*/
date_default_timezone_set('Asia/Dubai');

$order = Mage::getModel('sales/order')->load($OrderNumber, 'increment_id');

$Invoice=0;
$estimate_id=0;
$qb_customer_id=0;
/*
echo $OrderNumber='test-'.rand();*/

echo "<br>";
/*  GETTING SHIPPING INFO*/
$shippingAddress    = $order->getShippingAddress();
$country_id         = $shippingAddress['country_id'];
$countryName        = Mage::getModel('directory/country')->load($country_id)->getName();
$customer_telephone = $shippingAddress['telephone'];
$shipping_city      = $shippingAddress['city'];
$shipping_region    = $shippingAddress['region'];
$shipping_street    = $shippingAddress['street'];
$shipping_country   = $countryName;
$shipping_company   = $shippingAddress['company'];


/*  GETTING CUSTOMER INFO*/
$customer_firstname  = $order['customer_firstname'];
$customer_lastname   = $order['customer_lastname'];
$customer_email      = $order['customer_email'];
//$order_currency_code = $order['order_currency_code'];


$billingAddress    = $order->getBillingAddress();

if($order['customer_firstname']=='')
{

    $customer_firstname =$billingAddress->firstname;
    $customer_lastname =$billingAddress->lastname;
    $customer_email    = $billingAddress->email;
}





$order_currency_code=$Currency;

/*GETTING ORDER ITEMS*/
$items = $order->getAllVisibleItems();




//CHECK THIS CUSTOMER ALREADY IN QB OR NO 
$db->where("display_name", "$customer_firstname $customer_lastname");
$user = $db->getOne("qb_customers");

if ($db->count > 0) {
      $count_customr = $db->count;
      $qb_customer_id = $user['qb_customer_id'];
    
    /////if customer found then check his existing currency from qb if its diffrent from orderd currency 


    //IF ORDERD CURRENCY IS DIFFERENT FROM CUTOMER CURRENCY CHECK THIS CUSTOMER IN QB OR NO   
    if($user['currency']!=$order_currency_code)
    {
        //CHECK THIS CUSIMER EXIST WITH CURRNCY PREFIX
            $db->where("display_name", "$customer_firstname $customer_lastname-$order_currency_code");
            $user = $db->getOne("qb_customers");

            $count_customr = $db->count;
                if ($db->count > 0) {
                    $qb_customer_id = $user['qb_customer_id'];
                    $data = Array(
                        'customer_id' => $order['customer_id'],
                        "currency" => $order_currency_code    
                    );
                    $db->where('qb_customer_id', $qb_customer_id);
                    $db->update('qb_customers', $data);
                }


    }
    else
    {
            $data = Array(
                'customer_id' => $order['customer_id'],
                "currency" => $order_currency_code        
            );
            $db->where('qb_customer_id', $qb_customer_id);
            $db->update('qb_customers', $data);
    }


    
}else{
    //IF NOT FOUND IN OUR TABLE THEN WE HAVE TO DO CHECK SAME PERSON WITH  CURRENCY IN DISPLAY NAME

    $db->where("display_name", "$customer_firstname $customer_lastname-$order_currency_code");
    $user = $db->getOne("qb_customers");

    $count_customr = $db->count;
        if ($db->count > 0) {
            $qb_customer_id = $user['qb_customer_id'];
            $data = Array(
                'customer_id' => $order['customer_id'],
                "currency" => $order_currency_code    
            );
            $db->where('qb_customer_id', $qb_customer_id);
            $db->update('qb_customers', $data);
        }



} 










 //if not found then insert in qb and in return insert id from qb to local database
    if($count_customr==0){
    
    ////QICKBOOK ADD CUSTOMER 
    $CustomerService = new QuickBooks_IPP_Service_Customer();
    
    $Customer = new QuickBooks_IPP_Object_Customer();
    $Customer->setGivenName($customer_firstname);
    $Customer->setFamilyName($customer_lastname);
    $Customer->setDisplayName($customer_firstname . ' ' . $customer_lastname.'-'.$order_currency_code);
    
    // Phone #
    $PrimaryPhone = new QuickBooks_IPP_Object_PrimaryPhone();
    $PrimaryPhone->setFreeFormNumber($customer_telephone);
    $Customer->setPrimaryPhone($PrimaryPhone);
    
    // Mobile #
    $Mobile = new QuickBooks_IPP_Object_Mobile();
    $Mobile->setFreeFormNumber($customer_telephone);
    $Customer->setMobile($Mobile);
    
    // Fax #
    $Fax = new QuickBooks_IPP_Object_Fax();
    $Fax->setFreeFormNumber($customer_telephone);
    $Customer->setFax($Fax);
    
    // Bill address
    $BillAddr = new QuickBooks_IPP_Object_BillAddr();
    $BillAddr->setLine1($shipping_street);
    $BillAddr->setCity($shipping_city);
    $BillAddr->setCountrySubDivisionCode($shipping_country);
    $Customer->setBillAddr($BillAddr);
    $Customer->setCurrencyRef($order_currency_code);
    
    // Email
    $PrimaryEmailAddr = new QuickBooks_IPP_Object_PrimaryEmailAddr();
    $PrimaryEmailAddr->setAddress($customer_email);
    $Customer->setPrimaryEmailAddr($PrimaryEmailAddr);
    
    if ($resp = $CustomerService->add($Context, $realm, $Customer)) {
        $resp = str_replace('-', '', $resp);
        $resp = str_replace('{', '', $resp);
        $resp = str_replace('}', '', $resp);
        
echo "<br>";
        print('Our new customer ID is: [' . $resp . '] (name "' . $Customer->getDisplayName() . '")');
        
        $data = Array(
            "customer_id" => $order['customer_id'],
            "qb_customer_id" => $resp,
            "customer_firstname" => $customer_firstname,
            "customer_lastname" => $customer_lastname,
            "customer_email" => $customer_email,
            "display_name" => $customer_firstname . ' ' . $customer_lastname.'-'.$order_currency_code,
            "currency" => $order_currency_code
        );
        
        $id = $db->insert('qb_customers', $data);
        
        $qb_customer_id = $resp;
    } else {
        print($CustomerService->lastError($Context));
    }
    
    
}











//if not papal or paytabs else we just make direct payment
if(!$PaymentOnly)
{


if($qb_customer_id!=0)
{


    //CREATING ESTIMATE///
    $EstimateService = new QuickBooks_IPP_Service_Estimate();

    $Estimate = new QuickBooks_IPP_Object_Estimate();

    $Estimate->setDocNumber($OrderNumber);
    $Estimate->setTxnDate(date('Y-m-d'));


    ///loop for each record for order items 
    foreach ($items as $_item) {
        
        $newskus    = $_item->getSku();
        $newdescs   = $_item->getName();
        $sku        = $_product["sku"];
        $qty        = $_item->getQtyOrdered();
        $item_price = round($_item['price']);
        $sub_total  = round($_item['row_total']);
        
        
        $OrderSkuArray = split('-', $newskus);
        if (count($OrderSkuArray) > 1) {
            $first_sku = $OrderSkuArray[0];
            $d         = $OrderSkuArray[1]; //this is the logic where we check if item buy with accessories
            foreach ($OrderSkuArray as $OrderSkuArrayKey => $OrderSkuArrayValue) {
                $skuq        = "SELECT *  FROM `catalog_product_entity` WHERE sku='$OrderSkuArrayValue'";
                $squery      = mysqli_query($kdbcon, $skuq);
                $squerycount = mysqli_num_rows($squery);
                
                if ($squerycount == 0) {
                    unset($OrderSkuArray[0]);
                }
            }
            
        }
        
        $d=0;
        //ADD ITEMS IN ESTIMATE IF ONE ORDER CONTAIN MULTIPLE SKUS FIRST SKU WILL ADD WITH PRICE
        foreach ($OrderSkuArray as $OrderSkuArrayKey => $OrderSkuArrayValue) {
            
            //CHECK THIS ITEM ALREADY IN QB OR NO 
            $db->where("Name like '%$OrderSkuArrayValue%'");
            $item_array = $db->getOne("qb_example_item");
           
            if ($db->count > 0) {
                $qb_item_id = $item_array['ListID'];
                $qb_item_desc = $item_array['SalesDesc'];
                
            } else //if not found then insert in qb and in return insert id from qb to local database
                {
                
                $ItemService = new QuickBooks_IPP_Service_Item();
                $Item        = new QuickBooks_IPP_Object_Item();
                
                $Item->setName($OrderSkuArrayValue);
                $Item->setType('Inventory');
                 $Item->setSku($OrderSkuArrayValue);
                  $Item->setAssetAccountRef('83');//83 = Inventory Asset
                  $Item->setInvStartDate(date('Y-m-d'));
               
                 $Item->setQtyOnHand($qty);
                ///1 is sales account
                $Item->setIncomeAccountRef('1');
                
                if ($resp = $ItemService->add($Context, $realm, $Item)) {
                    $resp = str_replace('-', '', $resp);
                    $resp = str_replace('{', '', $resp);
                    $resp = str_replace('}', '', $resp);
                    
                    
                    
                    $data = Array(
                        "ListID" => $resp,
                        "Name" => $OrderSkuArrayValue,
                        "FullName" => $OrderSkuArrayValue
                    );
                    
                    $id = $db->insert('qb_example_item', $data);
                    
                    
echo "<br>";
                    print('Our new Item ID is: [' . $resp . ']');
                    
                    $qb_item_id = $resp;
                } else {
                    print($ItemService->lastError($Context));
                }
                
                 $qb_item_desc='--------------------';
            }
            ///IF MULTIPLE SKUS IN ONE ITEM THEN WE WILL ADD PRICE WITH FIRST SKU IN ESTIMATE
            if ($d != 0) {
                $item_price = $sub_total = 0;
            }
                $qty=round($qty);
                
echo "<br>";
                echo "sku=$OrderSkuArrayValue&amount=$item_price&qty=$qty&sub_total=$sub_total&qb_item_desc=$qb_item_desc<br>";
        
                ///add EACH ITE
                
                $Line = new QuickBooks_IPP_Object_Line();
                $Line->setDetailType('SalesItemLineDetail');
                $Line->setAmount(($item_price*$qty));
                $Line->setDescription($qb_item_desc);
              
                $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                $SalesItemLineDetail->setUnitPrice($item_price);
                $SalesItemLineDetail->setItemRef($qb_item_id);
                $SalesItemLineDetail->setItemName($OrderSkuArrayValue);
                $SalesItemLineDetail->setQty($qty);
                $Line->addSalesItemLineDetail($SalesItemLineDetail);
                $Estimate->addLine($Line);
            
            
           $d++;
        }
        
        
        
    } ///END FIRST ITEM LOOP FOR PRODUCTS

            ///DISCOUNT AND SHIPPING AND TAX WILL ADD AS ITEM IN ESTIMATE

            /// ITEM ID 1075 ITEM NAME SHIPPING & HANDLING 
           if($order['shipping_incl_tax']>0)
           {
                $Line = new QuickBooks_IPP_Object_Line();
                $Line->setDetailType('SalesItemLineDetail');
                $Line->setAmount(($order['shipping_incl_tax']));
              
                $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                $SalesItemLineDetail->setUnitPrice($order['shipping_incl_tax']);
                $SalesItemLineDetail->setItemRef('1075');
                $SalesItemLineDetail->setQty('1');
                $Line->addSalesItemLineDetail($SalesItemLineDetail);
                $Estimate->addLine($Line);
           }



            /// ITEM ID 1075 ITEM NAME SHIPPING & HANDLING 
           if($order['discount_invoiced']>0)
           {
                $Line = new QuickBooks_IPP_Object_Line();
                $Line->setDetailType('SalesItemLineDetail');
                $Line->setAmount((-$order['discount_invoiced']));
              
                $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                $SalesItemLineDetail->setUnitPrice(-$order['discount_invoiced']);
                $SalesItemLineDetail->setItemRef('1076');
                $SalesItemLineDetail->setQty('1');
                $Line->addSalesItemLineDetail($SalesItemLineDetail);
                $Estimate->addLine($Line);
           }



    //ASSIGNING CUSTOMER TO ESTIMATE
    $Estimate->setCustomerRef($qb_customer_id);



    if ($resp = $EstimateService->add($Context, $realm, $Estimate)) {

         $resp = str_replace('-', '', $resp);
         $resp = str_replace('{', '', $resp);
         $estimate_id = str_replace('}', '', $resp);


echo "<br>";
        print('Our new Estimate ID is: [' . $resp . ']');
        
echo "<br>";
        echo "Estimate Created in QuickBooks for Customer " . $customer_firstname . ' ' . $customer_lastname;
    } else {
        print($EstimateService->lastError());
    }

}






























if($estimate_id!=0)
{

        ////CREATE INVOICE///

        $InvoiceService = new QuickBooks_IPP_Service_Invoice();

        $Invoice = new QuickBooks_IPP_Object_Invoice();

        $Invoice->setDocNumber($OrderNumber);
        $Invoice->setTxnDate(date('Y-m-d'));


        ///loop for each record for order items 
        foreach ($items as $_item) {
            
            $newskus    = $_item->getSku();
            $newdescs   = $_item->getName();
            $sku        = $_product["sku"];
            $qty        = $_item->getQtyOrdered();
            $item_price = round($_item['price']);
            $sub_total  = round($_item['row_total']);
            
            
            $OrderSkuArray = split('-', $newskus);
            if (count($OrderSkuArray) > 1) {
                $first_sku = $OrderSkuArray[0];
                $d         = $OrderSkuArray[1]; //this is the logic where we check if item buy with accessories
                foreach ($OrderSkuArray as $OrderSkuArrayKey => $OrderSkuArrayValue) {
                    $skuq        = "SELECT *  FROM `catalog_product_entity` WHERE sku='$OrderSkuArrayValue'";
                    $squery      = mysqli_query($kdbcon, $skuq);
                    $squerycount = mysqli_num_rows($squery);
                    
                    if ($squerycount == 0) {
                        unset($OrderSkuArray[0]);
                    }
                }
                
            }
            
            $d=0;
         //ADD ITEMS IN ESTIMATE IF ONE ORDER CONTAIN MULTIPLE SKUS FIRST SKU WILL ADD WITH PRICE
            foreach ($OrderSkuArray as $OrderSkuArrayKey => $OrderSkuArrayValue)
            {

                //CHECK THIS ITEM ALREADY IN QB OR NO 
                $db->where("Name like '%$OrderSkuArrayValue%'");
                $item_array = $db->getOne("qb_example_item");
                
                if ($db->count > 0) {
                    $qb_item_id = $item_array['ListID'];
                } 

                $Line = new QuickBooks_IPP_Object_Line();
                $Line->setDetailType('SalesItemLineDetail');
                $Line->setAmount(($item_price*$qty));
                $Line->setDescription($qb_item_desc);

                $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                $SalesItemLineDetail->setUnitPrice($item_price);
                $SalesItemLineDetail->setItemRef($qb_item_id);
                $SalesItemLineDetail->setItemName($OrderSkuArrayValue);
                $SalesItemLineDetail->setQty($qty);
                $Line->addSalesItemLineDetail($SalesItemLineDetail);
                $Invoice->addLine($Line);
            }

        }



          ///DISCOUNT AND SHIPPING AND TAX WILL ADD AS ITEM IN ESTIMATE

                /// ITEM ID 1075 ITEM NAME SHIPPING & HANDLING 
               if($order['shipping_incl_tax']>0)
               {
                    $Line = new QuickBooks_IPP_Object_Line();
                    $Line->setDetailType('SalesItemLineDetail');
                    $Line->setAmount(($order['shipping_incl_tax']));
                  
                    $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                    $SalesItemLineDetail->setUnitPrice($order['shipping_incl_tax']);
                    $SalesItemLineDetail->setItemRef('1075');
                    $SalesItemLineDetail->setQty('1');
                    $Line->addSalesItemLineDetail($SalesItemLineDetail);
                    $Invoice->addLine($Line);
               }



                /// ITEM ID 1075 ITEM NAME SHIPPING & HANDLING 
               if($order['discount_invoiced']>0)
               {
                    $Line = new QuickBooks_IPP_Object_Line();
                    $Line->setDetailType('SalesItemLineDetail');
                    $Line->setAmount((-$order['discount_invoiced']));
                  
                    $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
                    $SalesItemLineDetail->setUnitPrice(-$order['discount_invoiced']);
                    $SalesItemLineDetail->setItemRef('1076');
                    $SalesItemLineDetail->setQty('1');
                    $Line->addSalesItemLineDetail($SalesItemLineDetail);
                    $Invoice->addLine($Line);
               }




        $Invoice->setCustomerRef($qb_customer_id);


        if ($resp = $InvoiceService->add($Context, $realm, $Invoice))
        {
             $resp = str_replace('-', '', $resp);
             $resp = str_replace('{', '', $resp);
             $invoice_id = str_replace('}', '', $resp);
            
            print('Our new Invoice ID is: [' . $resp . ']');
        }
        else
        {
            print($InvoiceService->lastError());
        }


}

}










if($PaymentOnly||$invoice_id!=0)
{


        ////CREATING PAMENTS

        $total=$order['shipping_incl_tax'];
        

        if(!$PaymentOnly)
        {
            ///loop for each record for order items 
            foreach ($items as $_item) {
                
                $qty        = $_item->getQtyOrdered();
                $item_price = round($_item['price']);
                $total+=($item_price*$qty);
            }
            
        $total=$total-$order['discount_invoiced'];
        }
        else
        {
            $total = $PaymentTotal;
        }

        $PaymentService = new QuickBooks_IPP_Service_Payment();

        // Create payment object
        $Payment = new QuickBooks_IPP_Object_Payment();

        $Payment->setPaymentRefNum($OrderNumber);
        $Payment->setTxnDate(date('Y-m-d'));
        $Payment->setTotalAmt($total);



        if(!$PaymentOnly)
        {

                    // Create line for payment (this details what it's applied to)
            $Line = new QuickBooks_IPP_Object_Line();
            $Line->setAmount($total);
            $Line->setDescription($qb_item_desc);

         
            // The line has a LinkedTxn node which links to the actual invoice
            $LinkedTxn = new QuickBooks_IPP_Object_LinkedTxn();
            $LinkedTxn->setTxnId($invoice_id);
            $LinkedTxn->setTxnType('Invoice');
            $Line->setLinkedTxn($LinkedTxn);

             $Payment->addLine($Line);
        }
        



        $Payment->setPaymentMethodRef('6');
        
           
        ///CHART OF ACCOUNT REF///

       
        if($GatewayName=='paytabs')
        {
            ///name PT-Paytabs.co id 105
            ///name PT-Paytabs-USD id 137

            if($order_currency_code=='USD')
            {
               
                $Payment->setDepositToAccountRef('137');
            }

            if($order_currency_code=='AED'||$order_currency_code=='SAR')
            {
                $Payment->setDepositToAccountRef('105');
            }
            
        }

        if($GatewayName=='paypal')
        {
             ///name PP-Paypal id 110
            if($order_currency_code=='USD')
            {
                $Payment->setDepositToAccountRef('110');
            }
        }

        
        
        $Payment->setCustomerRef($qb_customer_id);

        // Send payment to QBO 
        if ($resp = $PaymentService->add($Context, $realm, $Payment))
        {
            print('Our new Payment ID is: [' . $resp . ']');
        }
        else
        {
            print($PaymentService->lastError());
        }


    }
}///update quickbook
?>
