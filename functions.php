<?php
/**
 * AJAX for Constant Contact in WordPress
 */


add_action('wp_ajax_cc_signup', 'cc_signup');
add_action('wp_ajax_nopriv_cc_signup', 'cc_signup');

/**
 * AJAX for Constant Contact in WordPress. This ajax endpoint expects
 * two variables via $_REQUEST, EmailAddress and action. The included
 * class-constant-contact.php class has been stripped to only accept
 * an email address
 *
 * @return	string
 */
function cc_signup(){
	if (!empty($_REQUEST) && !empty($_REQUEST['EmailAddress'])) {
		include_once dirname(__FILE__)."/class-constant-contact.php";
		$ccConfigOBJ = new CC_Config();
		$ccContactOBJ = new CC_Contact();
		$ccListOBJ = new CC_List(); 
		$postFields = array();
	
		// ## PROCESS BASIC FIELDS ## //
		$postFields["email_address"] = @$_REQUEST["EmailAddress"];
		$postFields["success_url"] = @$_REQUEST["SuccessURL"];
		$postFields["failure_url"] = @$_REQUEST["FailureURL"];
		$postFields["request_type"] = @$_REQUEST["RequestType"];
	
		// ## PROCESS LISTS ## //
		$allLists = $ccListOBJ->getLists('', true);	
		foreach ($allLists as $k=>$item) {
			if(!empty($_REQUEST['Lists'])){
				if (in_array($item['title'],$_REQUEST['Lists'])) {
					$postFields["lists"][] = $item['id'];
				}
			}
			else {
				if (in_array($item['title'],$ccConfigOBJ->contact_lists)) {
					$postFields["lists"][] = $item['id'];
				}
			}
		}
	
		$contactXML = $ccContactOBJ->createContactXML(null,$postFields);
		$return_code = $ccContactOBJ->addSubscriber($contactXML);
	
		$error = true;
		if ($return_code==201) {
			$error = false;
			$status = array(
				'error' => $error,
				'code' => $return_code,
				'title' => 'Thank You!',
				'message' => 'You will receive an email shortly confirming your subscription.'
			);
		} elseif ($return_code==409) {
			$status = array(
				'error' => $error,
				'code' => $return_code,
				'title' => 'We\'re Sorry!',
				'message' => 'It appears that you are already a subscriber of our mailing list.'
			);
		} else {
			$status = array(
				'error' => $error,
				'code' => $return_code,
				'title' => 'We\'re Sorry!',
				'message' => 'It appears that you were not added to our mailing list.'
			);
		}
	}

	header("Content-type: text/json");
	echo json_encode($status);
	exit;
}

/**
 * This theme was built with PHP, Semantic HTML, CSS, and love.
 */