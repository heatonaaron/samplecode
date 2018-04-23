<?PHP
/* ******************************************************************************************************************** / 
THIS CLASS USED TO CREATE CUSTOMER PAYMENT PROFILES AND CHARGE THEM FOR AN ORDER
***********************************************************************************************************************/
use net\merchant\api\contract\v1 as MerchantAPI;
use net\merchant\api\controller as MerchantController;

Class Payment {
    private $obj_dbi;    
    private $merchantloginid;
    private $merchanttrankey;
    private $sandbox;
    private $merchanturl;    
    
    public function __construct(){
        require_once(BASE_PATH.'/vendor/autoload.php');
        global $obj_dbi;        
        $this->obj_dbi = $obj_dbi;
    
        $this->merchantloginid	= $GLOBALS['MERCHANT_LOGINID'];
        $this->merchanttrankey	= $GLOBALS['MERCHANT_TRANKEY'];
        
        //SET TO LIVE OR SANDBOX
        if($GLOBALS['MERCHANT_SANDBOX'] == 'no') {
            $this->merchanturl = \net\merchant\api\constants\MerchantEnvironment::PRODUCTION;
        } else {
            $this->merchanturl = \net\merchant\api\constants\MerchantEnvironment::SANDBOX;
        }        
    }    
    
    /* ******************************************************************************************************************** / 
	METHOD: createCustomerProfile
	DESCRIPTION: This method creates the customer profile in merchant
	PARAMETERS: $firstname - first name of customer
                $lastname - last name of customer
                $ccnumber - credit card number to store
                $expdate - experation date
                $csv - security code
                $address - credit card billing address
                $city - credit card billing city
                $state - credit card billing state
                $zip - credit card billing zip
                $phone - credit card billing phone
    RETURNS: $response - array with success or failure and errors
	***********************************************************************************************************************/
    public function createCustomerProfile($firstname,$lastname,$ccnumber,$expdate,$csv, $address, $city,$state,$zip,$phone){
    /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
        $merchantAuthentication = new MerchantAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->merchantloginid);
        $merchantAuthentication->setTransactionKey($this->merchanttrankey);
    
        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create a Customer Profile Request
        //  1. (Optionally) create a Payment Profile
        //  2. (Optionally) create a Shipping Profile
        //  3. Create a Customer Profile (or specify an existing profile)
        //  4. Submit a CreateCustomerProfile Request
        //  5. Validate Profile ID returned                
        // Set credit card information for payment profile
        $creditCard = new MerchantAPI\CreditCardType();
        $creditCard->setCardNumber($ccnumber);
        $creditCard->setExpirationDate($expdate);
        $creditCard->setCardCode($csv);
        $paymentCreditCard = new MerchantAPI\PaymentType();
        $paymentCreditCard->setCreditCard($creditCard);

        // Create the Bill To info for new payment type
        $billTo = new MerchantAPI\CustomerAddressType();
        $billTo->setFirstName($firstname);
        $billTo->setLastName($lastname);
        $billTo->setCompany("");
        $billTo->setAddress($address);
        $billTo->setCity($city);
        $billTo->setState($state);
        $billTo->setZip($zip);
        $billTo->setCountry("USA");
        $billTo->setPhoneNumber($phone);
        $billTo->setfaxNumber("");

        // Create a new CustomerPaymentProfile object
        $paymentProfile = new MerchantAPI\CustomerPaymentProfileType();
        $paymentProfile->setCustomerType('individual');
        $paymentProfile->setBillTo($billTo);
        $paymentProfile->setPayment($paymentCreditCard);
        $paymentProfile->setDefaultpaymentProfile(true);
        $paymentProfiles[] = $paymentProfile;

        $email = '';

        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new MerchantAPI\CustomerProfileType();
        $customerProfile->setDescription($firstname.' '.$lastname.' profile');
        $customerProfile->setMerchantCustomerId("M_" . time());
        $customerProfile->setEmail($email);
        $customerProfile->setpaymentProfiles($paymentProfiles);

        // Assemble the complete transaction request
        $request = new MerchantAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setProfile($customerProfile);

        // Create the controller and get the response
        $controller = new MerchantController\CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->merchanturl);

        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            $profileid = $response->getCustomerProfileId();
            $paymentProfiles = $response->getCustomerPaymentProfileIdList();
            $paymentid = $paymentProfiles[0];
            $response = array('success'=>'true', 'profileids'=>$profileid."|".$paymentid);
        } else {            
            $errorMessages = "ERROR :  Invalid response ";
            $errorMessages .= $response->getMessages()->getMessage();
            $response =  array('success'=>'false', 'errors'=>$errorMessages);
            
            mail(TECH_SUPPORT_EMAILS, 'Customer profile not created', print_r($response, 1));
        }
        
        return $response;
    }    
    
    /* ******************************************************************************************************************** / 
	METHOD: chargeCustomerProfile
	DESCRIPTION: This method charges a customer profile in merchant
	PARAMETERS: $order_id - order that is being charged
                $amount - amount to be charged
                $customer_id - id of customer to charge
    RETURNS: an array of list data
	***********************************************************************************************************************/
    public function chargeCustomerProfile($order_id, $amount, $customer_id) {
        //Get Payment IDs
        $sql = "SELECT payment_ids
                FROM customer_cc
                WHERE id = ?";
        $result = $this->obj_dbi->query($sql, 'i', array($customer_id), false);
        $row = $this->obj_dbi->getRow($result);
        $idarray = explode('|',$row['payment_ids']);
        
        $profileid = $idarray[0];
        $paymentprofileid = $idarray[1];
                
        /* Create a merchantAuthenticationType object with authentication details
        retrieved from the constants file */
        $merchantAuthentication = new MerchantAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->merchantloginid);
        $merchantAuthentication->setTransactionKey($this->merchanttrankey);

        // Set the transaction's refId
        $refId = 'ref' . time();

        $profileToCharge = new MerchantAPI\CustomerProfilePaymentType();
        $profileToCharge->setCustomerProfileId($profileid);
        $paymentProfile = new MerchantAPI\PaymentProfileType();
        $paymentProfile->setPaymentProfileId($paymentprofileid);
        $profileToCharge->setPaymentProfile($paymentProfile);

        $transactionRequestType = new MerchantAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType( "authCaptureTransaction");
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setProfile($profileToCharge);

        $request = new MerchantAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId( $refId);
        $request->setTransactionRequest( $transactionRequestType);
        $controller = new MerchantController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->merchanturl);
        
        if ($response != null) {
            if($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {                    
                    $response = " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    $response.=  "Charge Customer Profile APPROVED  :" . "\n";
                    $response.= " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
                    $response.= " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
                    $response.= " Code : " . $tresponse->getMessages()[0]->getCode() . "\n"; 
                    $response.= " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";                    
                    
                    $sql = "UPDATE order_payments
                            SET trans_id = ?
                            WHERE order_id = ?";
                    $result = $this->obj_dbi->query($sql,'si', array($tresponse->getTransId(),$order_id), false);
                    
                    return array('success'=>'true', 'error'=>'');
                } else  {
                    $response.= "Transaction Failed \n";
                    
                    if($tresponse->getErrors() != null) {
                        $response = " Error code  : " . $tresponse->getErrors()[0]->getErrorCode()."|";
                        $response .= " Error message : " . $tresponse->getErrors()[0]->getErrorText()."|";
                        
                        $sql = "INSERT INTO credit_card_errors(order_id, response) 
                                VALUES (?,?)";                        
                        $result = $this->obj_dbi->query($sql, 'is', array($order_id,$response), false);
                        
                    }
                }
            } else {
                $tresponse = "Transaction Failed \n";
                $tresponse.= $response->getTransactionResponse();
                
                if($tresponse != null && $tresponse->getErrors() != null) {
                    $response.= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                    $response.= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";                      
                } else {
                    $response.= " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                    $response.= " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
                }
                
                $sql = "INSERT INTO credit_card_errors(order_id, response) 
                        VALUES (?,?)";                        
                $result = $this->obj_dbi->query($sql, 'is', array($order_id,$response), false);
            }
        }  else {
            $response = "No response returned \n";
    
            $sql = "INSERT INTO credit_card_errors(order_id, response) 
                    VALUES (?,?)";                        
            $result = $this->obj_dbi->query($sql, 'is', array($order_id,$response), false);
        }

        return array('success'=>'false', 'error'=>$response);
    }
}