<?php

class CloseEvents {

    protected $eventType;
    public $mysqlDb;
    public function __construct($event) {
        $this->eventType = $event;   
    }

    public function getMysqlModel() {
        $db = new Database();
        $this->mysqlDb  = $db;
    }

    public function bindQueryparams($data, $template = ':id') {
        $params = [];
        foreach($data as $key => $value) {
            $params[$template . $value] = $value;
        }
        return $params;
    }

}

class CloseOpenedInvoices extends CloseEvents  {

    // call parent constructor
    public $sqlDb; 
    public function __construct() {
        $event  = 'INVOICE_CLOSE';
        parent::__construct($event);
        $this->sqlDb = new SqlDatabase();
    }

    protected function getAllOpenedInvRequests() {

        $sql = "SELECT * FROM `[INV_OPEN_REQUESTS]` WHERE `[INV_OPEN_REQUESTS].[STATUS]` = 1";
        $this->sqlDb->query($sql);
        return $this->sqlDb->resultset();
    }

    // Check is there invoice open requests
    protected function isThereInvOpenRequests($data) {
        
        if (count($data) > 0) {
            return true;
        } else {
            return false;
        }
    }

    // Get all invoices that are opened for more than half an hour

    protected function getInvOpenRequests() {
        $sql = "SELECT * FROM `[INV_OPEN_REQUESTS]` WHERE `[INV_OPEN_REQUESTS].[STATUS]` = 1 AND `[INV_OPEN_REQUESTS].[CREATED_AT]` < DATEADD(MINUTE, -30, GETDATE())";
        $this->sqlDb->query($sql);
        return $this->sqlDb->resultset();
    }

    // Get all invoices that are opened for more than 15 mintues

    protected function getInvOpenRequests15() {
        $sql = "SELECT * FROM `[INV_OPEN_REQUESTS]` WHERE `[INV_OPEN_REQUESTS].[STATUS]` = 1 AND `[INV_OPEN_REQUESTS].[CREATED_AT]` < DATEADD(MINUTE, -15, GETDATE())";
        $this->sqlDb->query($sql);
        return $this->sqlDb->resultset();
    }

    // Get all company ids from invoices that are opened for more than 15 mintues

    protected function getInvOpenRequestsCompanyIds($data) {

        $companyIds = [];
        // loop through the data and only push unique company id
        
        foreach ($data as $key => $value) {
            if (!in_array($value['COMPANY_ID'], $companyIds)) {
                array_push($companyIds, $value['COMPANY_ID']);
            }
        }
        return $companyIds;
    }

    // get all user ids from the request data

    protected function getInvOpenRequestsUserIds($data) {

        $userIds = [];
        // loop through the data and only push unique company id
        
        foreach ($data as $key => $value) {
            if (!in_array($value['CREATED_BY'], $userIds)) {
                array_push($userIds, $value['CREATED_BY']);
            }
            if (!in_array($value['APPROVER'], $userIds)) {
                array_push($userIds, $value['APPROVER']);
            }
            
        }
        return $userIds;
    }

    // Get company details from company ids

    protected function getCompanyDetails($companyIds) {

        $companyDetails = [];
        $companyIds = $this->bindQueryparams($companyIds, ':company_id');
        $this->mysqlDb->query("SELECT * FROM `bds_company` WHERE `comp_id` IN (" . implode(',', array_keys($companyIds)) . ")");
        foreach($companyIds as $key => $value) {
            $this->mysqlDb->bind($key, $value);
        }
        $companyDetails = $this->mysqlDb->resultset();
        return $companyDetails;
    }

    // Get user details from user ids

    protected function getUserDetails($userIds) {

        $userDetails = [];
        $userIds = $this->bindQueryparams($userIds, ':user_id');
        $this->mysqlDb->query("SELECT * FROM `bds_users` WHERE `user_id` IN (" . implode(',', array_keys($userIds)) . ")");
        foreach($userIds as $key => $value) {
            $this->mysqlDb->bind($key, $value);
        }
        $userDetails = $this->mysqlDb->resultset();
        return $userDetails;
    }

    // get user details array with user id as key

    protected function getUserDetailsArray($userDetails) {

        $userDetailsArray = [];
        foreach ($userDetails as $key => $value) {
            $userDetailsArray[$value['user_id']] = $value;
        }
        return $userDetailsArray;
    }

    // get company details array with company id as key

    protected function getCompanyDetailsArray($companyDetails) {

        $companyDetailsArray = [];
        foreach ($companyDetails as $key => $value) {
            $companyDetailsArray[$value['comp_id']] = $value;
        }
        return $companyDetailsArray;
    }

