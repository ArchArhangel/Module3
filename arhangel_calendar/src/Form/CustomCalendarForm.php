<?php

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
   * Our static month array.
   *
   * @var array|string[]
   */
  protected static array $months = [
    'jan',
    'feb',
    'mar',
    'apr',
    'may',
    'jun',
    'jul',
    'aug',
    'sep',
    'oct',
    'nov',
    'dec',
  ];

  /**
   * Our static quartal array.
   *
   * @var array|\string[][]
   */
  protected static array $quartals = [
    'q1' => [
      'jan',
      'feb',
      'mar',
    ],
    'q2' => [
      'apr',
      'may',
      'jun',
    ],
    'q3' => [
      'jul',
      'aug',
      'sep',
    ],
    'q4' => [
      'oct',
      'nov',
      'dec',
    ],
  ];

  /**
   * Our static row items array.
   *
   * @var array|string[]
   */
  protected static array $rowItems = [
    'year',
    'jan',
    'feb',
    'mar',
    'q1',
    'apr',
    'may',
    'jun',
    'q2',
    'jul',
    'aug',
    'sep',
    'q3',
    'oct',
    'nov',
    'dec',
    'q4',
    'ytd',
  ];

  /**
   * Create dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   *
   * @return \Drupal\arhangel_calendar\Form\CustomCalendarForm
   *   Form.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('messenger'));
  }

  /**
   * CustomCalendarForm constructor.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger variable.
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'arhangel_calendar_custom_calendar_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
          $this->t('Year'),
          $this->t('Jan'),
          $this->t('Feb'),
          $this->t('Mar'),
          $this->t('Q1'),
          $this->t('Apr'),
          $this->t('May'),
          $this->t('Jun'),
          $this->t('Q2'),
          $this->t('Jul'),
          $this->t('Aug'),
          $this->t('Sep'),
          $this->t('Q3'),
          $this->t('Oct'),
          $this->t('Nov'),
          $this->t('Dec'),
          $this->t('Q4'),
          $this->t('YTD'),
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
        $date = (int) date('Y') - $j + 1;
        $form['calendar'][$i]['table'][$j] = $this->addNewRow($date);

        isset($form_state->getTriggeringElement()['#name'])
          ? $position = $form_state->getTriggeringElement()['#name']
          : $position = '';

        if ($position === 'main-submit') {
          $month_list = [];
          $quartal_list = [];
          $ytd = 0;
          foreach (self::$months as $month) {
            $month_list[$month] = $form_state->getValue([
              'calendar',
              $i,
              'table',
              $j,
              $month,
            ]);
          }

          foreach (self::$quartals as $quartal => $quartal_month) {
            $quartal_value = 0;
            foreach ($quartal_month as $month_item) {
              $quartal_value = (float) $quartal_value
                + (float) $month_list[$month_item];
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
            $ytd = (float) $ytd + (float) $item_value;
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
   *
   * @param int $year
   *   Year.
   *
   * @return array
   *   Table.
   */
  public function addNewRow(int $year) {
    $row = [];
    foreach (self::$rowItems as $item) {
      if (($item == 'year')) {
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
      elseif (
        ($item == 'q1')
        || ($item == 'q2')
        || ($item == 'q3')
        || ($item == 'q4')
        || ($item == 'ytd')
      ) {
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
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
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
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function addTableCallback(array &$form, FormStateInterface $form_state) {
    $table = $form_state->get('calendar');
    $table++;
    $form_state->set('calendar', $table);
    $form_state->setRebuild();
  }

  /**
   * Ajax form callback return.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   Full form.
   */
  public function formReturn(array &$form, FormStateInterface $form_state) {
    $table = $form_state->getTriggeringElement()['#name'];
    $table = explode(' ', $table);
    return $form['calendar'][$table[1]]['table'];
  }

  /**
   * Ajax table callback return.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   Full table.
   */
  public function tableReturn(array &$form, FormStateInterface $form_state) {
    return $form['calendar'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tables = $form_state->getValue('calendar');
    $table_count = $form_state->get('calendar');
    $start = [];
    $end = [];

    foreach ($tables as $table_key => $table) {
      $rows = [];
      foreach ($table['table'] as $row_key => $row) {
        foreach ($row as $item_key => $item) {
          if (in_array($item_key, self::$months)) {
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
      $rows = array_filter($rows,
        function ($s) {
          return $s !== '';
        }
      );
      $count = count($rows);
      if (
        ((array_key_last($rows) - array_key_first($rows) + 1) != $count)
        && ($count != 0)
      ) {
        $this->messenger()->addError($this->t('Invalid'));
        return FALSE;
      }
    }

    for ($i = 1; $i < $table_count; $i++) {
      if (
        ($start[0] != $start[$i])
        || ($end[0] != $end[$i])
      ) {
        $this->messenger()->addError($this->t('Invalid'));
        return FALSE;
      }
    }

    $this->messenger()->addStatus($this->t('Valid'));
    $form_state->setRebuild();
    return TRUE;
  }

}
