<?php
/**
 * Copyright 2014 Openstack Foundation
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

/**
 * Class SangriaPageDeploymentExtension
 */
final class SangriaPageDeploymentExtension extends Extension
{

    public function onBeforeInit()
    {
        Config::inst()->update(get_class($this), 'allowed_actions', array(
            'ViewDeploymentStatistics',
            'ViewDeploymentStatisticsSurveyBuilder',
            'ViewDeploymentSurveyStatistics',
            'ViewSurveysStatisticsSurveyBuilder',
            'ViewDeploymentDetails',
            'DeploymentDetails',
            'SurveyDetails',
            'AddNewDeployment',
            'AddUserStory',
            'ViewDeploymentsPerRegion',
            'ViewDeploymentSurveysPerRegion',
            'ViewUsersPerRegion',
            'exportUsersPerRegion'
        ));

        Config::inst()->update(get_class($this->owner), 'allowed_actions', array(
            'ViewDeploymentStatistics',
            'ViewDeploymentStatisticsSurveyBuilder',
            'ViewDeploymentSurveyStatistics',
            'ViewSurveysStatisticsSurveyBuilder',
            'ViewDeploymentDetails',
            'DeploymentDetails',
            'SurveyDetails',
            'AddNewDeployment',
            'AddUserStory',
            'ViewDeploymentsPerRegion',
            'ViewDeploymentSurveysPerRegion',
            'ViewUsersPerRegion',
            'exportUsersPerRegion'
        ));
    }


    function onBeforeIndex($controller)
    {
        Session::clear("ViewDeploymentSurveyStatistics_survey_range");
        Session::clear("ViewDeploymentStatistics_survey_range");
        Session::clear("ViewDeploymentsPerRegion_survey_range");
        Session::clear("global_survey_range");
    }

    function DeploymentDetails()
    {
        $params = $this->owner->request->allParams();
        $deployment_id = intval(Convert::raw2sql($params["ID"]));

        $range = Session::get("global_survey_range");
        //get survey version
        if (!empty($range) && $range === SurveyType::FALL_2015) {
            $deployment = Survey::get()->byID($deployment_id);
            if ($deployment->ClassName === 'EntitySurvey') {
                $deployment = EntitySurvey::get()->byID($deployment_id);
            }
        } else {
            $deployment = Deployment::get()->byID($deployment_id);
        }

        if ($deployment) {
            $back_url = $this->owner->request->getVar('BackUrl');
            if (empty($back_url)) {
                $back_url = $this->owner->Link("ViewDeploymentDetails");
            }
            if ($deployment instanceof Survey) {
                $details_template = 'SangriaPage_SurveyBuilderSurveyDetails';
                $data = array
                (
                    "Survey" => $deployment,
                    "BackUrl" => $back_url
                );
            } else {
                $details_template = $deployment->getSurveyType() == SurveyType::OLD ? "SangriaPage_DeploymentDetailsOld" : "SangriaPage_DeploymentDetails";
                $data = array
                (
                    "Deployment" => $deployment,
                    "BackUrl" => $back_url
                );
            }

            return $this->owner->Customise
            (
                $data
            )->renderWith(array($details_template, 'SangriaPage', 'SangriaPage'));
        }

        return $this->owner->httpError(404, 'Sorry that Deployment could not be found!.');
    }

    function SurveyDetails()
    {
        $params = $this->owner->request->allParams();
        $deployment_id = intval(Convert::raw2sql($params["ID"]));;
        $range = Session::get("global_survey_range");
        //get survey version
        if (!empty($range) && $range === SurveyType::FALL_2015) {
            $survey = Survey::get()->byID($deployment_id);
            if ($survey->ClassName === 'EntitySurvey') {
                $survey = EntitySurvey::get()->byID($deployment_id);
            }
        } else {
            $survey = DeploymentSurvey::get()->byID($deployment_id);
        }

        if ($survey) {
            $back_url = $this->owner->request->getVar('BackUrl');
            if ($survey instanceof Survey) {
                $details_template = 'SangriaPage_SurveyBuilderSurveyDetails';
                $data = array
                (
                    "Survey" => $survey,
                    "BackUrl" => $back_url
                );
            } else {
                $details_template = $survey->getSurveyType() == SurveyType::OLD ? "SangriaPage_SurveyDetailsOld" : "SangriaPage_SurveyDetails";
                $data = array
                (
                    "Survey" => $survey,
                    "BackUrl" => $back_url
                );
            }
            if (empty($back_url)) {
                $back_url = "#";
            }

            return $this->owner->Customise
            (
                $data
            )->renderWith(array($details_template, 'SangriaPage', 'SangriaPage'));
        }

        return $this->owner->httpError(404, 'Sorry that Survey could not be found!.');
    }

    // Deployment Survey data

    public function ViewDeploymentSurveyStatistics()
    {

        $range = self::getSurveyRange('ViewDeploymentSurveyStatistics');
        if ($range === SurveyType::FALL_2015) {
            return Controller::curr()->redirect(Controller::curr()->Link("ViewSurveysStatisticsSurveyBuilder"));
        }
        SangriaPage_Controller::generateDateFilters('DS');
        Requirements::css("themes/openstack/javascript/datetimepicker/jquery.datetimepicker.css");
        Requirements::javascript("themes/openstack/javascript/datetimepicker/jquery.datetimepicker.js");
        Requirements::css("themes/openstack/css/deployment.survey.page.css");
        Requirements::javascript("themes/openstack/javascript/deployment.survey.filters.js");
        Requirements::javascript("themes/openstack/javascript/sangria/sangria.page.viewdeploymentsurveystatistics.js");

        return $this->owner->Customise(array())->renderWith(array(
            'SangriaPage_ViewDeploymentSurveyStatistics',
            'SangriaPage',
            'SangriaPage'
        ));
    }

    private static function boolval($var)
    {
        if (!is_string($var)) {
            return (bool)$var;
        }
        switch (strtolower($var)) {
            case '1':
            case 'true':
            case 'on':
            case 'yes':
            case 'y':
                return true;
            default:
                return false;
        }
    }


    public static function getSurveyRange($page)
    {
        $params = Controller::curr()->getRequest()->postVars();
        if (isset($params["survey_range"])) {
            $range = Convert::raw2sql($params["survey_range"]);
            Session::set($page . "_survey_range", $range);
            Session::set("global_survey_range", $range);

            return $range;
        }
        $range = Session::get($page . "_survey_range");

        return is_null($range) ? SurveyType::OLD : $range;
    }


    private static function generateDeploymentSurveysSummaryOptions($options, $field)
    {
        $list = new ArrayList();
        $range = self::getSurveyRange('ViewDeploymentSurveyStatistics');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        foreach ($options as $option => $label) {
            $count = DB::query("SELECT COUNT(*) FROM DeploymentSurvey DS INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry WHERE {$field} LIKE '%" . $option . "%' AND " . SangriaPage_Controller::$date_filter_query . $range_filter)->value();
            $do = new DataObject();
            $do->Value = $label;
            $do->Count = $count;
            $list->push($do);
        }

        return $list;
    }

    private static function DeploymentSurveyValues($field)
    {
        $range = self::getSurveyRange('ViewDeploymentSurveyStatistics');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        $query = "SELECT DISTINCT  DS.{$field} FROM DeploymentSurvey DS INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry WHERE DS.{$field} IS NOT NULL AND " . SangriaPage_Controller::$date_filter_query . $range_filter . " ORDER BY DS.{$field}";

        $rows = DB::query($query);
        $list = new ArrayList();

        foreach ($rows as $row) {
            $list->push(new DeploymentSurvey($row));
        }

        return $list;
    }

    function IndustrySummary()
    {
        return self::generateDeploymentSurveysSummaryOptions(DeploymentSurveyOptions::$industry_options, 'DS.Industry');
    }

    function OtherIndustry()
    {
        return self::DeploymentSurveyValues('OtherIndustry');
    }

    function OrganizationSizeSummary()
    {
        return self::generateDeploymentSurveysSummaryOptions(DeploymentSurveyOptions::$organization_size_options,
            'DS.OrgSize');
    }

