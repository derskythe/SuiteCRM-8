<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}


#[\AllowDynamicProperties]
class FP_eventsViewDetail extends ViewDetail
{
    public $currSymbol;
    public function __construct()
    {
        parent::__construct();
    }




    public function display() : void
    {
        $this->bean->email_templates();
        parent::display();
    }
}
