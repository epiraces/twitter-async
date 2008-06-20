<?php
class EpiForm
{
  private $id;
  private $fields = array();
  private $slot   = 0;
  private $currentField = -1;
  private $debug = false;
  private $pass = null;
  private $fail = null;

  public static function addForm($id)
  {
    return new EpiForm($id);
  }

  public function __construct($id)
  {
    $this->id = $id;
  }

  public function addEvent($event)
  {
    $this->fields[$this->currentField]['events'][] = $event;
    return $this;
  }

  public function addField($idOfField)
  {
    $slot = pow(2, $this->slot++);
    $this->fields[++$this->currentField] = array('id' => $idOfField, 'slot' => $slot);
    return $this;
  }

  public function addMessage($msg)
  {
    $this->fields[$this->currentField]['msg'] = $msg;
  }

  public function addType($type, $params)
  {
    call_user_func(array(__CLASS__, '_'.$type), $params);
    return $this;
  }

  public function debug($bool)
  {
    $this->debug = (boolean)$bool;
    return $this;
  }

  public function overloadPass($js)
  {
    $this->pass = $js;
  }

  public function overloadFail($js)
  {
    $this->fail = $js;
  }

  public function prepareForServer()
  {
    return '<input type="hidden" name="__EpiForm__" value=\'' . json_encode($this->fields) . '\' />';
  }

  public function repopulate($str)
  {
    $fields = EpiFormServer::decode($str);
    return 'YAHOO.formValidator.repopulate(' . $fields . ');';
  }

  public function validateJS()
  {
    // YAHOO.formValidator.init({"form":"f","defs":[{"el":"i","type":"maxChars","params":5,"event":["keyup","mouseup"]}]});
    $retval = array();
    $retval['form'] = $this->id;
    $retval['debug']= $this->debug;
    $retval['defs'] = array();
    foreach($this->fields as $i => $field)
    {
      $retval['defs'][$i] = array('el' => $field['id'], 'type' => $field['type']['rule'], 'args' => $field['type']['args'], 'event' => $field['events'], 'msg' => $field['msg']);
    }

    $retval = 'YAHOO.formValidator.init(' . json_encode($retval) . ');';
    if($this->pass)
    {
      $retval .= 'YAHOO.formValidator.pass = ' . trim($this->pass) . ';';
    }
    if($this->fail)
    {
      $retval .= 'YAHOO.formValidator.fail = ' . trim($this->fail) . ';';
    }

    return $retval;
  }

  private function _maxChars($args)
  {
    $this->fields[$this->currentField]['type'] = array('rule' => 'maxChars', 'args' => (string)$args);
  }

/*  
  public static function addField($idOfField = null, $validationType = null, $prepareForServer = null)
  {
    $slot = pow(2, self::$slot++);
    self::$fields[] = array('id' => $idOfField, 'type' => $validationType, 'slot' => $slot);

      echo '
            <script>
              Event.observe(window, "load", function() {
                if($("' . $idOfField . '").readAttribute("name") == undefined)
                {
                  var rand = $("' . $idOfField . '").readAttribute("id") + parseInt(Math.random() * 100000);
                  $("' . $idOfField . '")..writeAttribute({name: "' . $idOfField. '"});
                }

                if($("' . $idOfField . '").readAttribute("epiform") == undefined)
                {
                  $("' . $idOfField . '").writeAttribute({epiform: "' . $validationType . '"});
                }';

      if($prepareForServer === true)
      {
        echo 'new Insertion.After("' . $idOfField . '", \'<input type="hidden" name="__EpiForm__[]" value="' . $slot . '~\'+$(\'' . $idOfField . '\').readAttribute(\'name\')+\'~' . $validationType . '" />\');';
      }

      echo '
              });
            </script>
            ';
  }
  
  public static function checkFields()
  {
    $retval = 0;
    if(count(self::$definitions) == 0)
    {
      self::$definitions = self::generateDefinitions();
    }

    foreach(self::$definitions as $v)
    {
      $slot = $v[0];
      $name = $v[1];
      $type = '_' . $v[2];

      if(self::$type($_REQUEST[$name]))
      {
        $retval += $slot;
      }

    }

    return $retval;
  }

  public static function getFieldsByError($errorCode = 0)
  {
    $retval = array();
    foreach(self::$fields as $field)
    {
      if(($field['slot'] & $errorCode) == $field['slot'])
      {
        $retval[] = $field['id'];
      }
    }

    return $retval;
  }

  public static function validateClient($pathToJavascript = null, $idOfForm = null, $errorClass = null)
  {
    $retval = '';

    if(!self::$javascriptIncluded)
    {
      echo '<script id="__EpiFormJavaScript__" src="' . $pathToJavascript . '"></script>';
      self::$javascriptIncluded = true;
    }
    
    echo '
          <script>
            Event.observe(window, "load", function(){
              new EpiForm($("' . $idOfForm . '"), "' . $errorClass . '"); 
            });
          </script>';
  }

  public static function _blank($value = null)
  {
    return empty($value);
  }

  private static function generateDefinitions()
  {
    $retval = array();
    foreach($_REQUEST['__EpiForm__'] as $row)
    {
      $retval[] = (array)explode('~', $row);
    }

    return $retval;
  }
*/
}

class EpiFormServer
{
  public static $definitions;

  public static function checkFields()
  {
    $retval = 0;
    if(count(self::$definitions) == 0)
    {
      self::$definitions = self::generateDefinitions();
    }

    foreach(self::$definitions as $def)
    {
      $slot = $def['slot'];
      $name = $def['id'];
      $type = '_' . $def['type']['rule'];
      $args = $def['type']['args'];

      if(self::$type($_REQUEST[$name], $args))
      {
        $retval += $slot;
      }

    }

    return $retval;
  }

  public static function encode($_post = null)
  {
    if($_post === null)
    {
      $_post = $_POST;
    }

    return base64_encode(json_encode($_post));
  }

  public static function decode($str)
  {
    return base64_decode($str);
  }

  private static function generateDefinitions()
  {
    self::$definitions = json_decode($_REQUEST['__EpiForm__'], 1);
    return self::$definitions;
  }

  private static function _maxChars($val, $args)
  {
    return mb_strlen($val) > $args; // false if longer than $args
  }
}
?>
