<?php

/**
 * Contains \Drupal\arhangel_calendar\Form\CustomCalendarForm.
 */
namespace Drupal\arhangel_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CustomCalendarForm.
 *
 * @package Drupal\arhangel_calendar\Form
 */
class CustomCalendarForm extends FormBase {

  /**
   * Create dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   *
   * @return \Drupal\arhangel_calendar\Form\CustomCalendarForm
   *   Form.
   */
  public static function create(ContainerInterface $container): CustomCalendarForm {
    return new static($container->get('messenger'));
  }

  /**
   * Object for Drupal Messenger.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * CustomCalendarForm constructor.
   *
   * @param \Drupal\Core\Messenger\Messenger $messengerInterface
   */
  public function __construct(Messenger $messengerInterface) {
    $this->messenger = $messengerInterface;
  }

  /**
   * {@inheritDoc}
   * @return string
   */
  public function getFormId() {
    return 'arhangel_calendar_custom_calendar_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $months = [
      'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
    ];
    $quartals = [
      'q1' => ['jan', 'feb', 'mar'],
      'q2' => ['apr', 'may', 'jun'],
      'q3' => ['jul', 'aug', 'sep'],
      'q4' => ['oct', 'nov', 'dec'],
    ];

    $tables = $form_state->get('calendar');

    if (empty($tables)) {
      $tables = 1;
      $form_state->set('calendar', $tables);
    }

    $form['calendar']['#prefix'] = '<div class = "arhangel_calendar_tables" id="arhangel_calendar_tables">';
    $form['calendar']['#suffix'] = '</div>';
    $form['calendar']['#tree'] = TRUE;

    for ($i = 0; $i < $tables; $i++) {

      $form['calendar'][$i]['addRow'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add Year'),
        '#name' => 'line ' . $i,
        '#submit' => ['::addRowCallback'],
        '#ajax' => [
          'callback' => '::formReturn',
          'event' => 'click',
          'wrapper' => 'line_wrap' . $i,
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Adding row...'),
          ],
        ],
      ];

