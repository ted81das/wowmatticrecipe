<?php defined('WPINC') or die ?><?php

// Your PHP code goes here!
add_filter('gettext', 'change_bb_admin_text', 20, 3);
function change_bb_admin_text($translated_text, $text, $domain)
{
  if ('fl-builder' == $domain)
  {
    switch ($translated_text)
    {
      case 'Beaver Builder':
        $translated_text = __('WowBuilder', $domain);
      break;
    }
  }

  return $translated_text;
}

// Your PHP code goes here!
add_filter('gettext', 'change_aimattic_admin_text', 20, 3);
function change_aimattic_admin_text($translated_text, $text, $domain)
{
  if ('aiomatic-automatic-ai-content-writer' == $domain)
  {
    switch ($translated_text)
    {
      case 'Aiomatic':
        $translated_text = __('Wowmattic', $domain);
      break;
    }
  }

  return $translated_text;
}