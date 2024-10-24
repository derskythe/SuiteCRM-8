<?php

require_once('modules/Cases/CasesListViewSmarty.php');

#[\AllowDynamicProperties]
class CasesViewList extends ViewList
{
    public function __construct()
    {
        parent::__construct();
    }




    public function preDisplay() : void
    {
        $this->lv = new CasesListViewSmarty();
    }
}
