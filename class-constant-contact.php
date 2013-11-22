<?php

/**
* Class that is using REST to communicate with ConstantContact server
* This class currently supports actions performed using the contacts, lists, and campaigns APIs
* @author ConstantContact Dev Team
* @version 2.0.0
* @since 30.03.2010 
*/
class CC_Config {
	var $login = 'CC_LOGIN_HERE';
	var $password = 'CC_PASSWORD_HERE';
	var $apikey = "CC_API_KEY_HERE"; 
	var $contact_lists = array("List Name");
	var $force_lists = false; 
	var $included_fields = array("EmailAddress");
	var $custom_field_labels = array();
	var $show_contact_lists = false;
	var $actionBy = "ACTION_BY_CONTACT"; 
	var $success_url = "";
	var $failure_url = "";
	var $make_dialog = "";
}

class CC_Utility extends CC_Config {

	// YOUR BASIC CHANGES SHOULD END HERE
	var $requestLogin; //this contains full authentication string.
	var $lastError = ''; // this variable will contain last error message (if any)
	var $apiPath = 'https://api.constantcontact.com/ws/customers/'; //is used for server calls.
	var $doNotIncludeLists = array('Removed', 'Do Not Mail', 'Active'); //define which lists shouldn't be returned.

	public function __construct() {
		//when the object is getting initialized, the login string must be created as API_KEY%LOGIN:PASSWORD
		$this->requestLogin = $this->apikey."%".$this->login.":".$this->password;
		$this->apiPath = $this->apiPath.$this->login;
	}         

	/**
	* Validate an email address
	* @return  TRUE if address is valid and FALSE if not.
	*/    
	protected function isValidEmail($email){
		return eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email);
	} 

	/**
	* Private function used to send requests to ConstantContact server
	* @param string $request - is the URL where the request will be made
	* @param string $parameter - if it is not empty then this parameter will be sent using POST method
	* @param string $type - GET/POST/PUT/DELETE
	* @return a string containing server output/response
	*/    
	public function doServerCall($request, $parameter = '', $type = "GET") {
		$ch = curl_init();
		$request = str_replace('http://', 'https://', $request);
		// Convert id URI to BASIC compliant
		curl_setopt($ch, CURLOPT_URL, $request);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->requestLogin);
		# curl_setopt ($ch, CURLOPT_FOLLOWLOCATION  ,1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type:application/atom+xml", 'Content-Length: ' . strlen($parameter)));
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		switch ($type) {
			case 'POST':                  
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
			break;
			case 'PUT':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
			break;
			case 'DELETE':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
			break;
			default:
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			break;
		}

		$return['xml'] = curl_exec($ch); 
		$return['info'] = curl_getinfo($ch);
		$return['error'] = curl_error($ch); 

		// Write Data to a log file
		curl_close($ch);
		return $return;
	}

	public function getServiceDescription() {
		$call = $this->apiPath.'/';
		$return = $this->doServerCall($call);
		return $return['info']['http_code'];
	}

}

/**
* Class that is used for retrieving 
* all the Email Lists from Constant Contact and 
* all Registered Email Addresses 
*/
class CC_List extends CC_Utility {

	/**
	* Recursive Method that retrieves all the Email Lists from ConstantContact.
	* @param string $path [default is empty]
	* @return array of lists
	*/    
	public function getLists($path = '', $getAllLists=false) {
		$mailLists = array();

		if ( empty($path)) $call = $this->apiPath.'/lists';
		else $call = $path;

		$return = $this->doServerCall($call);
		$parsedReturn = simplexml_load_string($return['xml']);
		$call2 = '';

		foreach ($parsedReturn->link as $item) {
			$tmp = $item->Attributes();
			$nextUrl = '';      
			if ((string) $tmp->rel == 'next') {
				$nextUrl = (string) $tmp->href;
				$arrTmp = explode($this->login, $nextUrl);
				$nextUrl = $arrTmp[1];
				$call2 = $this->apiPath.$nextUrl;
				break;
			}
		}

		foreach ($parsedReturn->entry as $item) {
			if ($this->contact_lists && !$getAllLists){
				if (in_array((string) $item->title, $this->contact_lists)) {
					$tmp = array();
					$tmp['id'] = (string) $item->id;
					$tmp['title'] = (string) $item->title;
					$mailLists[] = $tmp;
				}
			} else if (!in_array((string) $item->title, $this->doNotIncludeLists)) {
				$tmp = array();
				$tmp['id'] = (string) $item->id;
				$tmp['title'] = (string) $item->title;
				$mailLists[] = $tmp;
			}
		}

		if ( empty($call2)) return $mailLists;
		else return array_merge($mailLists, $this->getLists($call2));
	}

	/**
	* Method that retrieves  all Registered Email Addresses.
	* @param string $email_id [default is empty]
	* @return array of lists
	*/           
	public function getAccountLists($email_id = '') {
		$mailAccountList = array();

		if ( empty($email_id)) $call = $this->apiPath.'/settings/emailaddresses';
		else $call = $this->apiPath.'/settings/emailaddresses/'.$email_id; 

		$return = $this->doServerCall($call);
		$parsedReturn = simplexml_load_string($return['xml']);

		foreach ($parsedReturn->entry as $item) {               
			$nextStatus = $item->content->Email->Status;
			$nextEmail = (string) $item->title;
			$nextId = $item->id; 
			$nextAccountList = array('Email'=>$nextEmail, 'Id'=>$nextId);
			if($nextStatus == 'Verified'){  
				$mailAccountList[] = $nextAccountList; 
			}         
		}
		return $mailAccountList;
	}
}


