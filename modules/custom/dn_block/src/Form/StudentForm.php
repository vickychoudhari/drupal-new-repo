<?php

namespace Drupal\dn_block\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Routing;

/**
 * Provides the form for adding countries.
 */
class StudentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dn_student_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


    
    $form['fname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#maxlength' => 20,
      '#default_value' =>  '',
    ];
	 $form['sname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second Name'),
      '#required' => TRUE,
      '#maxlength' => 20,
      '#default_value' =>  '',
    ];
	$form['age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Age'),
      '#required' => TRUE,
      '#maxlength' => 20,
      '#default_value' => '',
    ];
	 $form['marks'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Marks'),
      '#required' => TRUE,
      '#maxlength' => 20,
      '#default_value' => '',
    ];
	
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#default_value' => $this->t('Save') ,
    ];
	
	//$form['#validate'][] = 'studentFormValidate';

    return $form;

  }
  
   /**
   * {@inheritdoc}
   */
  public function validateForm(array & $form, FormStateInterface $form_state) {
       $field = $form_state->getValues();
	   
		$fields["fname"] = $field['fname'];
		if (!$form_state->getValue('fname') || empty($form_state->getValue('fname'))) {
            $form_state->setErrorByName('fname', $this->t('Provide First Name'));
        }
		
		
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array & $form, FormStateInterface $form_state) {
	try{
		
		
		$field = $form_state->getValues();
	   
	
    $this->messenger()->addMessage($this->t('The form has been submitted. name="@first @last"', [
      '@first' => $field['fname'],
      '@last' => $field['sname']
    ]));
    
		  
	} catch(Exception $ex){
		\Drupal::logger('dn_block')->error($ex->getMessage());
	}
    
  }

}
  
