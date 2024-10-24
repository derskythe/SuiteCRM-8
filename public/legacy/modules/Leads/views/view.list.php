<?php

require_once('modules/Leads/LeadsListViewSmarty.php');

#[\AllowDynamicProperties]
class LeadsViewList extends ViewList
{
    /**
     * @see ViewList::preDisplay()
     */
    public function preDisplay() : void
    {
        require_once('modules/AOS_PDF_Templates/formLetter.php');
        formLetter::LVPopupHtml('Leads');
        parent::preDisplay();

        $this->lv = new LeadsListViewSmarty();
    }
}
