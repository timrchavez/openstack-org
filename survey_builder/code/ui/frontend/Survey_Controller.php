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

/**
 * Class Survey_Controller
 */
class Survey_Controller extends Page_Controller {

    const RoutePrefix  = 'surveys';

    static $allowed_actions_without_auth = array(
        'LandingPage',
        'RegisterForm',
        'MemberStart',
        'StartSurvey'
    );

    static $allowed_actions = array(
        'LandingPage',
        'RenderSurvey',
        'SurveyStepForm',
        'AddEntity',
        'SkipStep',
        'EditEntity',
        'NextDynamicEntityStep',
        'RegisterForm',
        'MemberStart',
        'StartSurvey',
    );

    static $url_handlers = array(
        'landing'                                     => 'LandingPage',
        'current/$STEP_SLUG/add-entity'               => 'AddEntity',
        'current/$STEP_SLUG/skip-step'                => 'SkipStep',
        'current/$STEP_SLUG/edit/$ENTITY_SURVEY_ID'   => 'EditEntity',
        'current/$STEP_SLUG/delete/$ENTITY_SURVEY_ID' => 'DeleteEntity',
        'current//$STEP_SLUG'                         => 'RenderSurvey',
        '$Action//$ID/$OtherID'                       => 'handleAction',
    );

    /**
     * @var ISurvey
     */
    private $current_survey;

    /**
     * @var IEntitySurvey
     */
    private $current_entity_survey;

    /**
     * @var ISurveyManager
     */
    private $survey_manager;

    /**
     * @var ISurveyRepository
     */
    private $survey_repository;

    /**
     * @return ISurveyManager
     */
    public function getSurveyManager()
    {
        return $this->survey_manager;
    }

    /**
     * @param ISurveyManager $survey_manager
     */
    public function setSurveyManager(ISurveyManager $survey_manager)
    {
        $this->survey_manager = $survey_manager;
    }

    /**
     * @return ISurveyRepository
     */
    public function getSurveyRepository()
    {
        return $this->survey_repository;
    }

    /**
     * @param ISurveyRepository $survey_repository
     */
    public function setSurveyRepository(ISurveyRepository $survey_repository)
    {
        $this->survey_repository = $survey_repository;
    }

    function init()
    {
        $this->current_survey = $this->current_entity_survey = null;
        $this->BootstrapConverted = true;

        parent::init();

        Requirements::css(Director::protocol() . '://maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css');
        Requirements::css('survey_builder/css/user-survey.css');
        Requirements::css("themes/openstack/css/chosen.css", "screen,projection");
        Requirements::css("themes/openstack/css/jquery.autocomplete.css");

        Requirements::block(SAPPHIRE_DIR . '/thirdparty/behaviour/behaviour.js');
        Requirements::block(SAPPHIRE_DIR . '/thirdparty/prototype/prototype.js');
        Requirements::block(SAPPHIRE_DIR . '/javascript/prototype_improvements.js');

        Requirements::javascript(Director::protocol() . "ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/jquery.validate.js");
        Requirements::javascript(Director::protocol() . "ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/additional-methods.js");
        Requirements::javascript("themes/openstack/javascript/chosen.jquery.min.js");
        Requirements::javascript("themes/openstack/javascript/jquery.autocomplete.min.js");
        Requirements::javascript('survey_builder/js/survey.validation.rules.jquery.js');
        Requirements::javascript('survey_builder/js/survey.controller.js');
    }

    protected function handleAction($request, $action) {
        if (!Member::currentUser()) {
            if (!in_array($action, self::$allowed_actions_without_auth)) {
                return $this->redirect("surveys/landing?BackURL=" . urlencode('/surveys/current'));
            }
        }
        if(strtolower($action) === 'index') return $this->redirect(self::RoutePrefix.'/current');
        return parent::handleAction($request, $action);
    }

    public function BootstrapConverted()
    {
        return true;
    }

