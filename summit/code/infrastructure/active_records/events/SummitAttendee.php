<?php

/**
 * Copyright 2015 OpenStack Foundation
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 **/
final class SummitAttendee extends DataObject implements ISummitAttendee
{

    private static $db = array
    (
        'SharedContactInfo'       => 'Boolean',
        'SummitHallCheckedIn'     => 'Boolean',
        'SummitHallCheckedInDate' => 'SS_Datetime',
    );

    private static $has_many = array
    (
        'Tickets' => 'SummitAttendeeTicket',
    );

    private static $defaults = array
    (
    );

    private static $many_many = array
    (
        'Schedule'    => 'SummitEvent',
    );

    private static $belongs_to = array
    (

    );

    static $many_many_extraFields = array
    (
        'Schedule' => array
        (
            'IsCheckedIn' => "Boolean",
        ),
    );

    private static $has_one = array
    (
        'Member'     => 'Member',
        'Summit'     => 'Summit',
    );

    private static $summary_fields = array
    (
        "Member.Email"        => 'Member',
        'SummitHallCheckedIn' => "Is Checked In",
        'TicketsCount' => '# Tickets'
    );

    static $indexes = array
    (
        'Summit_Member' =>  array('type'=>'unique', 'value'=>'SummitID,MemberID'),
    );

    private static $searchable_fields = array
    (
    );

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        foreach($this->Tickets() as $t)
            $t->delete();

