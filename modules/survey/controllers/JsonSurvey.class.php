<?php

/**
 * JSON Adapter for Survey module
 * @copyright   Comvation AG
 * @author      ss4u <ss4ugroup@gmail.com>
 * @package     contrexx
 * @subpackage  core_json
 */

namespace Cx\modules\survey\controllers;
use \Cx\Core\Json\JsonAdapter;

/**
 * JSON Adapter for Survey module
 * @copyright   Comvation AG
 * @author      ss4u <ss4ugroup@gmail.com>
 * @package     contrexx
 * @subpackage  core_json
 */
class JsonSurvey implements JsonAdapter {
    /**
     * List of messages
     * @var Array 
     */
    private $messages = array();
    
    /**
     * Returns the internal name used as identifier for this adapter
     * @return String Name of this adapter
     */
    public function getName() {
        return 'survey';
    }
    
    /**
     * Returns an array of method names accessable from a JSON request
     * @return array List of method names
     */
    public function getAccessableMethods() {
        return array('modifyQuestions', 'getSurveyQuestions');
    }

    /**
     * Returns all messages as string
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString() {
        return implode('<br />', $this->messages);
    }
    
    public function modifyQuestions() {        
        
        $objQuestion = new \SurveyQuestion();
        
        $objQuestion->id              = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        $objQuestion->surveyId        = isset($_GET['surveyId']) ? (int) $_GET['surveyId'] : 0;
        $objQuestion->questionType    = isset($_POST['questionType']) ? (int) $_POST['questionType'] : 0;
        $objQuestion->question        = isset($_POST['Question']) ? contrexx_input2raw($_POST['Question']) : '';
        $objQuestion->questionRow     = isset($_POST['QuestionRow']) ? contrexx_input2raw($_POST['QuestionRow']) : '';
        $objQuestion->questionChoice  = isset($_POST['ColumnChoices']) ? contrexx_input2raw($_POST['ColumnChoices']) : '';
        $objQuestion->questionAnswers = isset($_POST['QuestionAnswers']) ? contrexx_input2raw($_POST['QuestionAnswers']) : '';        
        $objQuestion->isCommentable   = isset($_POST['Iscomment']) ? (int) $_POST['Iscomment'] : 0;
                
        $objQuestion->save();
        
    }
    
    public function getSurveyQuestions() 
    {
        $objQuestionManager = new \SurveyQuestionManager((int) $_GET['surveyId']);
        return $objQuestionManager->showQuestions();
    }
}