    function InvolvementSummary()
    {
        return self::generateDeploymentSurveysSummaryOptions(DeploymentSurveyOptions::$openstack_involvement_options,
            'DS.OpenStackInvolvement');
    }

    function InformationSourcesSummary()
    {
        return self::generateDeploymentSurveysSummaryOptions(DeploymentSurveyOptions::$information_options,
            'DS.InformationSources');
    }

    function BusinessDriversSummary()
    {
        return self::generateDeploymentSurveysSummaryOptions(DeploymentSurveyOptions::$business_drivers_options,
            'DS.BusinessDrivers');
    }

    function OtherInformationSources()
    {
        return self::DeploymentSurveyValues('OtherInformationSources');
    }

    function FurtherEnhancement()
    {
        return self::DeploymentSurveyValues('FurtherEnhancement');
    }

    function FoundationUserCommitteePriorities()
    {
        return self::DeploymentSurveyValues('FoundationUserCommitteePriorities');
    }

    function OtherBusinessDrivers()
    {
        return self::DeploymentSurveyValues('OtherBusinessDrivers');
    }

    function WhatDoYouLikeMost()
    {
        return self::DeploymentSurveyValues('WhatDoYouLikeMost');
    }

    // Deployment Data

    function ViewDeploymentStatistics()
    {
        $range = self::getSurveyRange('ViewDeploymentStatistics');
        if ($range === SurveyType::FALL_2015) {
            return Controller::curr()->redirect(Controller::curr()->Link("ViewDeploymentStatisticsSurveyBuilder"));
        }
        SangriaPage_Controller::generateDateFilters('D');
        Requirements::css("themes/openstack/javascript/datetimepicker/jquery.datetimepicker.css");
        Requirements::javascript("themes/openstack/javascript/datetimepicker/jquery.datetimepicker.js");
        Requirements::css("themes/openstack/css/deployment.survey.page.css");
        Requirements::javascript("themes/openstack/javascript/deployment.survey.filters.js");
        Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentstatistics.js');

        return $this->owner->Customise(array())->renderWith(array(
            'SangriaPage_ViewDeploymentStatistics',
            'SangriaPage',
            'SangriaPage'
        ));
    }


    function IsPublicSummary()
    {
        $options = array(0 => "No", 1 => "Yes");

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.IsPublic", $options, true);
    }

    public static function generateSelectListSummary($fieldName, $optionSet, $applyDateFilters = false)
    {
        $list = new ArrayList();

        $urlString = isset($_SERVER["REDIRECT_URL"]) ? $_SERVER["REDIRECT_URL"] : Controller::curr()->Link('ViewDeploymentStatistics') . "?";
        $keyUrlString = "";
        $keyValue = "";

        foreach ($_GET as $key => $value) {
            if (preg_match("/Filter$/", $key)) {
                if ($key != $fieldName . "Filter") {
                    $urlString .= $key . "=" . $value . "&";
                } else {
                    $keyUrlString = $key . "=" . $value;
                    $keyValue = $value;
                }
            }
        }

        $range = self::getSurveyRange('ViewDeploymentsPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND D.Created < '" . SURVEY_START_DATE . "'";
        }

        foreach ($optionSet as $option => $label) {

            $query = "SELECT COUNT(*) FROM Deployment D INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry WHERE " . $fieldName . " LIKE '%" . $option . "%'" . SangriaPage_Controller::generateFilterWhereClause();
            $query .= ($applyDateFilters) ? ' AND ' . SangriaPage_Controller::$date_filter_query : '';
            $query .= $range_filter;
            $count = DB::query($query)->value();
            $do = new DataObject();

            $href = $urlString . $fieldName . "Filter=" . $option;

            if ($applyDateFilters) {
                $start_date = Controller::curr()->request->getVar('From');
                $end_date = Controller::curr()->request->getVar('To');
                if ($start_date && $end_date) {
                    $href .= "&From=" . $start_date . "&To=" . $end_date;
                }
            }

            $do->Value = "<a href='" . $href . "'>" . $label . "</a>";
            if (!empty($keyUrlString) && $keyValue != $option) {
                $do->Value .= " (<a href='" . $urlString . $keyUrlString . ",," . $option . "'>+</a>) (<a href='" . $urlString . $keyUrlString . "||" . $option . "'>|</a>)";
            }
            $do->Count = $count;
            $list->push($do);
        }

        return $list;
    }

    function DeploymentTypeSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.DeploymentType",
            $class::$deployment_type_options, true);
    }

    function ProjectsUsedSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ProjectsUsed",
            $class::$projects_used_options, true);
    }

    function CurrentReleasesSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.CurrentReleases",
            $class::$current_release_options, true);
    }

    function APIFormatsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.APIFormats", $class::$api_options, true);
    }

    function DeploymentStageSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.DeploymentStage", $class::$stage_options,
            true);
    }

    function HypervisorsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.Hypervisors", $class::$hypervisors_options,
            true);
    }

    function IdentityDriversSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.IdentityDrivers",
            $class::$identity_driver_options, true);
    }

    function SupportedFeaturesSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.SupportedFeatures",
            $class::$deployment_features_options, true);
    }

    function NetworkDriversSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.NetworkDrivers",
            $class::$network_driver_options, true);
    }

    function NetworkNumIPsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.NetworkNumIPs", $class::$network_ip_options,
            true);
    }

    function BlockStorageDriversSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.BlockStorageDrivers",
            $class::$block_storage_divers_options, true);
    }

    function ComputeNodesSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ComputeNodes",
            $class::$compute_nodes_options, true);
    }

    function ComputeCoresSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ComputeCores",
            $class::$compute_cores_options, true);
    }

    function ComputeInstancesSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ComputeInstances",
            $class::$compute_instances_options, true);
    }

    function BlockStorageTotalSizeSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.BlockStorageTotalSize",
            $class::$storage_size_options, true);
    }

    function ObjectStorageSizeSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ObjectStorageSize",
            $class::$storage_size_options, true);
    }

