<?php

/**
*  wrapper for __ function (translate) with optional default translation
**/
function ___($text, $domain = 'default', $default = null) {

  $translation = __($text, $domain);

  if($translation == $text && isset($default)) {
    $translation = $default;
  }

  return $translation;
}

?>
