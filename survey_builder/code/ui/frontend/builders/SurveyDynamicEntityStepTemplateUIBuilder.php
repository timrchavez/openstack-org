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

class SurveyDynamicEntityStepTemplateUIBuilder implements ISurveyStepUIBuilder {
    /**
     * @param ISurveyStep $step
     * @param string $action
     * @param string $form_name
     * @return Form
     */
    public function build(ISurveyStep $step, $action, $form_name ='SurveyStepForm')
    {
        $fields    = new FieldList();

        $fields->add(new HiddenField('survey_id', 'survey_id', $step->survey()->getIdentifier()));
        $fields->add(new HiddenField('step_id', 'step_id', $step->getIdentifier()));


        $content = $step->template()->content();
        if(!empty($content))
            $fields->add(new LiteralField('content', $content));

        if($step->template()->canSkip()){
            $fields->add(
                new LiteralField('skip',sprintf('<p><strong>If you do not wish to answer these questions, you make <a href="/surveys/current/%s/skip-step">skip to the next section</a>.</strong></p>', $step->template()->title()))
            );
        }

        if(!empty($content) || $step->template()->canSkip())
            $fields->add(new LiteralField('hr', '<hr/>'));

        $validator = null;
        $actions   = new FieldList(
            FormAction::create('AddEntity')->setTitle("Add")->setUseButtonTag(true),
            FormAction::create('Done')->setTitle("Done")->setUseButtonTag(true)
        );

        $form =  new DynamicStepForm(Controller::curr(), $form_name, $fields, $actions, $step, $validator);
        $form->setTemplate('DynamicEntityStepForm');
        $form->setAttribute('class','survey_step_form');
        return $form;
    }
}