// Deployment Details

    function ObjectStorageNumObjectsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.ObjectStorageNumObjects",
            $class::$storage_objects_options, true);
    }

    function ViewDeploymentDetails()
    {
        Requirements::javascript(Director::protocol() . "ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js");
        Requirements::javascript(Director::protocol() . "ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/additional-methods.min.js");
        Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
        Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery-ui.js');
        Requirements::javascript("themes/openstack/javascript/jquery.ui.datepicker.validation.package-1.0.1/jquery.ui.datepicker.validation.js");
        Requirements::javascript("themes/openstack/javascript/jquery.validate.custom.methods.js");
        Requirements::javascript("themes/openstack/javascript/sangria/view.deployment.details.js");

        return $this->owner->getViewer('ViewDeploymentDetails')->process($this->owner);
    }

    function Deployments()
    {

        $sort = $this->owner->request->getVar('sort');
        $sort_dir = $this->owner->getSortDir('deployments');
        $date_from = Convert::raw2sql(trim($this->owner->request->getVar('date-from')));
        $date_to = Convert::raw2sql(trim($this->owner->request->getVar('date-to')));
        $free_text = Convert::raw2sql(trim($this->owner->request->getVar('free-text')));

        $range = self::getSurveyRange('ViewDeploymentDetails');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "AND Deployment.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "AND Deployment.Created < '" . SURVEY_START_DATE . "'";
        }

        $sort_query = '';
        if (!empty($sort)) {
            switch (strtolower(trim($sort))) {
                case 'date': {
                    $sort_query = "Deployment.UpdateDate";
                    $sort_dir = strtoupper($sort_dir);
                }
                    break;
                default: {
                    $sort_query = "ID";
                    $sort_dir = 'DESC';
                }
                    break;
            }
        }

        $where_query = "IsPublic = 1 " . $range_filter;
        $res = Deployment::get();
        if (!empty($date_from) && !empty($date_to)) {
            $start = new \DateTime($date_from);
            $start->setTime(00, 00, 00);
            $end = new \DateTime($date_to);
            $end->setTime(23, 59, 59);
            $where_query .= " AND ( Deployment.UpdateDate >= '{$start->format('Y-m-d H:i:s')}' AND Deployment.UpdateDate <= '{$end->format('Y-m-d H:i:s')}')";
        }

        if (!empty($free_text)) {
            $where_query .= " AND ( Org.Name LIKE '%{$free_text}%' OR Label LIKE '%{$free_text}%' ) ";
            $res = $res->innerJoin('Org', 'Org.ID = Deployment.OrgID');
        }

        $res = $res->where($where_query);
        if (!empty($sort_query) && !empty($sort_dir)) {
            $res->sort($sort_query, $sort_dir);
        }

        return $res;
    }

    // Add User Story from Deployment

    public function getCountriesDDL()
    {
        $ddl = new CountryDropdownField('country', 'country');
        $ddl->setEmptyString('-- select a country --');
        $ddl->addExtraClass('add-control');
        $ddl->addExtraClass('countries-ddl');

        return $ddl;
    }

    function DeploymentsSurvey()
    {

        $range_filter = "AND DeploymentSurvey.Created >= '" . SURVEY_START_DATE . "'";

        $sqlQuery = new SQLQuery();
        $sqlQuery->setSelect(array('DISTINCT DeploymentSurvey.*'));
        $sqlQuery->setFrom(array("DeploymentSurvey, Deployment, Org"));
        $sqlQuery->setWhere(array(
            "Deployment.DeploymentSurveyID = DeploymentSurvey.ID
                            AND Deployment.IsPublic = 1
                            AND Org.ID = DeploymentSurvey.OrgID
                            AND DeploymentSurvey.Title IS NOT NULL
                            " . $range_filter
        ));

        $sqlQuery->setOrderBy('Org.Name');

        $result = $sqlQuery->execute();

        $arrayList = new ArrayList();

        foreach ($result as $rowArray) {
            // concept: new Product($rowArray)
            $arrayList->push(new $rowArray['ClassName']($rowArray));
        }

        return $arrayList;
    }

    function AddUserStory()
    {

        if (isset($_GET['ID']) && is_numeric($_GET['ID'])) {
            $ID = $_GET['ID'];
        } else {
            die();
        }

        $parent = UserStoryHolder::get()->first();
        if (!$parent) {
            $this->owner->setMessage('Error',
                'could not add an user story bc there is not any available parent page(UserStoryHolder).');
            Controller::curr()->redirectBack();
        }
        $userStory = new UserStory;
        $userStory->Title = $_GET['label'];
        $userStory->DeploymentID = $ID;
        $userStory->UserStoriesIndustryID = $_GET['industry'];
        $userStory->CompanyName = $_GET['org'];
        $userStory->CaseStudyTitle = $_GET['org'];
        $userStory->ShowInAdmin = 1;
        $userStory->setParent($parent); // Should set the ID once the Holder is created...
        $userStory->write();
        //$userStory->publish("Live", "Stage");
        $userStory->flushCache();
        $this->owner->setMessage('Success', '<b>' . $userStory->Title . '</b> added as User Story.');

        Controller::curr()->redirectBack();
    }

    function AddNewDeployment()
    {

        $survey = DataObject::get_one('DeploymentSurvey', 'ID = ' . $_POST['survey']);

        $deployment = new Deployment;

        $deployment->Label = $_POST['label'];
        $deployment->DeploymentType = $_POST['type'];
        $deployment->CountriesPhysicalLocation = $_POST['country'];
        $deployment->CountriesUsersLocation = $_POST['country'];

        if ($survey) {
            $deployment->OrgID = $survey->OrgID;
        } else {
            $deployment->OrgID = 0;
        }
        $deployment->IsPublic = 1;
        $deployment->write();
        if ($survey) {
            $survey->Deployments()->add($deployment);
        }
        $this->owner->setMessage('Success', '<b>' . $_POST['label'] . '</b> added as a new Deployment.');

        Controller::curr()->redirectBack();
    }

    function WorkloadsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.WorkloadsDescription",
            $class::$workloads_description_options, true);
    }

    function DeploymentToolsSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.DeploymentTools",
            $class::$deployment_tools_options, true);
    }

    function OperatingSystemSummary()
    {
        $class = self::getSurveyRange('SangriaPage_ViewDeploymentStatistics') == SurveyType::MARCH_2015 ? 'DeploymentOptions' : 'DeploymentArchiveOptions';

        return SangriaPageDeploymentExtension::generateSelectListSummary("D.OperatingSystems",
            $class::$operating_systems_options, true);
    }

    // deployment per regions

    function WhyNovaNetwork()
    {
        $filterWhereClause = SangriaPage_Controller::generateFilterWhereClause();

        $range = self::getSurveyRange('ViewDeploymentsPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "AND D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "AND D.Created < '" . SURVEY_START_DATE . "'";
        }


        $date_filter = ' AND ' . SangriaPage_Controller::$date_filter_query;

        $query = "SELECT DISTINCT D.WhyNovaNetwork FROM Deployment D INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID WHERE D.WhyNovaNetwork IS NOT NULL " . $filterWhereClause . $date_filter . $range_filter . " ORDER BY D.WhyNovaNetwork";

        $list = new ArrayList();
        foreach (DB::query($query) as $row) {
            $list->push(new Deployment($row));
        }

        return $list;
    }

    function ViewDeploymentsPerRegion()
    {

        $continent = intval(Convert::raw2sql(Controller::curr()->request->getVar('continent')));
        $country = Convert::raw2sql(Controller::curr()->request->getVar('country'));
        Requirements::javascript(Director::protocol() . "maps.googleapis.com/maps/api/js?sensor=false");
        Requirements::javascript("marketplace/code/ui/admin/js/utils.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/markerclusterer.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/oms.min.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/infobubble-compiled.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/google.maps.jquery.js");

        if (!empty($continent)) {
            $continent_name = DB::query("SELECT Name from Continent where ID = {$continent}")->value();
            $result = array(
                'continent' => $continent,
                'continent_name' => $continent_name
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentscontinent.js');

            return $this->owner->getViewer('ViewDeploymentsPerContinent')->process($this->owner->customise($result));
        }

        if (!empty($country)) {
            $range = self::getSurveyRange('ViewDeploymentsPerRegion');

            if ($range == SurveyType::MARCH_2015) {
                $range_filter = "AND D.Created >= '" . SURVEY_START_DATE . "'";
            } else {
                $range_filter = "AND D.Created < '" . SURVEY_START_DATE . "'";
            }

            $continent = DB::query("SELECT ContinentID from Continent_Countries where CountryCode = '{$country}';")->value();

            $old_query = <<<SQL
            SELECT COUNT(*) FROM Deployment D INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID WHERE DS.PrimaryCountry = '{$country}' {$range_filter};
SQL;

            $new_query = <<<SQL
            SELECT COUNT(EntityID)
FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.CountryCode = '{$country}';
SQL;


            $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
            $count = DB::query($query)->value();
            $result = array
            (
                'country' => $country,
                'country_name' => CountryCodes::$iso_3166_countryCodes[$country],
                'continent' => $continent,
                'count' => $count
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentscountry.js');

            return $this->owner->getViewer('ViewDeploymentsPerCountry')->process($this->owner->customise($result));
        }

        Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentsregion.js');

        return $this->owner->getViewer('ViewDeploymentsPerRegion')->process($this->owner);
    }

    function LoadJsonCountriesCoordinates($action = 'ViewDeploymentsPerRegion')
    {

        $doc = new DOMDocument;

        // We don't want to bother with white spaces
        $doc->preserveWhiteSpace = false;
        $dir = dirname(__FILE__);
        $doc->Load($dir . '/data/countries.xml');

        $xpath = new DOMXPath($doc);

        // We starts from the root element
        $query = "//country";

        $entries = $xpath->query($query);

        $json_data = 'var countries_data = [];';
        foreach ($entries as $entry) {
            $code = $entry->attributes->item(2)->nodeValue;
            $lat_lng = $entry->attributes->item(14)->nodeValue;
            $lat_lng = @explode(',', $lat_lng);
            if (count($lat_lng) != 2) {
                continue;
            }
            $lat = $lat_lng[0];
            $lng = $lat_lng[1];
            $link = $this->owner->Link($action) . '?country=' . $code;
            $json_data .= "countries_data[\"" . $code . "\"] = { lat: " . $lat . " , lng :" . $lng . ", url: '" . $link . "'};";
        }

        return $json_data;
    }

    function DeploymentsPerContinent()
    {
        $list = new ArrayList();

        $range = self::getSurveyRange('ViewDeploymentsPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "WHERE D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "WHERE D.Created < '" . SURVEY_START_DATE . "'";
        }


        $old_query = <<<SQL
SELECT COUNT(D.ID) AS DeploymentsQty, C.ID AS ContinentID, C.Name AS Continent FROM Deployment D
INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID
INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry
INNER JOIN Continent C ON C.ID = CC.ContinentID {$range_filter}
GROUP BY C.Name, C.ID;
SQL;

        $new_query = <<<SQL
SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) AS DeploymentsQty , C.ID AS ContinentID, C.Name AS Continent FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
INNER JOIN Continent C ON C.ID = CC.ContinentID
GROUP BY C.Name, C.ID;
SQL;

        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;

        $records = DB::query($query);

        foreach ($records as $record) {
            $count = $record['DeploymentsQty'];
            $continent = $record['Continent'];
            $continent_id = $record['ContinentID'];
            $do = new DataObject();
            $do->count = $count;
            $do->continent = $continent;
            $do->continent_id = $continent_id;
            $list->push($do);
        }

        return $list;
    }

    function DeploymentsPerCountry($country)
    {
        $list = new ArrayList();
        $range = self::getSurveyRange('ViewDeploymentsPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "AND D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "AND D.Created < '" . SURVEY_START_DATE . "'";
        }

        $old_query = <<<SQL
SELECT D.* from Deployment D INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID WHERE DS.PrimaryCountry = '{$country}' {$range_filter};
SQL;

        $new_query = <<<SQL
SELECT EntityID AS ID, 'EntitySurvey' AS ClassName, CC.CountryCode AS Country
FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.CountryCode = '{$country}';
SQL;

        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;

        $res = DB::query($query);

        foreach ($res as $row) {
            // concept: new Deployment($deployment)
            $entity = new $row['ClassName']($row);
            $list->push(
                new ArrayData
                (
                    array
                    (
                        'ID' => $entity->ID,
                        'Country' => $entity->ClassName === 'Deployment' ? $entity->Country : $row['Country'],
                        'Label' => $entity->ClassName === 'Deployment' ? sprintf("%s - %s", $entity->Label,
                            $entity->DeploymentType) : $entity->getFriendlyName(),
                    )
                )
            );
        }

        return $list;
    }

    function DeploymentsPerContinentCountry($continent_id)
    {

        $list = new ArrayList();
        $range = self::getSurveyRange('ViewDeploymentsPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "AND D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "AND D.Created < '" . SURVEY_START_DATE . "'";
        }

        $old_query = <<<SQL
SELECT COUNT(D.ID) AS Qty, DS.PrimaryCountry FROM Deployment D
INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID
WHERE PrimaryCountry
IN (SELECT CountryCode from Continent_Countries where ContinentID = {$continent_id}) {$range_filter} group BY PrimaryCountry;
SQL;

        $new_query = <<<SQL
SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) AS Qty, CC.CountryCode AS PrimaryCountry FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.ContinentID = {$continent_id}
GROUP BY CC.CountryCode ;
SQL;

        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;

        $countries = DB::query($query);
        foreach ($countries as $country) {
            $count = $country['Qty'];
            $country = $country['PrimaryCountry'];
            if (isset(CountryCodes::$iso_3166_countryCodes[$country])) {
                $do = new DataObject();
                $do->count = $count;
                $do->country = $country;
                $do->country_name = CountryCodes::$iso_3166_countryCodes[$country];
                $list->push($do);
            }
        }

        return $list;
    }

    function CountriesWithDeployments($continent_id)
    {
        $list = new ArrayList();
        $range = self::getSurveyRange('ViewDeploymentsPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "AND D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "AND D.Created < '" . SURVEY_START_DATE . "'";
        }
        $old_query = <<<SQL
SELECT  CC.CountryCode, COUNT(CC.CountryCode) AS Qty from Continent_Countries CC INNER JOIN DeploymentSurvey DS ON DS.PrimaryCountry = CC.CountryCode
INNER JOIN  Deployment D ON DS.ID = D.DeploymentSurveyID
WHERE CC.ContinentID =  {$continent_id} {$range_filter} GROUP BY CC.CountryCode;
SQL;

        $new_query = <<<SQL
SELECT CC.CountryCode, COUNT(CC.CountryCode) AS Qty
FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.ContinentID = {$continent_id}
GROUP BY CC.CountryCode
SQL;

        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
        $countries = DB::query($query);

        foreach ($countries as $country) {
            // concept: new Deployment($deployment)
            $do = new DataObject();
            $do->country = $country['CountryCode'];
            $do->country_name = CountryCodes::$iso_3166_countryCodes[$do->country];
            $do->count = $country['Qty'];
            $list->push($do);
        }

        return $list;
    }

    public function GetLinkForDeploymentsPerCountry($country)
    {
        return $this->owner->Link('ViewDeploymentsPerRegion') . '?country=' . $country;
    }

    public function GetLinkForDeploymentsPerContinent($continent)
    {
        return $this->owner->Link('ViewDeploymentsPerRegion') . '?continent=' . $continent;
    }

    // deployment surveys per region

    function DeploymentsCount($page, $useDateFilter = true)
    {
        $useDateFilter = self::boolval($useDateFilter);
        $filterWhereClause = SangriaPage_Controller::generateFilterWhereClause();
        $range = self::getSurveyRange($page);
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "WHERE D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "WHERE D.Created < '" . SURVEY_START_DATE . "'";
        }
        $date_filter = '';
        if ($useDateFilter) {
            $date_filter = " AND " . SangriaPage_Controller::$date_filter_query;
        }
        $old_query = "SELECT COUNT(*) from Deployment D INNER JOIN DeploymentSurvey DS ON DS.ID = D.DeploymentSurveyID INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry " . $range_filter . $filterWhereClause . $date_filter;

        $new_query = <<<SQL
      SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN EntitySurveyTemplate EST ON EST.ID = ST.ID
	INNER JOIN EntitySurvey ES ON ES.ID = S.ID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1 AND EST.EntityName = 'Deployment'
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0;
SQL;

        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;

        return DB::query($query)->value();
    }

    function DeploymentSurveysCount($page, $useDateFilter = true)
    {
        $useDateFilter = self::boolval($useDateFilter);
        $range = self::getSurveyRange($page);
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " DS.Created < '" . SURVEY_START_DATE . "'";
        }
        $date_filter = '';
        if ($useDateFilter) {
            $date_filter = " AND " . SangriaPage_Controller::$date_filter_query;
        }

        $old_query = "SELECT COUNT(*) FROM DeploymentSurvey DS INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry WHERE " . $range_filter . $date_filter;

        $new_query = <<<SQL
      SELECT COUNT(SURVEYS_COUNTRIES.SurveyID) FROM
