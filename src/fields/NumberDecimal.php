<?php
/**
 * Created by PhpStorm.
 * User: neeravm
 * Date: 3/25/15
 * Time: 12:16 PM
 */

namespace RedTest\core\fields;

use RedTest\core\forms\Form;
use RedTest\core\Utils;

/**
 * Class NumberDecimal
 *
 * @package RedTest\core\fields
 */
class NumberDecimal extends Field {

  /**
   * Fill random decimal values in the provided field.
   *
   * @param Form $formObject
   *   Form object.
   * @param string $field_name
   *   Field name.
   *
   * @return array
   *   An array with 3 values:
   *   (1) $success: Whether values could be filled in the field.
   *   (2) $values: Values that were filled for the field.
   *   (3) $msg: Message in case there is an error. This will be empty if
   *   $success is TRUE.
   */
  public static function fillDefaultValues(
    Form $formObject,
    $field_name
  ) {
    $num = 1;
    $min = -255;
    $max = 255;
    $scale = 2;
    $decimal_separator = '.';
    if (method_exists($formObject, 'getEntityObject')) {
      // This is an entity form.
      list($field, $instance, $num) = $formObject->getFieldDetails($field_name);
      if (!empty($instance['settings']['min'])) {
        $min = $instance['settings']['min'];
      }
      if (!empty($instance['settings']['max'])) {
        $max = $instance['settings']['max'];
      }
      $scale = $field['settings']['scale'];
      $decimal_separator = $field['settings']['decimal_separator'];
    }

    $values = array();
    for ($i = 0; $i < $num; $i++) {
      // We are assuming that precision is set correctly to accommodate min and
      // max values.
      $min_int = $min * pow(10, $scale);
      $max_int = $max * pow(10, $scale);
      $number = Utils::getRandomInt($min_int, $max_int) / pow(10, $scale);
      $number = str_replace(".", $decimal_separator, $number);
      $values[] = $number;
    }

    $function = "fill" . Utils::makeTitleCase($field_name) . "Values";

    return $formObject->$function($values);
  }

  /**
   * Fills provided decimal values in the field.
   *
   * @param Form $formObject
   *   Form object.
   * @param string $field_name
   *   Field name.
   * @param mixed $values
   *   Following formats are acceptable:
   *   (a) 23.67
   *   (b) array(23.34, -89.12)
   *   (c) array(array('value' => 23.34), array('value' => -89.12))
   *
   * @return array
   *   An array with 3 values:
   *   (1) $success: Whether values could be filled in the field.
   *   (2) $values: Values that were filled for the field.
   *   (3) $msg: Message in case there is an error. This will be empty if
   *   $success is TRUE.
   */
  public static function fillValues(
    Form $formObject,
    $field_name,
    $values
  ) {
    $access_function = "has" . Utils::makeTitleCase($field_name) . "Access";
    $access = $formObject->$access_function();
    if (!$access) {
      return array(FALSE, "", "Field $field_name is not accessible.");
    }

    list($field, $instance, $num) = $formObject->getFieldDetails($field_name);
    $decimal_separator = $field['settings']['decimal_separator'];

    $values = self::normalizeInput($values, $decimal_separator);
    $values = self::addCorrectDecimalSeparator($values, $decimal_separator);
    $values = self::formatValuesForInput($values);

    $return = $formObject->fillMultiValued($field_name, $values);

    $return = self::normalizeInput($return, $decimal_separator);

    return array(TRUE, Utils::normalize($return), "");
  }

  public static function getEmptyValue(Form $formObject, $field_name) {
    return array('value' => '');
  }

  public static function getTriggeringElementName($field_name) {
    return $field_name . '_add_more';
  }

  /**
   * Compare the decimal values with the actual values.
   *
   * @param mixed $actual_values
   *   Following formats are acceptable:
   *   (a) 23.67
   *   (b) array(23.34, -89.12)
   *   (c) array(array('value' => 23.34), array('value' => -89.12))
   * @param mixed $values
   *   Following formats are acceptable:
   *   (a) 23.67
   *   (b) array(23.34, -89.12)
   *   (c) array(array('value' => 23.34), array('value' => -89.12))
   * @param array $field
   *   Field information array.
   *
   * @return array
   *   An array with 2 values:
   *   (a) $success: Whether the values are the equal.
   *   (b) $msg: Message in case there is an error. This will be empty if
   *   $success is TRUE.
   */
  public static function compareValues($actual_values, $values, $field) {
    $decimal_separator = $field['settings']['decimal_separator'];

    xdebug_break();
    $actual_values = self::normalizeInput(
      $actual_values,
      $decimal_separator
    );
    $values = self::normalizeInput($values, $decimal_separator);

    if (sizeof($values) != sizeof($actual_values)) {
      return array(FALSE, "Number of values do not match.");
    }

    foreach ($values as $key => $value) {
      if ($actual_values[$key] != $value) {
        return array(FALSE, "Key $key does not match.");
      }
    }

    return array(TRUE, "");
  }

  /**
   * Format the values into a standardized format for easy comparison.
   *
   * @param string|float|array $values
   *   Following formats are acceptable:
   *   (a) 23.67
   *   (b) array(23.34, -89.12)
   *   (c) array(array('value' => 23.34), array('value' => -89.12))
   * @param string $decimal_separator
   *   Decimal separator
   *
   * @return array
   *   An array in standardized format for comparison. An example is:
   *   array(23.34, -89.12)
   */
  public static function normalizeInput($values, $decimal_separator = '.') {
    $output = array();

    if (self::isValidValue($values, $decimal_separator)) {
      $output[] = str_replace($decimal_separator, '.', $values);
    }
    elseif (is_array($values)) {
      foreach ($values as $key => $value) {
        if (self::isValidValue($value, $decimal_separator)) {
          $output[] = str_replace($decimal_separator, '.', $value);
        }
        elseif (is_array($value) && array_key_exists(
            'value',
            $value
          ) && self::isValidValue($value['value'], $decimal_separator)
        ) {
          $output[] = str_replace($decimal_separator, '.', $value['value']);
        }
      }
    }

    return $output;
  }

  /**
   * Checks whether the input is a valid decimal number.
   *
   * @param mixed $value
   *   Provided value.
   * @param string $decimal_separator
   *   Decimal separator.
   *
   * @return bool
   *   Whether the provided input is a valid decimal number or not.
   */
  private static function isValidValue($value, $decimal_separator = '.') {
    if (!empty($value) && (is_string($value) || is_numeric($value))) {
      $value = str_replace($decimal_separator, '.', $value);
      if (is_numeric($value)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Replaces "." by the provided decimal separator.
   *
   * @param array $values
   *   An array of decimal values.
   * @param string $decimal_separator
   *   Decimal separator.
   *
   * @return array
   *   An array of values with "." replaced by provided decimal separator.
   */
  private static function addCorrectDecimalSeparator(
    $values,
    $decimal_separator = '.'
  ) {
    $output = array();
    foreach ($values as $value) {
      $output[] = str_replace('.', $decimal_separator, $value);
    }

    return $output;
  }

  private static function formatValuesForInput($values) {
    $output = array();
    foreach ($values as $key => $value) {
      $output[$key] = array('value' => $value);
    }

    return $output;
  }
}