/**
* Class that is used for ConstantConact CRUD management
*/
class CC_Contact extends CC_Utility {

	/**
	* Upload a new contact to Constant Contact server
	* @param strong $contactXML - formatted XML with contact information
	* @return TRUE in case of success or FALSE otherwise
	*/    
	public function addSubscriber($contactXML) {
		$call = $this->apiPath.'/contacts';
		$return = $this->doServerCall($call, $contactXML, 'POST');
		$parsedReturn = simplexml_load_string($return['xml']);	
		$code = $return['info']['http_code'];
		return $code;
	}

	/**
	* Method that compose the needed XML format for a contact
	* @param string $id
	* @param array $params
	* @return Formed XML
	*/    
	public function createContactXML($id, $params = array()) {
		if ( empty($id)) $id = "urn:uuid:E8553C09F4xcvxCCC53F481214230867087";

		$update_date = date("Y-m-d").'T'.date("H:i:s").'+01:00';
		$xml_string = "<entry xmlns='http://www.w3.org/2005/Atom'></entry>";
		$xml_object = simplexml_load_string($xml_string);
		$title_node = $xml_object->addChild("title", htmlspecialchars("TitleNode"));
		$updated_node = $xml_object->addChild("updated", htmlspecialchars($update_date));
		$author_node = $xml_object->addChild("author");
		$author_name = $author_node->addChild("name", htmlspecialchars("CTCT Samples"));
		$id_node = $xml_object->addChild("id", $id);
		$summary_node = $xml_object->addChild("summary", htmlspecialchars("Customer document"));
		$summary_node->addAttribute("type", "text");
		$content_node = $xml_object->addChild("content");
		$content_node->addAttribute("type", "application/vnd.ctct+xml");
		$contact_node = $content_node->addChild("Contact", htmlspecialchars(""));
		$contact_node->addAttribute("xmlns", "http://ws.constantcontact.com/ns/1.0/");
		$email_node = $contact_node->addChild("EmailAddress", htmlspecialchars($params['email_address']));
		$fname_node = $contact_node->addChild("FirstName", urldecode(htmlspecialchars(@$params['first_name'])));
		$lname_node = $contact_node->addChild("LastName", urldecode(htmlspecialchars(@$params['last_name'])));
		$lname_node = $contact_node->addChild("MiddleName", urldecode(htmlspecialchars(@$params['middle_name'])));
		$lname_node = $contact_node->addChild("CompanyName", urldecode(htmlspecialchars(@$params['company_name'])));
		$lname_node = $contact_node->addChild("JobTitle", urldecode(htmlspecialchars(@$params['job_title'])));

		if (@$params['status'] == 'Do Not Mail') $this->actionBy = 'ACTION_BY_CONTACT';

		$optin_node = $contact_node->addChild("OptInSource", htmlspecialchars($this->actionBy));
		$hn_node = $contact_node->addChild("HomePhone", htmlspecialchars(@$params['home_number']));
		$wn_node = $contact_node->addChild("WorkPhone", htmlspecialchars(@$params['work_number']));
		$ad1_node = $contact_node->addChild("Addr1", htmlspecialchars(@$params['address_line_1']));
		$ad2_node = $contact_node->addChild("Addr2", htmlspecialchars(@$params['address_line_2']));
		$ad3_node = $contact_node->addChild("Addr3", htmlspecialchars(@$params['address_line_3']));
		$city_node = $contact_node->addChild("City", htmlspecialchars(@$params['city_name']));
		$state_node = $contact_node->addChild("StateCode", htmlspecialchars(@$params['state_code']));
		$state_name = $contact_node->addChild("StateName", htmlspecialchars(@$params['state_name']));
		$ctry_node = $contact_node->addChild("CountryCode", htmlspecialchars(@$params['country_code']));
		$zip_node = $contact_node->addChild("PostalCode", htmlspecialchars(@$params['zip_code']));
		$subzip_node = $contact_node->addChild("SubPostalCode", htmlspecialchars(@$params['sub_zip_code']));
		$note_node = $contact_node->addChild("Note", htmlspecialchars(@$params['notes']));
		$emailtype_node = $contact_node->addChild("EmailType", htmlspecialchars(@$params['mail_type']));

		if (! empty($params['custom_fields'])) {
			foreach ($params['custom_fields'] as $k=>$v) {
				$contact_node->addChild("CustomField".$k, htmlspecialchars($v));
			}
		}

		$contactlists_node = $contact_node->addChild("ContactLists");			
		if ($params['lists']) {
			foreach ($params['lists'] as $tmp) {
				$contactlist_node = $contactlists_node->addChild("ContactList");
				$contactlist_node->addAttribute("id", $tmp);
			}
		}

		$entry = $xml_object->asXML();
		return $entry;
	}

}

?>