(
	SELECT
    S.ID AS SurveyID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) SURVEYS_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, SURVEYS_COUNTRIES.Countries) > 0;
SQL;
        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;

        return DB::query($query)->value();
    }

    function ViewDeploymentSurveysPerRegion()
    {

        $continent = intval(Convert::raw2sql(Controller::curr()->request->getVar('continent')));
        $country = Convert::raw2sql(Controller::curr()->request->getVar('country'));
        Requirements::javascript(Director::protocol() . "maps.googleapis.com/maps/api/js?sensor=false");
        Requirements::javascript("marketplace/code/ui/admin/js/utils.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/markerclusterer.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/oms.min.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/infobubble-compiled.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/google.maps.jquery.js");

        $range = self::getSurveyRange('ViewDeploymentSurveysPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        if (!empty($continent)) {
            $continent_name = DB::query("SELECT Name from Continent where ID = {$continent}")->value();
            $result = array
            (
                'continent' => $continent,
                'continent_name' => $continent_name
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentscontinent.js');

            return $this->owner->getViewer('ViewDeploymentSurveysPerContinent')->process($this->owner->customise($result));
        }

        if (!empty($country)) {
            $old_query = <<<SQL
            SELECT COUNT(*) FROM DeploymentSurvey DS WHERE DS.PrimaryCountry = '{$country}' {$range_filter};
SQL;

            $new_query = <<<SQL
            SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) DEPLOYMENT_COUNTRIES
WHERE FIND_IN_SET('{$country}', DEPLOYMENT_COUNTRIES.Countries) > 0;
SQL;
            $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;


            $continent = DB::query("SELECT ContinentID from Continent_Countries where CountryCode = '{$country}';")->value();
            $count = DB::query($query)->value();
            $result = array
            (
                'country' => $country,
                'country_name' => CountryCodes::$iso_3166_countryCodes[$country],
                'continent' => $continent,
                'count' => $count
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentscountry.js');

            return $this->owner->getViewer('ViewDeploymentSurveysPerCountry')->process($this->owner->customise($result));
        }

        Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewdeploymentsregion.js');

        return $this->owner->getViewer('ViewDeploymentSurveysPerRegion')->process($this->owner);
    }

    function DeploymentSurveysPerContinent()
    {

        $range = self::getSurveyRange('ViewDeploymentSurveysPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " WHERE DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " WHERE DS.Created < '" . SURVEY_START_DATE . "'";
        }

        $old_query = <<< SQL
      SELECT COUNT(DS.ID) AS DeploymentsQty, C.ID AS ContinentID, C.Name AS Continent FROM DeploymentSurvey DS
INNER JOIN Continent_Countries CC ON CC.CountryCode = DS.PrimaryCountry
INNER JOIN Continent C ON C.ID = CC.ContinentID {$range_filter}
GROUP BY C.Name, C.ID;
SQL;


        $new_query = <<<SQL
    SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) AS DeploymentsQty , C.ID AS ContinentID, C.Name AS Continent  FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
INNER JOIN Continent C ON C.ID = CC.ContinentID
GROUP BY C.Name, C.ID;
SQL;

        $list = new ArrayList();
        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
        $records = DB::query($query);

        foreach ($records as $record) {
            $count = $record['DeploymentsQty'];
            $continent = $record['Continent'];
            $continent_id = $record['ContinentID'];
            $do = new DataObject();
            $do->count = $count;
            $do->continent = $continent;
            $do->continent_id = $continent_id;
            $list->push($do);
        }

        return $list;
    }

    function DeploymentSurveysPerCountry($country)
    {
        $range = self::getSurveyRange('ViewDeploymentSurveysPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        $old_query = <<<SQL
    SELECT DS.* from DeploymentSurvey DS WHERE DS.PrimaryCountry = '{$country}' {$range_filter};
SQL;

        $new_query = <<<SQL
SELECT EntityID AS ID, 'Survey' AS ClassName, CC.CountryCode AS Country
FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.CountryCode = '{$country}';
SQL;

        $list = new ArrayList();
        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
        $res = DB::query($query);
        foreach ($res as $row) {
            // concept: new DeploymentSurvey($deployment)
            $entity = new $row['ClassName']($row);
            $list->push(
                new ArrayData
                (
                    array
                    (
                        'ID' => $entity->ID,
                        'Country' => $entity->ClassName === 'DeploymentSurvey' ? $entity->Country : $row['Country'],
                        'Label' => $entity->ClassName === 'DeploymentSurvey' ? sprintf("%s - %s", $entity->Email,
                            $entity->Industry) : $entity->getFriendlyName(),
                    )
                )
            );
        }

        return $list;
    }

    function DeploymentSurveysPerContinentCountry($continent_id)
    {
        $range = self::getSurveyRange('ViewDeploymentSurveysPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        $old_query = <<<SQL
      SELECT COUNT(DS.ID) AS Qty, DS.PrimaryCountry FROM DeploymentSurvey DS WHERE PrimaryCountry
IN (SELECT CountryCode FROM Continent_Countries WHERE ContinentID = {$continent_id}) {$range_filter} GROUP BY PrimaryCountry;
SQL;

        $new_query = <<< SQL
SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) AS Qty, CC.CountryCode AS PrimaryCountry FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyTemplate ST ON ST.ID = S.TemplateID
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.ContinentID = {$continent_id}
GROUP BY CC.CountryCode ;
SQL;


        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
        $list = new ArrayList();
        $countries = DB::query($query);
        foreach ($countries as $country) {
            $count = $country['Qty'];
            $country = $country['PrimaryCountry'];
            if (isset(CountryCodes::$iso_3166_countryCodes[$country])) {
                $do = new DataObject();
                $do->count = $count;
                $do->country = $country;
                $do->country_name = CountryCodes::$iso_3166_countryCodes[$country];
                $list->push($do);
            }
        }

        return $list;
    }

    public function GetLinkForDeploymentSurveysPerContinent($continent)
    {
        return $this->owner->Link('ViewDeploymentSurveysPerRegion') . '?continent=' . $continent;
    }

    public function GetLinkForDeploymentSurveysPerCountry($country)
    {
        return $this->owner->Link('ViewDeploymentSurveysPerRegion') . '?country=' . $country;
    }

    // user per region

    function CountriesWithDeploymentSurveys($continent_id)
    {
        $range = self::getSurveyRange('ViewDeploymentSurveysPerRegion');
        if ($range == SurveyType::MARCH_2015) {
            $range_filter = " AND DS.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = " AND DS.Created < '" . SURVEY_START_DATE . "'";
        }

        $list = new ArrayList();

        $old_query = <<<SQL
SELECT  CC.CountryCode, COUNT(CC.CountryCode) AS Qty from Continent_Countries CC INNER JOIN DeploymentSurvey DS ON DS.PrimaryCountry = CC.CountryCode
        WHERE CC.ContinentID =  {$continent_id} {$range_filter} GROUP BY CC.CountryCode;
SQL;

        $new_query = <<<SQL
SELECT COUNT(DEPLOYMENT_COUNTRIES.EntityID) AS Qty, CC.CountryCode FROM
(
	SELECT
    S.ID AS EntityID,
    A.Value AS Countries FROM
	Survey S
    INNER JOIN SurveyStep STP ON STP.SurveyID = S.ID
	INNER JOIN SurveyAnswer A ON A.StepID = STP.ID
	INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
	INNER JOIN SurveyDropDownQuestionTemplate DDL ON DDL.ID = Q.ID
	WHERE DDL.IsCountrySelector = 1
) DEPLOYMENT_COUNTRIES
INNER JOIN Continent_Countries CC ON FIND_IN_SET(CC.CountryCode, DEPLOYMENT_COUNTRIES.Countries) > 0
WHERE CC.ContinentID = {$continent_id}
GROUP BY CC.CountryCode ;
SQL;


        $query = $range === SurveyType::FALL_2015 ? $new_query : $old_query;
        $countries = DB::query($query);
        foreach ($countries as $country) {
            // concept: new Deployment($deployment)
            $do = new DataObject();
            $do->country = $country['CountryCode'];
            $do->country_name = CountryCodes::$iso_3166_countryCodes[$do->country];
            $do->count = $country['Qty'];
            $list->push($do);
        }

        return $list;
    }

    function ViewUsersPerRegion()
    {

        $continent = intval(Convert::raw2sql(Controller::curr()->request->getVar('continent')));
        $country = Convert::raw2sql(Controller::curr()->request->getVar('country'));

        Requirements::javascript(Director::protocol() . "maps.googleapis.com/maps/api/js?sensor=false");
        Requirements::javascript("marketplace/code/ui/admin/js/utils.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/markerclusterer.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/oms.min.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/infobubble-compiled.js");
        Requirements::javascript("marketplace/code/ui/frontend/js/google.maps.jquery.js");
        Requirements::javascript(Director::protocol() . "ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js");


        if (!empty($continent)) {
            $continent_name = DB::query("SELECT Name from Continent where ID = {$continent}")->value();
            $result = array(
                'continent' => $continent,
                'continent_name' => $continent_name
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewuserspercontinent.js');

            return $this->owner->getViewer('ViewUsersPerContinent')->process($this->owner->customise($result));
        }
        if (!empty($country)) {
            $continent = DB::query("SELECT ContinentID from Continent_Countries where CountryCode = '{$country}';")->value();
            $count = DB::query("SELECT COUNT(M.ID) FROM Member M WHERE M.Country = '{$country}';")->value();
            $result = array(
                'country' => $country,
                'country_name' => CountryCodes::$iso_3166_countryCodes[$country],
                'continent' => $continent,
                'count' => $count
            );
            Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewuserspercountry.js');

            return $this->owner->getViewer('ViewUsersPerCountry')->process($this->owner->customise($result));
        }

        Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.viewusersperregion.js');

        return $this->owner->getViewer('ViewUsersPerRegion')->process($this->owner);
    }

    function UsersPerContinent()
    {
        $list = new ArrayList();
        $records = DB::query('SELECT COUNT(M.ID) UsersCount, C.Name AS ContinentName, C.ID ContinentID from Member M INNER JOIN Continent_Countries CC ON M.Country = CC.CountryCode
INNER JOIN Continent C ON C.ID = CC.ContinentID
GROUP BY C.Name, C.ID;');
        foreach ($records as $record) {
            $count = $record['UsersCount'];
            $continent = $record['ContinentName'];
            $continent_id = $record['ContinentID'];
            $do = new DataObject();
            $do->count = $count;
            $do->continent = $continent;
            $do->continent_id = $continent_id;
            $list->push($do);
        }

        return $list;
    }

    function UsersPerContinentCountry($continent_id)
    {

        $list = new ArrayList();
        $countries = DB::query("SELECT COUNT(M.ID) UsersCount, CC.CountryCode AS Country from Member M INNER JOIN Continent_Countries CC ON M.Country = CC.CountryCode
INNER JOIN Continent C ON C.ID = CC.ContinentID
WHERE C.ID = {$continent_id}
GROUP BY CC.CountryCode;");
        foreach ($countries as $country) {
            $count = $country['UsersCount'];
            $country = $country['Country'];
            if (isset(CountryCodes::$iso_3166_countryCodes[$country])) {
                $do = new DataObject();
                $do->count = $count;
                $do->country = $country;
                $do->country_name = CountryCodes::$iso_3166_countryCodes[$country];
                $list->push($do);
            }
        }

        return $list;
    }

    function UsersCount()
    {
        return DB::query("SELECT COUNT(M.ID) from Member M INNER JOIN Continent_Countries CC ON M.Country = CC.CountryCode;")->value();
    }

    function CountriesWithUsers($continent_id)
    {
        $list = new ArrayList();

        $countries = DB::query("SELECT  CC.CountryCode, COUNT(CC.CountryCode) AS Qty from Continent_Countries CC INNER JOIN Member M ON M.Country = CC.CountryCode
WHERE CC.ContinentID = {$continent_id} GROUP BY CC.CountryCode; ");

        foreach ($countries as $country) {
            $country_code = $country['CountryCode'];
            if (isset(CountryCodes::$iso_3166_countryCodes[$country_code])) {
                $do = new DataObject();
                $do->country = $country_code;
                $do->country_name = CountryCodes::$iso_3166_countryCodes[$country_code];
                $do->count = $country['Qty'];
                $list->push($do);
            }
        }

        return $list;
    }

    function UserPerCountry($country)
    {
        $cache = SS_Cache::factory('cache_sangria_user_per_country');
        $list = unserialize($cache->load('var_sangria_users_per' . $country));
        if (!$list) {

            $list = new ArrayList();
            $members = DB::query("SELECT M.ID, M.ClassName, M.FirstName, M.Surname, M.Email, M.Country FROM Member M  WHERE M.Country = '{$country}' ; ");
            foreach ($members as $member) {
                // concept: new Deployment($deployment)
                $list->push(new $member['ClassName']($member));
            }
            $cache->save(serialize($list), 'var_sangria_users_per' . $country);
        }

        return $list;
    }

    public function DeploymentMatchingOrgs()
    {
        $filterWhereClause = SangriaPage_Controller::generateFilterWhereClause();

        $range = self::getSurveyRange('ViewDeploymentsPerRegion');

        if ($range == SurveyType::MARCH_2015) {
            $range_filter = "D.Created >= '" . SURVEY_START_DATE . "'";
        } else {
            $range_filter = "D.Created < '" . SURVEY_START_DATE . "'";
        }

        $date_filter = ' AND ' . SangriaPage_Controller::$date_filter_query;

        $query = "SELECT DISTINCT O.Name FROM Deployment D JOIN DeploymentSurvey S ON (D.DeploymentSurveyID = S.ID) JOIN Org O on (S.OrgID = O.ID) WHERE " . $range_filter . $filterWhereClause . $date_filter;

        $results = DB::query($query);
        $list = new ArrayList();

        foreach ($results as $row) {
            $list->push(new Org($row));
        }

        return $list;
    }

    public function getDeploymentTypeOptions()
    {
        $options = '';
        foreach (DeploymentOptions::$deployment_type_options as $key => $val) {
            $options .= sprintf('<option value="%s">%s</option>', $key, $val);
        }

        return $options;
    }

    function exportUsersPerRegion()
    {
        $params = $this->owner->getRequest()->getVars();
        if (!isset($params['countries']) || empty($params['countries'])) {
            return $this->owner->httpError('412', 'missing required param countries');
        }

        if (!isset($params['members']) || empty($params['members'])) {
            return $this->owner->httpError('412', 'missing required param members');
        }

        $countries = $params['countries'];
        $members = $params['members'];
        $join_members = '';
        $join_countries = '';

        if (!count($countries)) {
            return $this->owner->httpError('412', 'missing required param countries');
        } else {
            foreach ($countries as $country) {
                $join_countries .= "'" . $country . "',";
            }
        }
        $join_countries = rtrim($join_countries, ",");

        if (!count($members)) {
            return $this->owner->httpError('412', 'missing required param members');
        } else {
            foreach ($members as $member) {
                $join_members .= "'" . $member . "',";
            }
        }
        $join_members = rtrim($join_members, ",");


        $query = new SQLQuery();
        $select_fields = array(
            'Member.FirstName',
            'Member.Surname',
            'Member.Email',
            'Member.City',
            'Member.State',
            'Member.Country'
        );
        $query->setFrom('Member');
        $query->setSelect($select_fields);
        $query->addInnerJoin('Group_Members', 'Group_Members.MemberID = Member.ID');
        $query->addInnerJoin('Group', "Group.ID = Group_Members.GroupID AND Group.Code IN (" . $join_members . ")");
        $query->setWhere("Member.Country IN (" . $join_countries . ")");
        $query->setOrderBy('SurName,FirstName');

        $result = $query->execute();

        $data = array();
        foreach ($result as $row) {
            $member = array(
                'FirstName' => $row['FirstName'],
                'Surname' => $row['Surname'],
                'Email' => $row['Email'],
                'City' => $row['City'],
                'State' => $row['State'],
                'Country' => CountryCodes::$iso_3166_countryCodes[$row['Country']]
            );

            array_push($data, $member);
        }

        $filename = "UsersPerCountry" . date('Ymd') . ".csv";

        return CSVExporter::getInstance()->export($filename, $data, ',');
    }

    // survey builder statistics

    public function SurveyBuilderSurveyTemplates($class_name = 'EntitySurveyTemplate')
    {
        Session::set('SurveyBuilder.Statistics.ClassName', $class_name);
        if ($class_name === 'SurveyTemplate') {
            return SurveyTemplate::get()->filter('ClassName', 'SurveyTemplate');
        }

        return EntitySurveyTemplate::get();
    }

    public function getSurveyQuestions2Show()
    {
        $template = $this->getCurrentSelectedSurveyTemplate();
        if (is_null($template)) {
            return new ArrayList();
        }
        $res = array();
        foreach ($template->Steps()->sort('Order') as $step) {
            if (!$step instanceof ISurveyRegularStepTemplate) {
                continue;
            }

            foreach ($step->Questions()->sort('Order') as $q) {
                if ($q->ShowOnSangriaStatistics) {
                    array_push($res, $q);
                }
            }
        }

        return new ArrayList($res);
    }

    /**
     * @return SurveyTemplate|null
     */
    private function getCurrentSelectedSurveyTemplate()
    {

        $template_id = Session::get(sprintf("SurveyBuilder.%sStatistics.TemplateId",
            Session::get('SurveyBuilder.Statistics.ClassName')));
        $template = null;
        if (!empty($template_id)) {
            $template = SurveyTemplate::get()->byID(intval($template_id));
            if (!is_null($template) && $template->ClassName === 'EntitySurveyTemplate') {
                $template = EntitySurveyTemplate::get()->byID(intval($template_id));
            }
        }

        return $template;
    }

    /**
     * @return null|string
     */
    private function getCurrentSelectedSurveyClassName()
    {
        $template = $this->getCurrentSelectedSurveyTemplate();
        if (is_null($template)) {
            return null;
        }
        if ($template instanceof EntitySurveyTemplate) {
            return 'EntitySurvey';
        }

        return "Survey";
    }

    public function RenderCurrentFilters()
    {
        $questions_filters = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters_Questions',
            Session::get('SurveyBuilder.Statistics.ClassName')));
        if (!empty($questions_filters)) {
            $template = $this->getCurrentSelectedSurveyTemplate();
            if (is_null($template)) {
                return;
            }

            $questions_filters = explode(',', $questions_filters);
            $output = '';
            foreach ($questions_filters as $qid) {
                if (empty($qid)) {
                    continue;
                }
                $q = $template->getQuestionById($qid);
                if (is_null($q)) {
                    continue;
                }
                $output .= $q->Name . ',';
            }

            return trim($output, ',');
        }

        return '';
    }

    public function IsSurveyTemplateSelected($current_template_id)
    {
        $template_id = Session::get(sprintf("SurveyBuilder.%sStatistics.TemplateId",
            Session::get('SurveyBuilder.Statistics.ClassName')));

        return (!empty($template_id) && !empty($current_template_id) && intval($current_template_id) === intval($template_id));
    }

    public function SurveyBuilderDateFilterQueryString()
    {
        $request = Controller::curr()->getRequest();
        $from = $request->getVar('From');
        $to = $request->getVar('To');
        $query_str = '';
        if (!empty($from) && !empty($to)) {
            $query_str = sprintf("&From=%s&To=%s", $from, $to);
        }

        return $query_str;
    }

    public function IsQuestionOnFiltering($qid)
    {
        $questions_filters = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters_Questions',
            Session::get('SurveyBuilder.Statistics.ClassName')));
        if (!empty($questions_filters)) {
            $questions_filters = explode(',', $questions_filters);

            return in_array($qid, $questions_filters);
        }

        return false;
    }

    public function SurveyBuilderSurveyCount()
    {
        $request   = Controller::curr()->getRequest();
        $from     = $request->getVar('From');
        $to       = $request->getVar('To');
        $template = $this->getCurrentSelectedSurveyTemplate();

        if (is_null($template)) {
            return 0;
        }
        $class_name = $this->getCurrentSelectedSurveyClassName();
        $filters = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters',
            Session::get('SurveyBuilder.Statistics.ClassName')));

        $filter_query_tpl_int = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyQuestionValueTemplate V2 ON V2.OwnerID = Q2.ID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'
		AND FIND_IN_SET(V2.ID, A2.Value) > 0
		AND SSTPL2.ID = %s
		AND Q2.ID = %s
		AND V2.ID = %s
        AND I2.ID = I.ID
	)
