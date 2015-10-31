<?php
	include('header.php');
	require('magento.php');
	require('quick_books_update.php');//QUICKBOOK DATA PROCESSING FILE



		$Response=json_encode($_REQUEST);
		$ocd_order_no = $_REQUEST['reference_id'];
	
		//COFIG FOR QUICKBOOK FUNCTION 
		$GatewayName	=	'paytabs';
		$OrderNumber	=	$ocd_order_no;
		$TotalAmount	=	$_REQUEST['amount'];
		$Currency   	=	$_REQUEST['currency'];


		$insert = mysqli_query($kdbcon,"INSERT INTO o_paytabs_transactions (Response,Reference) values('$Response','$ocd_order_no') ");



		$Response=$_REQUEST;

		$resp_code	  = $Response['response_code'];
		
		//LOADING ORDER NUMBER 
		$order      	= Mage::getModel('sales/order')->load($ocd_order_no, 'increment_id');
		$entity_id      = $order->getId();
	    $payment        = $order->getPayment();
	    $payment_type   = $payment['method'];
		$username   	= "Paytabs";
		$con_time		= date('d-m-Y H:i:s');
		$mag_time		= date('m/d/y H:i:s');
		$ip_adr 		= $_SERVER['REMOTE_ADDR'];


		if(($Response['response_code']=='5001') OR ($Response['response_code']=='5002'))
		{
			//update_quickbooks($GatewayName,$OrderNumber,$TotalAmount,$Currency,true);

			$order_query = "
			UPDATE 
				o_order_con_del
			SET 
				ocd_con_status = 'Confirmed',
				ocd_del_status = 'Undelivered',
				ocd_ovl_status = 'Processing',
				ocd_can_status = '',
				ocd_con_user='$username',
				ocd_con_ip_add='$ip_adr',
				ocd_entity_id='$entity_id',
				is_paid='1'
				WHERE ocd_order_no='$ocd_order_no' and is_paid='0'";

			$order_query = mysqli_query($kdbcon,$order_query);
		

			
			$ovl_status		= "Processing";

			//BEIZU QUERY START

			if(($ocd_payment_mode=='phoenix_cashondelivery') OR ($ocd_payment_mode=='cashondelivery'))
			{
				$cod_queren = 'YES';
				$shoukuan 	= 'NO';
			}
			else
			{
				$cod_queren = 'NO';
				$shoukuan 	= 'YES';
			}
		
			$orderquxiao 	= 'NO';
			$szbeihuo		= 'NO';

			$select = "SELECT * FROM cs_order_beizhu WHERE order_id='$entity_id'";
			$squery = mysqli_query($kdbcon, $select);
			$scount = mysqli_num_rows($squery);
			
			if($scount==1)
			{
				 $beizhu_query = "UPDATE cs_order_beizhu
								SET cod_queren='$cod_queren',
								    cod_time='$mag_time',
								    cod_user='$username',
								    shoukuan='$shoukuan',
								    orderquxiao='$orderquxiao',
								    shoukuan_time='$mag_time',
								    shoukuan_user='$username'
								WHERE order_id='$entity_id'";
			}
			else
			{
				$beizhu_query = "INSERT INTO cs_order_beizhu (order_id, increment_id, cod_queren, shoukuan, orderquxiao, szbeihuo, cod_time, cod_user, shoukuan_time, shoukuan_user)
								VALUES ('$entity_id',
								        '$ocd_order_no',
								        '$cod_queren',
								        '$shoukuan',
								        '$orderquxiao',
								        '$szbeihuo',
								        '$mag_time',
								        '$username',
								        '$mag_time',
								        '$username')";
			
			}
			 mysqli_query($kdbcon, $beizhu_query) or die(mysql_error());
			

		
		}




		//IF PAYEMENT REJECTED
		if($Response['response_code']=='5000')
		{

			$ovl_status 	= "canceled";
			$con_status 	= "Updated";
			$ocd_can_note 	= "Payment Not Verified";
			$ocd_can_reason = "Paytabs Payment Rejected";
			$ocd_del_status = "Undelivered";
			$con_status 	= "Updated";






		$order_query = mysqli_query($kdbcon,"
			UPDATE 
				o_order_con_del
			SET 
				ocd_con_status = 'Confirmed',
				ocd_del_status = 'Undelivered',
				ocd_ovl_status = 'Cancelled',
				ocd_can_status = 'Cancelled',
				ocd_con_user   = '$username',
				ocd_con_ip_add = '$ip_adr',
				ocd_entity_id  = '$entity_id'
				WHERE ocd_order_no='$ocd_order_no'
			");

				mysqli_query($kdbcon, $order_query);


			$insert_history_query	= "INSERT INTO o_order_history (hs_order_no, hs_entity_id, hs_remarks, hs_updated_user, hs_status, hs_updated_ip_add)
										VALUES ('$ocd_order_no',
										        '$entity_id',
										        '$ocd_can_note',
										        '$username',
										        '$con_status',
										        '$ip_adr')";
			
			mysqli_query($kdbcon, $insert_history_query) or die(mysql_error());


			


			$ord_quxiao = 'YES';

			if($scount==0)
			{
				$beizhu_query = "INSERT INTO cs_order_beizhu (order_id, increment_id, orderquxiao, after_sale, quexiao_time, quexiao_user)
							VALUES ('$entity_id',
							        '$ocd_order_no',
							        '$ord_quxiao',
							        '$ocd_can_note',
							        '$mac_time',
							        '$username')";
			}
			else
			{
				//echo $scount;
				$beizhu_query = "UPDATE cs_order_beizhu
							SET orderquxiao='$ord_quxiao',
							    after_sale='$ocd_can_note',
							    quexiao_time='$mac_time',
							    quexiao_user='$username'
							WHERE (order_id='$entity_id')";
				
			}
			mysqli_query($kdbcon, $beizhu_query) or die(mysqli_error($kdbcon));
		
		}
	
    

    
		$update = "UPDATE sales_flat_order SET status='$ovl_status',payment_method='paytabs' WHERE entity_id='$entity_id'";
			
		$uquery = mysqli_query($kdbcon, $update) or die(mysqli_error($kdbcon));
		
		$updatea = "UPDATE sales_flat_order_grid SET status='$ovl_status' WHERE entity_id='$entity_id'";
			
		$uaquery = mysqli_query($kdbcon, $updatea) or die(mysqli_error($kdbcon));
		


?>