    public function RenderSurvey($request)
    {
        //check if user is logged in

        $step     = $request->param('STEP_SLUG');

        $current_survey = $this->getCurrentSurveyInstance();

        if(!empty($step) && !$current_survey->isAllowedStep($step)){
            // redirect
            return $this->redirect('/surveys/current/'.$current_survey->currentStep()->template()->title());
        }

        $this->survey_manager->registerCurrentStep($this->current_survey, $step);

        if($current_survey->isLastStep() && !$current_survey->isEmailSent()){
            $this->survey_manager->sendFinalStepEmail( new SurveyThankYouEmailSenderService, $current_survey);
        }

        return $this->customise(array(
            'Survey' => $current_survey,
        ))->renderWith(array('Surveys_CurrentSurveyContainer', 'Page'));
    }

    public function LandingPage()
    {
        return $this->customise(array('BackURL' => $this->request->requestVar('BackURL')))->renderWith(array('Surveys_LandingPage', 'Page'));
    }

    /**
     * @param $step_name
     * @return string
     * @throws NotFoundEntityException
     */
    public function SurveyStepClass($step_name){
        $css_step_class = '';
        $current_survey = $this->getCurrentSurveyInstance();
        $current_step   = $current_survey->currentStep();
        if($current_step->template()->title() == $step_name)
            $css_step_class = 'current';
        else{
            //check if future or complete
            if($current_survey->isAllowedStep($step_name)){
                $css_step_class = 'completed';
            }
            else{
                $css_step_class = 'future';
            }
        }
        return $css_step_class;
    }

    /**
     * @return ISurvey|null
     * @throws NotFoundEntityException
     */
    private function getCurrentSurveyInstance()
    {
        if(!is_null($this->current_survey)) return $this->current_survey;
        $current_survey_id = Session::get('CURRENT_SURVEY_ID');
        if (!empty($current_survey_id)){
            $this->current_survey = $this->survey_repository->getById($current_survey_id);

        }
        if(is_null($this->current_survey)){
            $current_template = $this->survey_manager->getCurrentSurveyTemplate();

            if (is_null($current_template))
                throw new NotFoundEntityException('SurveyTemplate', 'current template not set');

            $this->current_survey     = $this->survey_manager->buildSurvey($current_template->getIdentifier(), Member::currentUserID());
            Session::set('CURRENT_SURVEY_ID', $this->current_survey->getIdentifier());
        }

        return $this->current_survey;
    }

    /**
     * @param int $entity_survey_id
     * @return IEntitySurvey
     * @throws NotFoundEntityException
     */
    private function getCurrentEntitySurveyInstance($entity_survey_id){
        if(!is_null($this->current_entity_survey)) return $this->current_entity_survey;
        $this->current_survey = $this->getCurrentSurveyInstance();
        $this->current_entity_survey = $this->current_survey->currentStep()->getEntitySurvey($entity_survey_id);
        return $this->current_entity_survey;
    }

    public function SurveyStepForm()
    {
        $current_survey = $this->getCurrentSurveyInstance();
        $current_step   = $current_survey->currentStep();
        $builder        = SurveyStepUIBuilderFactory::getInstance()->build($current_step);
        $form           = $builder->build($current_step, 'NextStep');
        return $form;
    }

    public function NextStep($data, $form)
    {
        $current_survey = $this->getCurrentSurveyInstance();
        $current_step   = $current_survey->currentStep();
        if(!($current_step instanceof ISurveyRegularStep)) throw new LogicException();

        $next_step = $this->survey_manager->completeStep($current_step, $data);
        return $this->redirect('/surveys/current/'.$next_step->template()->title());
    }

    public function SkipStep($request){
        $current_survey = $this->getCurrentSurveyInstance();
        $current_step   = $current_survey->currentStep();
        $can_skip       = false;
        if($current_step instanceof ISurveyDynamicEntityStep && count($current_step->getEntitySurveys()) > 0 ){
            $can_skip = true;
        }
        if($current_step->template()->canSkip()) $can_skip = true;
        if(!$can_skip)  $this->redirectBack();
        $step = $request->param('STEP_SLUG');

        $next_step = $this->survey_manager->completeStep($current_step, $data = array());
        return $this->redirect('/surveys/current/'.$next_step->template()->title());
    }

    // Dynamic Entities