SQL;


        $filter_query_tpl_str = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'

		AND SSTPL2.ID = %s
		AND Q2.ID = %s
        AND I2.ID = I.ID
       	AND FIND_IN_SET('%s', A2.Value) > 0
	)
SQL;
        $filters_where = '';

        if (!empty($from) && !empty($to)) {
            $filters_where = " AND " . SangriaPage_Controller::generateDateFilters("I", "LastEdited");
        }

        if (!empty($filters)) {
            $filters = trim($filters, ',');
            $filters = explode(',', $filters);
            foreach ($filters as $t) {
                $t = explode(':', $t);
                $qid = intval($t[0]);
                $vid = is_int($t[1]) ? intval($t[1]) : $t[1];
                $vid = is_int($t[1]) ? intval($t[1]) : $t[1];
                if(count($t) === 3)
                    $vid = sprintf('%s:%s', $t[1], $t[2]);
                $filter_query_tpl = is_int($vid) ? $filter_query_tpl_int : $filter_query_tpl_str;
                $filters_where .= sprintf($filter_query_tpl, $template->ID, $qid, $vid);
            }
        }

        $query = <<<SQL
    SELECT COUNT(I.ID) FROM Survey I
    WHERE
    I.TemplateID = $template->ID AND I.ClassName = '{$class_name}' {$filters_where};
