<?php
/*
*  This file contains a language map to make text in the template translatable via 
*  the php gettext integration and template-key substitutions.
*/

$tpl_language_keys = array(
  'L_TITLE'             => _('Mudlet Pull-Request Snapshots'),
  'L_LOGO_ALT'          => _('Mudlet Logo'),
  'L_NAV_LINK_HOME'     => _('<a href="https://www.mudlet.org/">Home</a>'),
  'L_NAV_LINK_WIKI'     => _('<a href="https://wiki.mudlet.org/">Wiki</a>'),
  'L_NAV_LINK_FORUM'    => _('<a href="https://forums.mudlet.org/">Forum</a>'),
  'L_DESC_HEADING'      => _('Available Snapshot Files'),
  'L_DESC_TEXT'         => _('This page lists "Snapshots" of the Mudlet software built through ' .
                             'Continuous Integration services as a result of commits and pull ' .
                             'requests to the <a href="https://github.com/Mudlet/Mudlet">' .
                             'Mudlet repository on Github.com</a><br /> These files are made ' .
                             'available to enable easier testing of software changes but cannot ' .
                             'be stored indefinitely.<br /> For Mudlet Release Downloads visit: ' .
                             '<a href="https://www.mudlet.org/download/">' .
                             'https://www.mudlet.org/download/</a>'),
                             
  'L_TIME_SHOWN_IN'     => _('All times are shown in '),
  'L_LATEST_HEADING'    => _('Latest <abbr title="Public Test Build">PTB</abbr> Snapshots'),
  
  'L_FILTER_PLATFORM_TEXT'  => _('Show only files for: '),
  'L_FILTER_ALL_PLATFORMS'  => _('All Platforms'),
  'L_FILTER_SOURCE_TEXT'    => _('in'),
  'L_FILTER_SOURCE_BPR'     => _('Branches &amp; PRs'),
  'L_FILTER_SOURCE_BRO'     => _('Branches Only'),
  'L_FILTER_SOURCE_PRO'     => _('Pull-Requests Only'),
  'L_REMEMBER_VIA_COOKIE'   => _('Remember via Cookie: '),
  
  'L_FOOTER_TIME_SHOWN_IN'  => _('All times are in '),
  'L_FOOTER_STORAGE_USED'   => _('Storage Used'),
  'L_FOOTER_TAGLINE'        => _('Made with <i class="fas fa-heart" aria-hidden="true" title="heart"></i>' .
                                 '<span class="sr-only">heart</span>, ' .
                                 '<i class="fas fa-coffee" aria-hidden="true" title="coffee"></i>' .
                                 '<span class="sr-only">coffee</span>, and You.'),
  
  'L_FOOTER_LINKS' => _('<a href="https://www.mudlet.org/terms-of-service/" ' .
                        'title="Mudlet.org Terms of Service and Privacy Policy">Terms &amp; Privacy</a>' .
                        '<a href="https://www.mudlet.org/about/" title="About Mudlet">About Mudlet</a>'),
  
  'L_JS_LOCAL_TIME'     => _('local time')
);