    public function EditEntity($request){

        $step                 = $request->param('STEP_SLUG');
        $entity_survey_id     = intval($request->param('ENTITY_SURVEY_ID'));
        $this->current_survey = $this->getCurrentSurveyInstance();
        $current_step         = $this->current_survey->currentStep();

        if(!($current_step instanceof ISurveyDynamicEntityStep)) throw new LogicException();

        $this->current_entity_survey  = $current_step->getEntitySurvey($entity_survey_id);

        return $this->customise(array(
            'Survey'       => $this->current_survey,
            'EntitySurvey' => $this->current_entity_survey
        ))->renderWith(array('Surveys_CurrentSurveyDynamicEntityContainer', 'Page'));
    }

    public function SurveyDynamicEntityStepForm(){
        $this->current_survey = $this->getCurrentSurveyInstance();
        $current_step        =  $this->current_survey->currentStep();
        if(is_null($this->current_entity_survey)) throw new LogicException();
        $builder        = SurveyStepUIBuilderFactory::getInstance()->build($this->current_entity_survey->currentStep());
        $form           = $builder->build($this->current_entity_survey->currentStep(), 'NextDynamicEntityStep');
        return $form;
    }

    public function AddEntity($request){

        $current_survey = $this->getCurrentSurveyInstance();
        $current_step   = $current_survey->currentStep();
        if(!($current_step instanceof ISurveyDynamicEntityStep)) throw new LogicException();

        //create the current survey entity
        $this->current_entity_survey = $this->survey_manager->buildEntitySurvey($current_step, Member::currentUserID());
        $url = sprintf('surveys/current/%s/edit/%s', $current_step->template()->title(), $this->current_entity_survey->getIdentifier());
        $this->redirect($url);
    }

    public function NextDynamicEntityStep($data, $form)
    {
        $entity_survey = $this->getCurrentEntitySurveyInstance(intval($data['survey_id']));
        $current_step  = $entity_survey->currentStep();
        $next_step     = $this->survey_manager->completeStep($current_step, $data);
        if($entity_survey->isLastStep()){
            return $this->redirect('surveys/current/'.$this->current_survey->currentStep()->template()->title());
        }
        else{
            return $this->redirect('/surveys/current/'.$next_step->template()->title());
        }
    }

    public function DeleteEntity($request){

        $step                 = $request->param('STEP_SLUG');
        $entity_survey_id     = intval($request->param('ENTITY_SURVEY_ID'));
    }

    // landing page

    public function getLoginPageTitle()
    {
        $res = '';
        if (empty($res)) {
            $res = 'OpenStack User Survey: Welcome!';
        }
        return $res;
    }

    public function getLoginPageContent()
    {
        $link = Controller::curr()->Link();
        $res = '';
        if (empty($res)) {
            $res = <<< HTML

			<p>This survey provides users an opportunity to influence the community and software
		direction. By sharing information about your configuration and requirements, the OpenStack
		Foundation User Committee will be able to advocate on your behalf.</p>
		<p><a href="{$link}faq" class="roundedButton">More Information About The Survey</a></p>
		<hr/>

		<h1>Get Started</h1>
HTML;
        }
        return $res;
    }

    public function getLoginPageSlide1Content()
    {
        $res = '';
        if (empty($res)) {
            $res = 'This is the <strong>OpenStack User Survey</strong> for OpenStack cloud users and operators.';
        }
        return $res;
    }


    public function getLoginPageSlide2Content()
    {
        $res = '';
        if (empty($res)) {
            $res = 'It should only take <strong>10 minutes</strong> to complete.';
        }
        return $res;
    }

    public function getLoginPageSlide3Content()
    {
        $res = '';
        if (empty($res)) {
            $res = 'All of the information you provide is <strong>confidential</strong> to the Foundation (unless you specify otherwise).';
        }
        return $res;
    }

    public function RegisterForm()
    {
        return new SurveyRegistrationForm($this, 'RegisterForm');
    }

    public function MemberStart()
    {
        $member = null;
        if (isset($_REQUEST['m'])) {
            $member = Member::get()->byID((int)$_REQUEST['m']);
        }
        // Check whether we are merely changin password, or resetting.
        if (isset($_REQUEST['t']) && $member && $member->validateAutoLoginToken($_REQUEST['t'])) {
            $member->logIn();
            return $this->redirect(self::RoutePrefix.'/current');
        } elseif (Member::currentUser()) {
            return $this->redirect(self::RoutePrefix.'/current');
        } else {
            return $this->redirect(self::RoutePrefix.'/current');
        }
    }

}