SQL;

        return DB::query($query)->value();
    }

    public function SurveyBuilderLabelSubmitted()
    {
        $class_name = Session::get('SurveyBuilder.Statistics.ClassName');
        if (empty($class_name)) {
            return;
        }
        if ($class_name === 'SurveyTemplate') {
            return "Surveys Submitted";
        } else {
            return "Deployments Submitted";
        }
    }

    private function ViewStatisticsSurveyBuilder(SS_HTTPRequest $request, $action, $class_name)
    {
        Requirements::javascript('themes/openstack/javascript/sangria/sangria.page.view.statistics.surveybuilder.js');

        $qid           = $request->requestVar('qid');
        $vid           = $request->requestVar('vid');
        $clear_filters = $request->requestVar('clear_filters');
        $from          = $request->requestVar('From');
        $to            = $request->requestVar('To');
        $template_id   = intval($request->requestVar('survey_template_id'));

        if ($template_id != 0)
        {
            Session::set(sprintf("SurveyBuilder.%sStatistics.TemplateId", $class_name), $template_id);
            Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters", $class_name));
            Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters_Questions", $class_name));
        }

        if (!empty($clear_filters)) {
            Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters", $class_name));
            Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters_Questions", $class_name));

            return Controller::curr()->redirect(Controller::curr()->Link($action));
        } else {
            if (!empty($qid) && !empty($vid)) {
                $qid = intval($qid);
                $vid = is_int($vid) ? intval($vid) : $vid;
                $filters = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters', $class_name));
                $questions_filters = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters_Questions', $class_name));
                $filters .= sprintf("%s:%s,", $qid, $vid);
                $questions_filters .= sprintf("%s,", $qid);

                Session::set(sprintf("SurveyBuilder.%sStatistics.Filters", $class_name), $filters);
                Session::set(sprintf("SurveyBuilder.%sStatistics.Filters_Questions", $class_name), $questions_filters);

                $query_str = '';
                if (!empty($from) && !empty($to)) {
                    $query_str = sprintf("?From=%s&To=%s", $from, $to);
                }

                return Controller::curr()->redirect(Controller::curr()->Link($action) . $query_str);
            }
        }

        return $this->owner->Customise
        (
            array
            (
                'ClassName' => $class_name,
                'Action' => $action
            )
        )->renderWith(array('SangriaPage_ViewStatisticsSurveyBuilder', 'SangriaPage', 'SangriaPage'));
    }

    public function ViewDeploymentStatisticsSurveyBuilder(SS_HTTPRequest $request)
    {
        $this->clearStatisticsSurveyBuilderSessionData('SurveyTemplate');

        return $this->ViewStatisticsSurveyBuilder($request, 'ViewDeploymentStatisticsSurveyBuilder',
            'EntitySurveyTemplate');
    }

    public function ViewSurveysStatisticsSurveyBuilder(SS_HTTPRequest $request)
    {
        $this->clearStatisticsSurveyBuilderSessionData('EntitySurveyTemplate');

        return $this->ViewStatisticsSurveyBuilder($request, 'ViewSurveysStatisticsSurveyBuilder', 'SurveyTemplate');
    }

    private function clearStatisticsSurveyBuilderSessionData($class_name)
    {
        Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters", $class_name));
        Session::clear(sprintf("SurveyBuilder.%sStatistics.Filters_Questions", $class_name));
        Session::clear(sprintf("SurveyBuilder.%sStatistics.TemplateId", $class_name));
    }

    public function IsRegularStep($template)
    {
        return true;
    }

    /**
     * @param $question_id
     * @param $row_id
     * @param $column_id
     * @return string
     */
    public function SurveyBuilderMatrixCountAnswers($question_id, $row_id, $column_id)
    {
        $filters_where = '';

        $template    = $this->getCurrentSelectedSurveyTemplate();

        if (is_null($template))
        {
            return;
        }

        $filters    = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters', Session::get('SurveyBuilder.Statistics.ClassName')));


        $class_name = $this->getCurrentSelectedSurveyClassName();

        $filter_query_tpl_int = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyQuestionValueTemplate V2 ON V2.OwnerID = Q2.ID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'
		AND FIND_IN_SET(V2.ID, A2.Value) > 0
		AND SSTPL2.ID = %s
		AND Q2.ID = %s
		AND V2.ID = %s
        AND I2.ID = I.ID
	)
