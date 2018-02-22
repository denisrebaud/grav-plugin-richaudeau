<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use DOMDocument;

/**
 * Class RichaudeauPlugin
 * @package Grav\Plugin
 */
class RichaudeauPlugin extends Plugin
{
  private static $previous_text = NULL;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onPageContentProcessed' => ['onPageContentProcessed', 0]
        ]);
    }

    /**
     * Apply Richaudeau's rules to a string
     *
     * @param string  $str        text to modify
     * @param string  $punctuations list of punctuations
     * @param boolean $add_extra_space
     *
     * @return string Modified text
     */
    private function add_style($str, $punctuations, $add_extra_space)
    {
error_log('str: '.$str);
error_log('punctuations: '.$punctuations);
error_log('previous_text: '.self::$previous_text);

      $classes = 'richaudeau';
      if ($add_extra_space) {
        $classes .= ' richaudeau_spaced';
        $separator = "[\s\n\r]+"; // at last one space
      }
      else {
        $separator = "[\n\r]*"; // no space, carriage return and line feed ignorer
      }
      // the string characters may content character with special meaning
      // in a regular expression, like the dot. We need to protect them.
      $max = mb_strlen($punctuations);
      $punctuations_protected = '';
      for ($i = 0; $i < $max; $i++) {
        $punctuations_protected .= sprintf('\\%s', mb_substr($punctuations, $i, 1));
      }
      $options = 'misu';
      $pattern = sprintf('/([%s]%s)(\w)/%s', $punctuations_protected, $separator, $options);
      $replace = sprintf('$1<span class="%s">$2</span>', $classes);

      // if one of the characters is used by HTML, we need to protect it
      if (strpos($punctuations, ';') !== false) {
        $str = preg_replace('/(&[^;]+);/'.$options, '$1~', $str);
        $str = preg_replace($pattern, $replace, $str);
        $str = preg_replace('/(&[^~]+)~/'.$options, '$1;', $str);
      }
      else {
        $str = preg_replace($pattern, $replace, $str);
      }

      if ((self::$previous_text !== NULL) && strcmp(self::$previous_text,'') != 0) {
        $pattern = sprintf('/[%s]%s$/%s', $punctuations_protected, $separator, $options);
        // if previous text ends with one of the punctuation
        if (preg_match($pattern, self::$previous_text)) {
          // we treat the first character
          $pattern = sprintf('/^(%s\w)/%s', $separator, $options);
          $replace = sprintf('<span class="%s">$1</span>', $classes);
          $str = preg_replace($pattern, $replace, $str);
        }
      }
      self::$previous_text = $str;
      return $str;
    }

    private function modifyChildNodes($doc, $node, $punctuation_with_extra_space, $punctuation_without_extra_space)
    {
      $dom_updated = false;
      if ($node->nodeType == XML_ELEMENT_NODE) {
        $childNode = $node->firstChild;
        while ($childNode) {
          // Recursively call modifyChildNodes
          // on each child node
            $dom_updated = $dom_updated || $this->modifyChildNodes($doc, $childNode, $punctuation_with_extra_space, $punctuation_without_extra_space);
            $childNode = $childNode->nextSibling;
        }
      } elseif ($node->nodeType == XML_TEXT_NODE) {
        $original_text = $node->nodeValue;
        if ($punctuation_with_extra_space) {
          // search punctuation which must be preceded by at least a space
          // we add two em in order to visually split the text
          $text = $this->add_style($node->nodeValue, $punctuation_with_extra_space, true);
        }
        else {
          $text = $node->nodeValue;
        }
        if ($punctuation_without_extra_space) {
          // search punctuation which must not be preceded by a space
          $text = $this->add_style($text, $punctuation_without_extra_space, false);
        }
        // remplace the current node by the new HTML code only in case of changes
        if (strcmp($original_text, $text) != 0) {
          $dom_updated = true;
          $new_element = $doc->createDocumentFragment();
          $new_element->appendXML($text);
          $node->parentNode->replaceChild($new_element, $node);
        }
      }
      return $dom_updated;
    }

    /**
     * Apply typography filter to content,
     * when each page has not been cached yet.
     *
     * @param  Event  $event The event when 'onPageContentProcessed' was
     *                       fired.
     */
    public function onPageContentProcessed(Event $e)
    {
      $page_modified = false;
      $page = $e['page'];
      $config = $this->mergeConfig($page);

      // Get the current content
      $content = $page->getRawContent();
      // if empty page, nothing to do.
      if (!$content) {
        return;
      }
      $delim = '/,\s*/u';

      $readability_enable = $config->get('readability.enable');
      // Get the tags list from the plugin configuration
      $tags = $config->get('readability.tags');
      if ($readability_enable && $tags) {
        $tags = preg_split($delim,$tags);
        $weight = $config->get('readability.weight');
        // Get the ponctuation list from the plugin configuration
        $punctuation_with_extra_space = $config->get('readability.punctuation_with_extra_space');
        // Get the ponctuation list from the plugin configuration
        $punctuation_without_extra_space = $config->get('readability.punctuation_without_extra_space');

        $doc = new DOMDocument('1.0', 'UTF-8');
        if ($doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'))) {
          $css = sprintf('span.richaudeau{font-weight:%s;}span.richaudeau_spaced::before{content:"\00A0";}', $weight);
          foreach($tags as $tag) {
            $elements = $doc->getElementsByTagName($tag);
            if ($elements->length > 0) {
              $css = sprintf('%s::first-letter,%s', $tag, $css);
              $elements_modified = false;
              foreach($elements as $element) {
                self::$previous_text = NULL;
                $elements_modified = $this->modifyChildNodes($doc, $element, $punctuation_with_extra_space, $punctuation_without_extra_space) || $elements_modified;
              }
              if ($elements_modified) {
                $content = $doc->saveHTML();
                $page_modified = true;
              }
            }
          }
          $this->grav['assets']->addInlineCss($css);
        }
      }

      $typography_enable = $config->get('typography.enable');
      if ($typography_enable) {
        $page_modified = true;
        include_once('php-typography/php-typography.php');
        $typo = new \phpTypography();
        $typo->set_defaults();

        $tags_to_ignore = $config->get('typography.tags_to_ignore');
        if ($tags_to_ignore) {
            $typo->set_​tags_​to_​ignore(preg_split($delim,$tags_to_ignore));
        }

        $classes_to_ignore = $config->get('typography.classes_to_ignore');
        if ($classes_to_ignore) {
            $typo->set_​classes_​to_​ignore(preg_split($delim,$classes_to_ignore));
        }

        $ids_to_ignore = $config->get('typography.ids_to_ignore');
        if ($ids_to_ignore) {
            $typo->set_​ids_​to_​ignore(preg_split($delim,$ids_to_ignore));
        }

        $hyphenation = $config->get('typography.hyphenation');
        if ($hyphenation) {
          $typo->set_hyphenation($hyphenation);
        }

        $language = $page->language();
        $code_language = sprintf('%s-%s', $language, mb_strtoupper($language));
        $typo->set_hyphenation_language($code_language);

        $​min_length_hyphenation = $config->get('typography.​min_length_hyphenation');
        if (is_int($​min_length_hyphenation)) {
            $typo->set_min_length_hyphenation($​min_length_hyphenation);
        }

        $​min_before_hyphenation = $config->get('typography.​min_before_hyphenation');
        if (is_int($​min_before_hyphenation)) {
            $typo->set_min_before_hyphenation($​min_before_hyphenation);
        }

        $​min_after_hyphenation = $config->get('typography.​min_after_hyphenation');
        if (is_int($​min_after_hyphenation)) {
            $typo->set_min_after_hyphenation($​min_after_hyphenation);
        }

        $hyphenate_headings = $config->get('typography.hyphenate_headings');
        if ($hyphenate_headings) {
          $typo->set_hyphenate_headings($hyphenate_headings);
        }

        $hyphenate_title_case = $config->get('typography.hyphenate_title_case');
        if ($hyphenate_title_case) {
          $typo->set_hyphenate_title_case($hyphenate_title_case);
        }

        $hyphenate_all_caps = $config->get('typography.hyphenate_all_caps');
        if ($hyphenate_all_caps) {
          $typo->set_hyphenate_all_caps($hyphenate_all_caps);
        }

        $hyphenation_exceptions = $config->get('typography.hyphenation_exceptions');
        if ($hyphenation_exceptions) {
            $typo->set_hyphenation_exceptions(preg_split($delim,$hyphenation_exceptions));
        }

        $fraction_spacing = $config->get('typography.fraction_spacing');
        if ($fraction_spacing) {
          $typo->set_fraction_spacing($fraction_spacing);
        }

        $unit_spacing = $config->get('typography.unit_spacing');
        if ($unit_spacing) {
          $typo->set_unit_spacing($unit_spacing);
        }

        $units = $config->get('typography.units');
        if ($units) {
            $typo->set_units(preg_split($delim,$units));
        }

        $dash_spacing = $config->get('typography.dash_spacing');
        if ($dash_spacing) {
          $typo->set_dash_spacing($dash_spacing);
        }

        $single_character_word_spacing = $config->get('typography.single_character_word_spacing');
        if ($single_character_word_spacing) {
          $typo->set_single_character_word_spacing($single_character_word_spacing);
        }

        $space_collapse = $config->get('typography.space_collapse');
        if ($space_collapse) {
          $typo->set_space_collapse($space_collapse);
        }

        $dewidow = $config->get('typography.dewidow');
        if ($dewidow) {
          $typo->set_dewidow($dewidow);
        }

        $max_dewidow_length = $config->get('typography.max_dewidow_length');
        if ($max_dewidow_length) {
          $typo->set_max_dewidow_length($max_dewidow_length);
        }


// continuer d'ajouter ici la prise en compte des autres réglages



        if (strcmp($language, 'fr') == 0) {
          $typo->set_smart_quotes_primary('dou­bleGuillemets­French');
          $typo->set_smart_diacritics(false); // pour éviter d'altérer les négations en français (il né faut pas)
        }

        $content = $typo->process($content);
      }
      if ($page_modified) {
        $page->setRawContent($content);
      }
    }
}
