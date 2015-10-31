<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dubai');


function update_payment_gateways_quickbooks($GatewayName='paytabs')
{
/* include dirname(__FILE__).'/php-qb/quickbooks-php-master/docs/partner_platform/example_app_ipp_v3/config.php';*/
 

 include '../php-qb/quickbooks-php-master/docs/partner_platform/example_app_ipp_v3-dev/config.php';


date_default_timezone_set('Asia/Dubai');
echo "<br>";

if($GatewayName=='paytabs')
{

$OrderNumber    =   $_REQUEST['reference_id'].'-'.rand();
$TotalAmount    =   $_REQUEST['amount'];
$Currency       =   $_REQUEST['currency'];

$customer_telephone = $_REQUEST['phone_num'];
$shipping_city      = $_REQUEST['shipping_city'];
$shipping_region    = $_REQUEST['shipping_state'];
$shipping_street    = $_REQUEST['shipping_address'];
$shipping_country   = $_REQUEST['shipping_country'];
$order_currency_code= $_REQUEST['currency'];



/*  GETTING CUSTOMER INFO*/

$customer_fullname   = $_REQUEST['customer_name'].rand();

$customer_fullname   = explode(' ',$customer_fullname);
$customer_firstname  = $customer_fullname[0];
$customer_lastname   = $customer_fullname[1];
$customer_email      = $_REQUEST['email'];
$order_number        =  $_REQUEST['reference_id'];
}//END IF PAYTABS




$order = Mage::getModel('sales/order')->load($order_number, 'increment_id');

//CHECK THIS CUSTOMER ALREADY IN QB OR NO 
$db->where("DisplayName", "$customer_firstname $customer_lastname");
$user = $db->getOne("qb_example_customer");
echo $db->getLastQuery();

if ($db->count > 0) {
      $count_customr = $db->count;
      $qb_customer_id = $user['ListID'];
    
    /////if customer found then check his existing currency from qb if its diffrent from orderd currency 


    //IF ORDERD CURRENCY IS DIFFERENT FROM CUTOMER CURRENCY CHECK THIS CUSTOMER IN QB OR NO   
    if($user['Currency']!=$order_currency_code)
    {
        //CHECK THIS CUSIMER EXIST WITH CURRNCY PREFIX
            $db->where("DisplayName", "$customer_firstname $customer_lastname-$order_currency_code");
            $user = $db->getOne("qb_customers");

            $count_customr = $db->count;
                if ($db->count > 0) {
                    $qb_customer_id = $user['qb_customer_id'];
                    $data = Array(
                        'CustomerId' => $order['customer_id'],
                        "Currency" => $order_currency_code    
                    );
                    $db->where('ListID', $qb_customer_id);
                    $db->update('qb_example_customer', $data);
                }


    }
    else
    {
            $data = Array(
                'CustomerId' => $order['customer_id'],
                "Currency" => $order_currency_code        
            );
            $db->where('ListID', $qb_customer_id);
            $db->update('qb_example_customer', $data);
    }


    
}else{
    //IF NOT FOUND IN OUR TABLE THEN WE HAVE TO DO CHECK SAME PERSON WITH  CURRENCY IN DISPLAY NAME

    $db->where("DisplayName", "$customer_firstname $customer_lastname-$order_currency_code");
    $user = $db->getOne("qb_example_customer");

    $count_customr = $db->count;
        if ($db->count > 0) {
            $qb_customer_id = $user['qb_customer_id'];
            $data = Array(
                'CustomerId' => $order['customer_id'],
                "Currency" => $order_currency_code    
            );
            $db->where('ListID', $qb_customer_id);
            $db->update('qb_example_customer', $data);
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
            "CustomerId"    => $order['customer_id'],
            "ListID"        => $resp,
            "FullName"      => $customer_firstname . ' ' . $customer_lastname,
            "FirstName"     => $customer_firstname,
            "LastName"      => $customer_lastname,
            "Email"         => $customer_email,
            "DisplayName"   => $customer_firstname . ' ' . $customer_lastname.'-'.$order_currency_code,
            "Currency"      => $order_currency_code
        );
        
        $id = $db->insert('qb_example_customer', $data);
        
        $qb_customer_id = $resp;
    } else {
        print($CustomerService->lastError($Context));
    }
    
    
}



if($qb_customer_id!=0)
{



        ////CREATING PAMENTS

        $total=$TotalAmount;
      
        $PaymentService = new QuickBooks_IPP_Service_Payment();

        // Create payment object
        $Payment = new QuickBooks_IPP_Object_Payment();

        $Payment->setPaymentRefNum($OrderNumber);
        $Payment->setTxnDate(date('Y-m-d'));
        $Payment->setTotalAmt($total);

        //6 for paytabs
        $Payment->setPaymentMethodRef('6');
        
           
        ///CHART OF ACCOUNT REF///


       
        if($GatewayName=='paytabs')
        {

        /* 
            ID=59 NAME="PT-Paytabs-SAR"
            ID=57 NAME="PT-Paytabs-AED"
            ID=55 NAME="PT-Paytabs-USD"
        */
            if($order_currency_code=='USD')
            {
               
                $Payment->setDepositToAccountRef('55');
            }

            if($order_currency_code=='AED')
            {
                $Payment->setDepositToAccountRef('57');
            }

            if($order_currency_code=='SAR')
            {
                $Payment->setDepositToAccountRef('59');
            }
            
        }

        if($GatewayName=='paypal')
        {
             ///ID 58 NAME "PP-Paypal-USD"
            if($order_currency_code=='USD')
            {
                $Payment->setDepositToAccountRef('58');
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





    
}///update QuickBooks
?>
