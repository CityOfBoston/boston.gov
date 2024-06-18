<?php

/**
 * @file
 * Theme settings for Boston theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function bos_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state, $form_id = NULL) {
  // Work-around for a core bug affecting admin themes. See issue #943212.
  if (isset($form_id)) {
    return;
  }

  // Create the form using Forms API.
  $form['breadcrumb'] = [
    '#type' => 'fieldset',
    '#title' => t('Breadcrumb settings'),

    'boston_breadcrumb' => [
      '#type' => 'select',
      '#title' => t('Display breadcrumb'),
      '#default_value' => theme_get_setting('boston_breadcrumb'),
      '#options' => [
        'yes' => t('Yes'),
        'admin' => t('Only in admin section'),
        'no' => t('No'),
      ],
    ],
    'breadcrumb_options' => [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[name="boston_breadcrumb"]' => ['value' => 'no'],
        ],
      ],

      'boston_breadcrumb_separator' => [
        '#type' => 'textfield',
        '#title' => t('Breadcrumb separator'),
        '#description' => t('Text only. Don’t forget to include spaces.'),
        '#default_value' => theme_get_setting('boston_breadcrumb_separator'),
        '#size' => 5,
        '#maxlength' => 10,
      ],
      'boston_breadcrumb_home' => [
        '#type' => 'checkbox',
        '#title' => t('Show home page link in breadcrumb'),
        '#default_value' => theme_get_setting('boston_breadcrumb_home'),
      ],
      'boston_breadcrumb_trailing' => [
        '#type' => 'checkbox',
        '#title' => t('Append a separator to the end of the breadcrumb'),
        '#default_value' => theme_get_setting('boston_breadcrumb_trailing'),
        '#description' => t('Useful when the breadcrumb is placed just before the title.'),
        '#states' => [
          'disabled' => [
            ':input[name="boston_breadcrumb_title"]' => ['checked' => TRUE],
          ],
        ],
      ],
      'boston_breadcrumb_title' => [
        '#type' => 'checkbox',
        '#title' => t('Append the content title to the end of the breadcrumb'),
        '#default_value' => theme_get_setting('boston_breadcrumb_title'),
        '#description' => t('Useful when the breadcrumb is not placed just before the title.'),
      ],
    ],
  ];

  $form['support'] = [
    '#type' => 'fieldset',
    '#title' => t('Accessibility and support settings'),

    'boston_skip_link_anchor' => [
      '#type' => 'textfield',
      '#title' => t('Anchor ID for the “skip link”'),
      '#default_value' => theme_get_setting('boston_skip_link_anchor'),
      '#field_prefix' => '#',
      '#description' => t('Specify the HTML ID of the element that the accessible-but-hidden “skip link” should link to. Note: that element should have the <code>tabindex="-1"</code> attribute to prevent an accessibility bug in webkit browsers. (<a href="@link">Read more about skip links</a>.)', ['@link' => 'https://drupal.org/node/467976']),
    ],
    'boston_skip_link_text' => [
      '#type' => 'textfield',
      '#title' => t('Text for the “skip link”'),
      '#default_value' => theme_get_setting('boston_skip_link_text'),
      '#description' => t('For example: <em>Jump to navigation</em>, <em>Skip to content</em>'),
    ],
    'boston_meta' => [
      '#type' => 'checkboxes',
      '#title' => t('Add HTML5 and responsive scripts and meta tags to every page.'),
      '#default_value' => theme_get_setting('boston_meta'),
      '#options' => [
        'html5' => t('Add HTML5 shim JavaScript to add support to IE 6-8.'),
        'meta' => t('Add meta tags to support responsive design on mobile devices.'),
      ],
      '#description' => t('IE 6-8 require a JavaScript polyfill solution to add basic support of HTML5. Mobile devices require a few meta tags for responsive designs.'),
    ],
  ];

  $libs = \Drupal::service('library.discovery')
    ->getLibrariesByExtension('bos_theme');
  $opts = [];
  foreach ($libs as $libname => $lib) {
    if (!empty($lib['data']['name'])) {
      $opts[$libname] = $lib['data']['name'];
    }
  }

  $form['style'] = [
    '#type' => 'fieldset',
    '#title' => t('Defines location of the core style assets.'),

    'asset_source' => [
      '#type' => 'select',
      '#title' => t('Core css source'),
      '#default_value' => theme_get_setting('asset_source'),
      '#options' => $opts,
    ],
  ];

  if ($form['var']['#value'] == 'theme_boston_settings') {
    $form['support']['boston_layout'] = [
      '#type' => 'radios',
      '#title' => t('Layout'),
      '#options' => [
        'boston-responsive-sidebars' => t('Responsive sidebar layout <small>(layouts/responsive-sidebars.css)</small>'),
        'boston-fixed-width' => t('Fixed width layout <small>(layouts/fixed-width.css)</small>'),
      ],
      '#default_value' => theme_get_setting('boston_layout'),
    ];
  }

  $form['error-pages'] = [
    '#type' => 'fieldset',
    '#title' => t('Site Error Page Settings'),
    '403-page' => [
      '#type' => 'textarea',
      '#title' => t('403-Page (Permission Denied)'),
      '#description' => t('Adds text for the themes 403 page. <br/>(<i>page is defined in themes/custom/bos_theme/templates/misc/page--403.html.twig</i>)'),
      '#default_value' => theme_get_setting('403-page'),
      '#rows' => 5,
      '#theme' => 'textarea',
      '#resizable' => 'vertical',
    ],
    '404-page' => [
      '#type' => 'textarea',
      '#title' => t('404-Page (Page Not Found)'),
      '#description' => t('Adds text for the themes 404 page. <br/>(<i>page is defined in themes/custom/bos_theme/templates/misc/page--404.html.twig</i>)'),
      '#default_value' => theme_get_setting('404-page'),
      '#rows' => 5,
      '#theme' => 'textarea',
      '#resizable' => 'vertical',
    ],
  ];

  $form['themedev'] = [
    '#type' => 'fieldset',
    '#title' => t('Theme development settings'),

  ];

}