SQL;

        $filter_query_tpl_str = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'
	    AND SSTPL2.ID = %s
		AND Q2.ID = %s
        AND I2.ID = I.ID
        AND FIND_IN_SET('%s', A2.Value) > 0
	)
SQL;

        $filters_where = '';

        if (!empty($from) && !empty($to)) {
            $filters_where = " AND " . SangriaPage_Controller::generateDateFilters("I", "LastEdited");
        }

        if (!empty($filters)) {
            $filters = trim($filters, ',');
            $filters = explode(',', $filters);
            foreach ($filters as $t) {
                $t = explode(':', $t);
                $qid = intval($t[0]);
                $vid = is_int($t[1]) ? intval($t[1]) : $t[1];
                if(count($t) === 3)
                    $vid = sprintf('%s:%s', $t[1], $t[2]);
                $filter_query_tpl = is_int($vid) ? $filter_query_tpl_int : $filter_query_tpl_str;
                $filters_where .= sprintf($filter_query_tpl, $template->ID, $qid, $vid);
            }
        }

$query = <<<SQL

SELECT COUNT(A.Value) FROM SurveyAnswer A
    INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
    INNER JOIN SurveyStepTemplate STPL ON STPL.ID = Q.StepID
    INNER JOIN SurveyTemplate SSTPL ON SSTPL.ID = STPL.SurveyTemplateID
    INNER JOIN SurveyStep S ON S.ID = A.StepID
    INNER JOIN Survey I ON I.ID = S.SurveyID
    WHERE
    I.ClassName = '{$class_name}'
    AND FIND_IN_SET('{$row_id}:{$column_id}', A.Value) > 0
    AND SSTPL.ID = $template->ID
    AND Q.ID = {$question_id}
    {$filters_where};
