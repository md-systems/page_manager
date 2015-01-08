/**
 * @file
 * Attaches entity-type selection behaviors to the static context selection form.
 *
 * Based on dynamic-entity-reference-widget.js from
 * http://drupal.org/project/dynamic_entity_reference.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.pageManagerDynamicEntityReferenceAutocomplete = {
    attach: function (context) {
      var $context = $(context);
      var $selects = $context.find('select.page-manager-autocomplete-reference-update').once('page-manager-autocomplete-reference-update');
      if ($selects.length) {
        $selects.change(function() {
          var $select = $(this);
          var $autocomplete = $select.parents('.autocomplete-reference').find('.form-autocomplete');
          var basePath;
          if (!(basePath = $autocomplete.data('base-autocomplete-path'))) {
            // This is the first time this has run, copy the default value.
            basePath = $autocomplete.attr('data-autocomplete-path');
            // By default, the base path contains the default suffix, so cut
            // that off.
            basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
            // Store for subsequent calls.
            $autocomplete.data('base-autocomplete-path', basePath);
          }
          $autocomplete.attr('data-autocomplete-path', basePath + $select.val());
        });
        $selects.change();
      }
    }
  };

})(jQuery);