    protected prepareDataForSendingEmail() {
        $requests = $this->getAllOpenedInvoiceRequestsWithEvents();
        // check if there are any requests
        if (!$this->isThereInvOpenRequests($requests)) {
            return [];
        }
            

        $data = [];
        $companyIds = $this->getInvOpenRequestsCompanyIds($requests);
        $userIds = $this->getInvOpenRequestsUserIds($requests);
        $companyDetails = $this->getCompanyDetails($companyIds);
        $userDetails = $this->getUserDetails($userIds);
        $userDetailsArray = $this->getUserDetailsArray($userDetails);
        $companyDetailsArray = $this->getCompanyDetailsArray($companyDetails);
        $dataFor15Min = [];
        $dataFor30Min = [];
        foreach($requests as $request) {
            if(!$requests['is_15_min_passed']) continue; 

            $eventKey = $request['event_id'];
            $requestKey = $request['id'];
            $data[$eventKey][$requestKey] = [];
            $data[$eventKey][$requestKey]['company_name'] = $companyDetailsArray[$request['company_id']]['comp_name'];
            $data[$eventKey][$requestKey]['company_id'] = $request['company_id'];
            $data[$eventKey][$requestKey]['request_id'] = $request['id'];
            $data[$eventKey][$requestKey]['request_created_by'] = $userDetailsArray[$request['created_by']]['user_full_name'];
            $data[$eventKey][$requestKey]['request_created_by_id'] = $request['created_by'];
            $data[$eventKey][$requestKey]['request_created_at'] = $request['created_at'];
            $data[$eventKey][$requestKey]['request_approver'] = $userDetailsArray[$request['approver']]['user_full_name'];
            $data[$eventKey][$requestKey]['request_approver_id'] = $request['approver'];
            $data[$eventKey][$requestKey]['request_status'] = $request['status'];
            $data[$eventKey][$requestKey]['to'] = $userDetailsArray[$request['approver']]['user_email'];
            $data[$eventKey][$requestKey]['cc'] = array_merge([],[$userDetailsArray[$request['created_by']]['user_email']]);
            $data[$eventKey][$requestKey]['subject'] = $this->getEmailSubject($request);
            $data[$eventKey][$requestKey]['body'] = $this->getEmailBody($request);
            $data[$eventKey][$requestKey]['message_sequence'] = $request['message_sequence_id'];
            $data[$eventKey][$requestKey]['event_id'] = $request['event_id'];

            if($request['is_30_min_passed']) {
                $dataFor30Min[] = $data[$eventKey][$requestKey];
            } else {
                $dataFor15Min[] = $data[$eventKey][$requestKey];
            }

            
        }
        return $data;
    }   

    public function sendEmail() {

        $data = $this->prepareDataForSendingEmail();
        if (empty($data)) {
            return;
        }
        // send emails notifying the approvers that the invoice request is still open for 15 minutes

        $this->sendEmails($data);
    }

   
    
    
    
    protected function getAllOpenedInvoiceRequestsWithEvents() {
        $sql = "SELECT 
                `[INV_OPEN_REQUEST_EVENTS].[id] AS `event_id`, 
                `[INV_OPEN_REQUEST_EVENTS].[event]`, 
                `[INV_OPEN_REQUEST_EVENTS].[created_at] as event_time, 
                `[INV_OPEN_REQUESTS].[id], 
                `[INV_OPEN_REQUESTS].[company_id]`,
                `[INV_OPEN_REQUESTS].[created_by]`, 
                `[INV_OPEN_REQUESTS].[approver]`,
                `[INV_OPEN_REQUESTS].[status]`,
                `[INV_OPEN_REQUESTS].[mail_status]`,
                `[INV_OPEN_REQUESTS].[message_sequence_id]`,
                IFF(`[INV_OPEN_REQUEST_EVENTS].[created_at]` < DATEADD(MINUTE, -30, GETDATE()), 1, 0) AS `is_30_min_passed`,
                IFF(`[INV_OPEN_REQUEST_EVENTS].[created_at]` < DATEADD(MINUTE, -15, GETDATE()), 1, 0) AS `is_15_min_passed`
            FROM `[INV_OPEN_REQUESTS]` 
                INNER JOIN `[INV_OPEN_REQUEST_EVENT] ON `[INV_OPEN_REQUESTS].[id]` = `[INV_OPEN_REQUEST_EVENTS].[request_id]`
                WHERE `[INV_OPEN_REQUESTS].[status]` = :status AND `[INV_OPEN_REQUESTS].[mail_status]` = :mailStatus
                AND `[INV_OPEN_REQUEST_EVENTS].[event]` = :event
            ORDER BY `[INV_OPEN_REQUESTS].[id]` ASC";
        $this->sqlDb->query($sql);
        $this->sqlDb->bind(':status', 'OPENED');
        $this->sqlDb->bind(':mailStatus', 1);
        $this->sqlDb->bind(':event', 'OPENED');
        return $this->sqlDb->resultset();
    }

    
    protected function closeOpenInvoiceRequests($data) {
        $sql = "UPDATE `[INV_OPEN_REQUESTS]` SET `[status]` = :status WHERE `[id]` IN(:id)";
        $this->sqlDb->query($sql);
        foreach ($data as $eventKey => $eventValue) {
            foreach ($eventValue as $requestKey => $requestValue) {
                $this->sqlDb->bind(':status', 'CLOSED');
                $this->sqlDb->bind(':id', $requestValue['request_id']);
                $this->sqlDb->execute();
            }
        }       

    }
}