SQL;

        return DB::query($query)->value();
    }

    public function SurveyBuilderMatrixPercentAnswers($question_id, $row_id, $column_id)
    {
        $count   = $this->SurveyBuilderMatrixCountAnswers($question_id, $row_id, $column_id);
        $total   = $this->SurveyBuilderSurveyCount();
        $percent = ($count/$total) * 100;
        $percent = sprintf ("%.2f", $percent);
        return '( '.$percent.' % )';
    }

    public function SurveyBuilderCountAnswers($question_id, $value_id)
    {

        $request     = Controller::curr()->getRequest();
        $from        = $request->getVar('From');
        $to          = $request->getVar('To');
        $question_id = intval($question_id);
        $value_id    = intval($value_id) > 0 ? intval($value_id) : $value_id;
        $template    = $this->getCurrentSelectedSurveyTemplate();

        if (is_null($template))
        {
            return;
        }

        $filters    = Session::get(sprintf('SurveyBuilder.%sStatistics.Filters', Session::get('SurveyBuilder.Statistics.ClassName')));
        $class_name = $this->getCurrentSelectedSurveyClassName();

        $filter_query_tpl_int = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyQuestionValueTemplate V2 ON V2.OwnerID = Q2.ID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'
		AND FIND_IN_SET(V2.ID, A2.Value) > 0
		AND SSTPL2.ID = %s
		AND Q2.ID = %s
		AND V2.ID = %s
        AND I2.ID = I.ID
	)
SQL;

        $filter_query_tpl_str = <<<SQL
    AND EXISTS
    (
		SELECT * FROM SurveyAnswer A2
        INNER JOIN SurveyQuestionTemplate Q2 ON Q2.ID = A2.QuestionID
		INNER JOIN SurveyStepTemplate STPL2 ON STPL2.ID = Q2.StepID
		INNER JOIN SurveyTemplate SSTPL2 ON SSTPL2.ID = STPL2.SurveyTemplateID
		INNER JOIN SurveyStep S2 ON S2.ID = A2.StepID
		INNER JOIN Survey I2 ON I2.ID = S2.SurveyID
        WHERE
        I2.ClassName = '{$class_name}'
	    AND SSTPL2.ID = %s
		AND Q2.ID = %s
        AND I2.ID = I.ID
        AND FIND_IN_SET('%s', A2.Value) > 0
	)
SQL;

        $filters_where = '';

        if (!empty($from) && !empty($to)) {
            $filters_where = " AND " . SangriaPage_Controller::generateDateFilters("I", "LastEdited");
        }

        if (!empty($filters)) {
            $filters = trim($filters, ',');
            $filters = explode(',', $filters);
            foreach ($filters as $t) {
                $t = explode(':', $t);
                $qid = intval($t[0]);
                $vid = is_int($t[1]) ? intval($t[1]) : $t[1];
                if(count($t) === 3)
                    $vid = sprintf('%s:%s', $t[1], $t[2]);
                $filter_query_tpl = is_int($vid) ? $filter_query_tpl_int : $filter_query_tpl_str;
                $filters_where .= sprintf($filter_query_tpl, $template->ID, $qid, $vid);
            }
        }

        $query_str = <<<SQL
SELECT COUNT(A.Value) FROM SurveyAnswer A
    INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
    INNER JOIN SurveyStepTemplate STPL ON STPL.ID = Q.StepID
    INNER JOIN SurveyTemplate SSTPL ON SSTPL.ID = STPL.SurveyTemplateID
    INNER JOIN SurveyStep S ON S.ID = A.StepID
    INNER JOIN Survey I ON I.ID = S.SurveyID
    WHERE
    I.ClassName = '{$class_name}'
    AND FIND_IN_SET('{$value_id}', A.Value) > 0
    AND SSTPL.ID = $template->ID
    AND Q.ID = {$question_id}
    {$filters_where};
SQL;

        $query_int = <<<SQL
    SELECT COUNT(A.Value) FROM SurveyAnswer A
    INNER JOIN SurveyQuestionTemplate Q ON Q.ID = A.QuestionID
    INNER JOIN SurveyStepTemplate STPL ON STPL.ID = Q.StepID
    INNER JOIN SurveyTemplate SSTPL ON SSTPL.ID = STPL.SurveyTemplateID
    INNER JOIN SurveyQuestionValueTemplate V ON V.OwnerID = Q.ID
    INNER JOIN SurveyStep S ON S.ID = A.StepID
    INNER JOIN Survey I ON I.ID = S.SurveyID
    WHERE
    I.ClassName = '{$class_name}'
    AND FIND_IN_SET(V.ID, A.Value) > 0
    AND SSTPL.ID = $template->ID
    AND Q.ID = {$question_id}
    AND V.ID = {$value_id} {$filters_where};
SQL;
        $query = is_int($value_id) ? $query_int : $query_str;

        return DB::query($query)->value();
    }




}