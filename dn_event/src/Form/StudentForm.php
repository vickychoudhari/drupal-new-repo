<?php

namespace Drupal\dn_event\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Routing;
use Drupal\dn_event\SampleEvent;
/**
 * Provides the form for adding countries.
 */
class StudentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dn_event_form';
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
	   
		$fields["fname"] = $field['fname'];
		$fields["sname"] = $field['sname'];
		$fields["age"] = $field['age'];
		$fields["marks"] = $field['marks'];
		
    /*$node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type'       => 'news',
      'title'      => $row[0],
      'body'       => 'body content updated'
    ]);
    $node->save();*/

    // Following is the example for How to dispatch an event in Drupal 8?
    // Use the namespace of the ExampleEvent class 
   

    // load the Symfony event dispatcher object through services
    $dispatcher = \Drupal::service('event_dispatcher');

    // creating our event class object.
    $event = new SampleEvent($form_state->getValue('fname'));

    // dispatching the event through the ‘dispatch’  method, 
    // passing event name and event object ‘$event’ as parameters.
    $dispatcher->dispatch(SampleEvent::SUBMIT, $event);

		  
	} catch(Exception $ex){
		\Drupal::logger('dn_event')->error($ex->getMessage());
	}
    
  }

}
  
