<?php
/**
 * includes/brand.php
 * Renders the TrafStat mark as inline SVG. Call trafstat_mark() anywhere
 * you need the logo — sidebar, public header, login card, favicon
 * fallback in the HTML <head>.
 */
if (!defined('APP_NAME')) {
    exit('Direct access denied.');
}

/**
 * Renders the TrafStat brand mark as an inline SVG.
 *
 * @param string $class Additional CSS classes to apply.
 * @param int    $size  Width/height in pixels.
 * @param bool   $title When true, includes an accessible <title>.
 */
function trafstat_mark(string $class = '', int $size = 40, bool $title = false): string
{
    // Unique gradient ID so multiple instances don't collide.
    static $instance = 0;
    $instance++;
    $gid   = 'traf-g' . $instance;
    $cls   = $class !== '' ? ' class="' . e($class) . '"' : '';
    $titleTag = $title ? '<title>' . e(APP_NAME) . '</title>' : '';

    return <<<SVG
<svg{$cls} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="{$size}" height="{$size}" role="img" aria-label="TrafStat">
  {$titleTag}
  <defs>
    <linearGradient id="{$gid}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="#F97316"/>
      <stop offset="100%" stop-color="#C2410C"/>
    </linearGradient>
  </defs>
  <rect width="48" height="48" rx="12" fill="url(#{$gid})"/>
  <rect x="11" y="9" width="9" height="30" rx="4.5" fill="#fff" opacity="0.18"/>
  <circle cx="15.5" cy="15"   r="2" fill="#fff"/>
  <circle cx="15.5" cy="21.5" r="2" fill="#fff" opacity="0.7"/>
  <circle cx="15.5" cy="28"   r="2" fill="#fff" opacity="0.4"/>
  <rect x="26"   y="26" width="5.5" height="13" rx="1.75" fill="#fff"/>
  <rect x="33.5" y="20" width="5.5" height="19" rx="1.75" fill="#fff"/>
</svg>
SVG;
}