        $schedule = $this->getManyManyComponents('Schedule');
        foreach( $schedule as $s)
        {
            $schedule->remove($s);
        }
    }

    public function TicketsCount()
    {
        return (int)$this->Tickets()->count();
    }

    /**
     * @return int
     */
    public function getIdentifier()
    {
        return (int)$this->getField('ID');
    }

    /**
     * @return ICommunityMember
     */
    public function getMember()
    {
        return AssociationFactory::getInstance()->getMany2OneAssociation($this, 'Member')->getTarget();
    }

    /**
     * @return ISummit
     */
    public function getSummit()
    {
        return AssociationFactory::getInstance()->getMany2OneAssociation($this, 'Summit')->getTarget();
    }

    /**
     * @return DateTime
     */
    public function getTicketBoughtDate()
    {
        return $this->getField('TicketBoughtDate');
    }

    /**
     * @return bool
     */
    public function allowSharedContactInfo()
    {
        return $this->getField('SharedContactInfo');
    }

    /**
     * @return bool
     */
    public function isSummitHallCheckedIn()
    {
        return $this->getField('SummitHallCheckedIn');
    }

    /**
     * @return ISummitEvent[]
     */
    public function getSchedule()
    {
       return AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->toArray();
    }

    /**
     * @return bool
     */
    public function isScheduled($event_id)
    {
        $query = new QueryObject($this);
        $query->addAndCondition(QueryCriteria::equal('SummitEvent.ID',$event_id));
        $events = AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule', $query)->toArray();

        return (count($events) > 0);
    }

    /**
     * @param ISummitEvent $summit_event
     * @return void
     */
    public function addToSchedule(ISummitEvent $summit_event)
    {
        AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->add
        (
            $summit_event,
            array('IsCheckedIn'=> false)
        );
    }

    /**
     * @param ISummitEvent $summit_event
     * @return void
     */
    public function removeFromSchedule(ISummitEvent $summit_event)
    {
        AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->remove($summit_event);
    }

    /**
     * @return void
     */
    public function clearSchedule()
    {
        AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->removeAll();
    }

    /**
     * @return void
     */
    public function registerSummitHallChecking()
    {
       if($this->SummitHallCheckedIn) return;

       $this->SummitHallCheckedIn      = true;
       $this->SummitHallCheckedInDate =  MySQLDatabase56::nowRfc2822();
    }

    public function registerCheckInOnEvent(ISummitEvent $summit_event)
    {
        AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->remove($summit_event);
        AssociationFactory::getInstance()->getMany2ManyAssociation($this, 'Schedule')->add
        (
            $summit_event,
            array('IsCheckedIn'=> true)
        );
    }

    public function getCMSFields()
    {

        $f = new FieldList
        (
            $rootTab = new TabSet("Root", $tabMain = new Tab('Main'))
        );

        $f->addFieldToTab('Root.Main', new HiddenField('SummitID','SummitID'));
        $f->addFieldsToTab('Root.Main', new CheckboxField('SharedContactInfo', 'Allow Shared Contact Info?'));
        $f->addFieldsToTab('Root.Main', new CheckboxField('SummitHallCheckedIn', 'Is SummitHall checked In?'));
        $f->addFieldsToTab('Root.Main', $checked_in_date = new DatetimeField('SummitHallCheckedInDate', 'SummitHall checked In Date'));
        $checked_in_date->getDateField()->setConfig('showcalendar', true);
        $f->addFieldsToTab('Root.Main', new MemberAutoCompleteField('Member', 'Member'));

        if($this->ID > 0)
        {
            // schedule
            $config = GridFieldConfig_RelationEditor::create(50);
            $config->removeComponentsByType('GridFieldAddNewButton');
            $config->getComponentByType('GridFieldAddExistingAutocompleter')->setSearchList($this->getAllowedSchedule());
            $config->addComponent(new GridFieldAjaxRefresh(1000,false));
            $detailFormFields = new FieldList();
            $detailFormFields->push(new CheckBoxField(
                'ManyMany[IsCheckedIn]',
                'Is Checked In?'
            ));
            $config->getComponentByType('GridFieldDetailForm')->setFields($detailFormFields);
            $gridField = new GridField('Schedule', 'Schedule', $this->Schedule(), $config);
            $f->addFieldToTab('Root.Schedule', $gridField);

            //tickets

            $config = GridFieldConfig_RecordEditor::create(10);
            $gridField = new GridField('Tickets', 'Tickets', $this->Tickets(), $config);
            $f->addFieldToTab('Root.Tickets', $gridField);
        }
        return $f;
    }

    public function getAllowedSchedule()
    {
        $summit = $this->Summit();
        if(is_null($summit)) $summit = Summit::get()->byID(intval($_REQUEST['SummitID']));
        return SummitEvent::get()->filter(array('Published'=> true, 'SummitID' => $summit->ID));
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null) {
        return Permission::check("ADMIN") || Permission::check("ADMIN_SUMMIT_APP") || Permission::check("ADMIN_SUMMIT_APP_SCHEDULE");
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canEdit($member = null) {
        return Permission::check("ADMIN") || Permission::check("ADMIN_SUMMIT_APP") || Permission::check("ADMIN_SUMMIT_APP_SCHEDULE");
    }


    /**
     * @param ISummitAttendeeTicket $ticket
     * @return bool
     */
    public function hasTicket(ISummitAttendeeTicket $ticket)
    {
        $query   = new QueryObject($this);
        $tickets = AssociationFactory::getInstance()->getOne2ManyAssociation($this, 'Tickets', $query)->toArray();
        foreach($tickets as $t)
        {
            if(
                intval($t->ExternalOrderId)    === intval($ticket->ExternalOrderId) &&
                intval($t->ExternalAttendeeId) === intval($ticket->ExternalAttendeeId) &&
                $t->TicketType()->exists() &&
                $ticket->TicketType()->exists() &&
                intval($t->TicketType()->ExternalId) === intval($ticket->TicketType()->ExternalId)
            )
            return true;
        }
        return false;
    }

    /**
     * @return ISummitAttendeeTicket[]
     * @throws Exception
     */
    public function getTickets()
    {
        $query   = new QueryObject($this);
        return AssociationFactory::getInstance()->getOne2ManyAssociation($this, 'Tickets', $query)->toArray();
    }

    /**
     * @param ISummitAttendeeTicket $ticket
     * @return $this
     */
    public function addTicket(ISummitAttendeeTicket $ticket)
    {
        $query = new QueryObject($this);
        AssociationFactory::getInstance()->getOne2ManyAssociation($this, 'Tickets', $query)->add($ticket);
        return $this;
    }

    /**
     * @param bool $must_share
     * @return $this
     */
    public function setShareContactInfo($must_share)
    {
        $this->setField('SharedContactInfo', $must_share);
        return $this;
    }
}