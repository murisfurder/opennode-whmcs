<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

include_once (dirname(__FILE__) . '/inc/oms_utils.php');

function create_new_vm_with_invoice($invoiceId) {
	global $oms_usage_db;
	//$invoiceId = 7;
	//if ($_SESSION[uid] != 13)//For testing if not developer
	//	return;

	logActivity("Starting to POST new vm data");

	$invoice = getInvoiceById($invoiceId);

	$userid = $invoice['userid'];
	$username = get_username($userid);

	$items = $invoice['items'];
	foreach ($items as $item) {
		$vmData = array();
		$itemId = $item[0]['relid'];
		$clientproduct = getClientsProduct($userid, $itemId);
		//pr($clientproduct);
		//get data from client product
		$vmData[template] = "pld-3.0-x86_64";
		$vmData[hostname] = "pld-3.0-x86_64";
		$vmData[root_password] = "root";
		$vmData[root_password_repeat] = "root";

		//$vmData[swap_size]=0.5;
		if ($clientproduct) {
			//pr($clientproduct);
			//get item
			$products = getBundlesProductsByOtherProductId($clientproduct['pid']);
			if ($products) {
				foreach ($products as $product) {
					if (stristr($product['name'], "core"))
						$vmData[num_cores] = $product['count'];
					if (stristr($product['name'], "ram"))
						$vmData[memory] = $product['count'];
					if (stristr($product['name'], "storage"))
						$vmData[diskspace] = $product['count'];

				}
				logActivity("VM settings. cores: " . $vmData[num_cores] . ". memory:" . $vmData[memory] . ". disk: " . $vmData[diskspace]);
				if ($vmData[num_cores] > 0 && $vmData[memory] > 0 && $vmData[diskspace] > 0) {
					logActivity("Running oms command to create VM.");

					$command = '/machines/hangar/vms-openvz';
					oms_command($command, json_encode($vmData));
				} else {
					logActivity("Error: VM settings not set.");
				}
			}
		}
	}

	logActivity("POST-ing new vm data ended.");
}

function pr($data) {
	print_r("<PRE>");
	print_r($data);
	print_r("/<PRE>");
}

function getInvoiceById($invoiceId) {
	$command = "getinvoice";
	$adminuser = "admin";
	$values["invoiceid"] = $invoiceId;

	$invoice = localAPI($command, $values, $adminuser);
	//pr($invoice);

	return $invoice;
}

function getClientsProduct($clientId, $serviceId) {
	$command = "getclientsproducts";
	$adminuser = "admin";
	$values["clientid"] = $clientId;
	$values["serviceid"] = $serviceId;

	$results = localAPI($command, $values, $adminuser);
	if ($results['result'] == "success") {
		if ($results['products']['product']) {
			return $results['products']['product'][0];
		} else {
			logActivity("No product found. clientId:" . $clientId . ". serviceId:" . $serviceId);
		}

	} else if ($results['result'] == "error") {
		logActivity("Error getting clients products. clientId:" . $clientId . ". serviceId:" . $serviceId . ". Error:" . $clientData['message']);
	}
	return null;
}

function getBundlesProductsByOtherProductId($productId) {
	$sql = "SELECT bundle.id, bundle.name, bundle.itemdata FROM tblbundles bundle INNER JOIN  tblproducts product ON product.name = bundle.name AND product.id=" . $productId;
	$query = mysql_query($sql);
	$bundle = mysql_fetch_array($query);
	if ($bundle) {
		$itemdata = $bundle['itemdata'];
		//find product ids from string
		$ptn = "*\"pid\";[a-z]:[0-9]+:\"[0-9]+\"*";

		preg_match_all($ptn, $itemdata, $matches);

		foreach ($matches[0] as $match) {
			$ptnNr = "/[0-9]+$/";
			$str = str_replace("\"", "", $match);
			preg_match($ptnNr, $str, $matchNr);
			if ($matchNr)
				$productIds[$matchNr[0]]++;
			else
				logActivity("Error parsing itemdata to get product id.");
		}
		//find product names
		$products = array();
		$sum = 0;
		foreach ($productIds as $id => $count) {
			//print_r("Product with id:" . $id . ", count:" . $count);
			//Query for products
			$sql = "SELECT DISTINCT * FROM tblproducts product JOIN tblpricing price ON product.id = price.relid WHERE price.type='product' AND product.id = '" . $id . "'";
			$query = mysql_query($sql);
			$product = mysql_fetch_array($query);

			if ($product) {
				$product['count'] = $count;
				$products[] = $product;
			} else {
				logActivity("Error getting product with id:" . $id);
			}
		}
		return $products;
	} else {
		logActivity("Error getting bundle with id:" . $productId);
	}
	return null;
}

//add_hook("ClientAreaPage", 1, "create_new_vm_with_invoice");
//For testing
//add_hook("AcceptOrder", 0, "create_new_vm");
//add_hook("InvoicePaid", 1, "create_new_vm_with_invoice");
?>