      $form['calendar'][$i]['table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Calendar') . $i,
        '#title' => $this->t('Custom Calendar'),
        '#header' => [
          'Year', 'Jan', 'Feb', 'Mar', 'Q1', 'Apr', 'May', 'Jun', 'Q2',
          'Jul', 'Aug', 'Sep', 'Q3', 'Oct', 'Nov', 'Dec', 'Q4', 'YTD',
        ],
        '#attributes' => [
          'id' => 'line_wrap' . $i,
        ],
        '#empty' => 'Empty...',
        '#tree' => TRUE,
      ];

      $rows = $form_state->get('count' . $i);

      if (empty($rows)) {
        $rows = 1;
        $form_state->set('count' . $i, $rows);
      }

      for ($j = $rows; $j > 0; $j--) {
        $date = strval(intval(date('Y') - $j + 1));
        $form['calendar'][$i]['table'][$j] = $this->addNewRow($date);

        if (isset($form_state->getTriggeringElement()['#name']) == 'main-submit') {
          $position = $form_state->getTriggeringElement()['#name'];
        }
        else {
          $position = '';
        }

        if ($position === 'main-submit') {
          $month_list = [];
          $quartal_list = [];
          $ytd = 0;
          foreach ($months as $month) {
            $month_list[$month] = $form_state->getValue([
              'calendar', $i, 'table', $j, $month,
            ]);
          }
          foreach ($quartals as $quartal => $quartal_month) {
            $quartal_value = 0;
            foreach ($quartal_month as $month_item) {
              $quartal_value = floatval($quartal_value) + floatval($month_list[$month_item]);
            }
            if ($quartal_value != 0) {
              $quartal_list[$quartal] = round((($quartal_value + 1) / 3), 2);
            }
            else {
              $quartal_list[$quartal] = '';
            }
          }
          foreach ($quartal_list as $quartal_item => $item_value) {
            $form['calendar'][$i]['table'][$j][$quartal_item]['#value'] = $item_value;
            $ytd = floatval($ytd) + floatval($item_value);
          }
          if ($ytd != 0) {
            $ytd = round((($ytd + 1) / 4), 2);
          }
          else {
            $ytd = '';
          }
          $form['calendar'][$i]['table'][$j]['ytd']['#value'] = $ytd;
        }
      }
    }

    $form['addTable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Table'),
      '#submit' => ['::addTableCallback'],
      '#name' => 'line-table',
      '#ajax' => [
        'callback' => '::tableReturn',
        'event' => 'click',
        'wrapper' => 'arhangel_calendar_tables',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding table...'),
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#name' => 'main-submit',
      '#ajax' => [
        'callback' => '::tableReturn',
        'event' => 'click',
        'wrapper' => 'arhangel_calendar_tables',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];

    $form['#attached']['library'][] = 'arhangel_calendar/calendar-style';

    return $form;

  }

  /**
   * Add new row to table.
   * @param string $year
   *
   * @return array
   */
  public function addNewRow(string $year) {
    $row_items = [
      'year', 'jan', 'feb', 'mar', 'q1', 'apr', 'may', 'jun', 'q2',
      'jul', 'aug', 'sep', 'q3', 'oct', 'nov', 'dec', 'q4', 'ytd',
    ];
    $row = [];
    foreach ($row_items as $item) {
      if ($item == 'year') {
        $row[$item] = [
          '#title' => $item,
          '#value' => $year,
          '#type' => 'number',
          '#disabled' => TRUE,
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['calendar-year'],
          ],
        ];
      }
      elseif (($item == 'q1') || ($item == 'q2') || ($item == 'q3') || ($item == 'q4') || ($item == 'ytd')) {
        $row[$item] = [
          '#title' => $item,
          '#type' => 'textfield',
          '#disabled' => TRUE,
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['calendar-calculation'],
          ],
        ];
      }
      else {
        $row[$item] = [
          '#title' => $item,
          '#type' => 'number',
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['calendar-month'],
          ],
        ];
      }
    }
    return $row;
  }

  /**
   * Add row callback function.
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addRowCallback(array &$form, FormStateInterface $form_state) {
    $table = $form_state->getTriggeringElement()['#name'];
    $table = explode(' ', $table);
    $row = $form_state->get('count' . $table[1]);
    $row++;
    $form_state->set('count' . $table[1], $row);
    $form_state->setRebuild();
  }

  /**
   * Add table callback function.
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addTableCallback(array &$form, FormStateInterface $form_state) {
    $table = $form_state->get('calendar');
    $table++;
    $form_state->set('calendar', $table);
    $form_state->setRebuild();
  }

  /**
   * Ajax form callback return.
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function formReturn(array &$form, FormStateInterface $form_state) {
    $table = $form_state->getTriggeringElement()['#name'];
    $table = explode(' ', $table);
    return $form['calendar'][$table[1]]['table'];
  }

  /**
   * Ajax table callback return.
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function tableReturn(array &$form, FormStateInterface $form_state) {
    return $form['calendar'];
  }

  /**
   * {@inheritDoc}
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $months = [
      'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
    ];

    $errors = FALSE;
    $tables = $form_state->getValue('calendar');
    $table_count = $form_state->get('calendar');
    $start = [];
    $end = [];
    $row_count = [];
    foreach ($tables as $table_key => $table) {
      $rows = [];
      foreach ($table['table'] as $row_key => $row) {
        foreach ($row as $item_key => $item) {
          if (in_array($item_key, $months)) {
            if (empty($start[$table_key]) && !empty($item)) {
              $start[$table_key] = [$row_key, $item_key];
            }
            if (!empty($item)) {
              $end[$table_key] = [$row_key, $item_key];
            }
            array_push($rows, $item);
          }
        }
      }
      $count = count($rows);
      for ($i = 0; $i < $count; $i++) {
        if ($rows[$i] === '') {
          unset($rows[$i]);
        }
        else {
          break;
        }
      }
      for ($i = $count - 1; $i >= 0; $i--) {
        if ($rows[$i] === '') {
          unset($rows[$i]);
        }
        else {
          break;
        }
      }
      if (in_array('', $rows)) {
        $errors = TRUE;
      }
    }
    if ($table_count > 1) {
      for ($i = 0; $i < $table_count; $i++) {
        array_push($row_count, $form_state->get('count' . $i));
      }
      if (in_array(1, $row_count)) {
        for ($i = 1; $i < $table_count; $i++) {
          if (($start[0] != $start[$i]) || ($end[0] != $end[$i])) {
            $errors = TRUE;
          }
        }
      }
    }
    if (!$errors) {
      \Drupal::messenger()->addStatus(t('Valid'));
//      $this->messenger->addStatus('Valid');
      $form_state->setRebuild();
    }
    else {
      \Drupal::messenger()->addError(t('InValid'));
//      $this->messenger->addError('Invalid');
    }
    return $errors;
  }

}
